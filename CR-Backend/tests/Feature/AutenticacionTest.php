<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'ClaveSegura#2026';

    public function test_login_correcto_entrega_token_y_usuario(): void
    {
        $user = $this->crearUsuario('admin');

        $r = $this->postJson('/api/login', ['email' => $user->email, 'password' => self::CLAVE]);

        $r->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.user.email', $user->email)
          ->assertJsonStructure(['data' => ['token', 'debe_cambiar_password', 'es_institucional']]);
        $this->assertNotEmpty($r->json('data.token'));
    }

    public function test_los_tres_motivos_de_rechazo_dan_el_mismo_mensaje(): void
    {
        // OWASP: no se puede distinguir "no existe" de "clave mala" ni de "cuenta inactiva".
        $activo   = $this->crearUsuario('admin');
        $inactivo = $this->crearUsuario('rrhh', ['estado_registro' => 'inactivo']);

        $mensajes = [
            $this->postJson('/api/login', ['email' => 'nadie@prueba.test', 'password' => self::CLAVE])->json('errors.email.0'),
            $this->postJson('/api/login', ['email' => $activo->email, 'password' => 'mala'])->json('errors.email.0'),
            $this->postJson('/api/login', ['email' => $inactivo->email, 'password' => self::CLAVE])->json('errors.email.0'),
        ];

        $this->assertNotNull($mensajes[0]);
        $this->assertCount(1, array_unique($mensajes));
    }

    public function test_login_valida_el_formato_de_los_datos(): void
    {
        $this->postJson('/api/login', ['email' => 'no-es-correo', 'password' => 'x'])->assertStatus(422);
        $this->postJson('/api/login', [])->assertStatus(422);
    }

    public function test_un_nuevo_login_cierra_las_sesiones_anteriores(): void
    {
        $user = $this->crearUsuario('admin');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => self::CLAVE]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => self::CLAVE]);

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_el_registro_publico_esta_cerrado(): void
    {
        $this->postJson('/api/register', ['email' => 'a@b.co', 'password' => 'x'])->assertForbidden();
    }

    public function test_las_rutas_protegidas_exigen_sesion(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/employees')->assertUnauthorized();
        $this->getJson('/api/payrolls')->assertUnauthorized();
    }

    public function test_me_devuelve_al_usuario_de_la_sesion(): void
    {
        $user = $this->crearUsuario('empleado');

        $this->actingAs($user, 'sanctum')->getJson('/api/me')
             ->assertOk()->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_borra_el_token(): void
    {
        $user = $this->crearUsuario('admin');
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => self::CLAVE])->json('data.token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_quien_debe_cambiar_su_clave_no_pasa_a_otras_pantallas(): void
    {
        $user = $this->crearUsuario('admin', ['debe_cambiar_password' => true]);

        // 423 Locked: solo le quedan /me, /logout y /change-password.
        $this->actingAs($user, 'sanctum')->getJson('/api/employees')->assertStatus(423);
        $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk();
    }

    public function test_quien_no_firmo_los_terminos_queda_trabado(): void
    {
        $user = $this->crearUsuario('admin');
        $user->forceFill(['terminos_firmados' => false])->save();

        // 428 Precondition Required: falta el paso previo de firmar.
        $this->actingAs($user, 'sanctum')->getJson('/api/employees')->assertStatus(428);
        $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk();
    }

    public function test_el_limite_de_intentos_frena_la_fuerza_bruta(): void
    {
        $user = $this->crearUsuario('admin');

        $ultimo = null;
        for ($i = 0; $i < 30; $i++) {
            $ultimo = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'mala']);
            if ($ultimo->status() === 429) {
                break;
            }
        }

        $this->assertSame(429, $ultimo->status(), 'tras varios intentos fallidos llega el 429');
    }
}
