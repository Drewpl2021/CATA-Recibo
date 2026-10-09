<?php

namespace Tests\Feature;

use App\Mail\AccesoAlSistema;
use App\Models\Area;
use App\Models\Cargo;
use App\Models\Rol;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * El primer acceso, por correo y nunca con el DNI: la cuenta nace con una
 * clave que nadie conoce y la persona crea la suya con el enlace de «Tu
 * acceso a CATA-Recibo» (72 h, un solo uso).
 */
class AccesoPorCorreoTest extends TestCase
{
    use RefreshDatabase;

    private const DNI = '45678912';
    private const CORREO = 'rosa.quispe@prueba.test';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Rol::firstOrCreate(['nombre' => 'empleado'], ['descripcion' => 'empleado']);
    }

    private function darDeAlta(array $cambios = [])
    {
        return $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->postJson('/api/employees', array_merge([
            'dni' => self::DNI, 'nombre' => 'Rosa', 'apellido' => 'Quispe',
            'cargo_id' => Cargo::create(['nombre' => 'Docente'])->id,
            'area_id' => Area::create(['nombre' => 'Secundaria'])->id,
            'sede_id' => Sede::create(['nombre' => 'CATA Central'])->id,
            'telefono' => '987654321', 'direccion' => 'Jr. Lima 123',
            'fecha_ingreso' => '2026-03-01', 'fecha_nacimiento' => '1990-05-10', 'sueldo_base' => 2500,
            'tipo_contrato_id' => $this->idTipoContrato('Plazo indeterminado'),
            'email' => self::CORREO,
        ], $cambios))->assertCreated();
    }

    /** El token del último correo de acceso que le llegó a ese correo. */
    private function tokenDelCorreo(string $correo = self::CORREO): string
    {
        $token = null;
        Mail::assertQueued(AccesoAlSistema::class, function (AccesoAlSistema $m) use ($correo, &$token) {
            if ($m->email !== $correo) {
                return false;
            }
            parse_str(parse_url($m->enlace, PHP_URL_QUERY), $q);
            $token = $q['token'];

            return true;
        });

        return $token;
    }

    private function entrar(string $clave)
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/login', ['email' => self::CORREO, 'password' => $clave]);
    }

    public function test_el_alta_no_usa_el_dni_y_le_manda_su_acceso(): void
    {
        $this->darDeAlta()->assertJsonPath('mensaje', fn ($m) => str_contains($m, self::CORREO));

        $cuenta = User::where('email', self::CORREO)->firstOrFail();
        $this->assertFalse(Hash::check(self::DNI, $cuenta->password), 'La cuenta nació con el DNI como contraseña.');
        $this->assertNotNull($cuenta->acceso_enviado_en);
        Mail::assertQueued(AccesoAlSistema::class, 1);

        // Con el DNI ya no se entra.
        $intento = $this->entrar(self::DNI);
        $this->assertContains($intento->status(), [401, 422]);
        $this->assertNull($intento->json('data.token'));
    }

    public function test_crea_su_contrasena_con_el_enlace_una_sola_vez(): void
    {
        $this->darDeAlta();
        $token = $this->tokenDelCorreo();

        // El DNI no vale como contraseña nueva.
        $this->postJson('/api/create-password', [
            'token' => $token, 'email' => self::CORREO, 'password' => self::DNI, 'password_confirmation' => self::DNI,
        ])->assertStatus(422);

        $this->postJson('/api/create-password', [
            'token' => $token, 'email' => self::CORREO, 'password' => 'Juliaca2026', 'password_confirmation' => 'Juliaca2026',
        ])->assertOk();

        $this->entrar('Juliaca2026')->assertOk();
        $this->assertFalse((bool) User::where('email', self::CORREO)->value('debe_cambiar_password'));

        // Ya se usó: no sirve otra vez.
        $this->postJson('/api/create-password', [
            'token' => $token, 'email' => self::CORREO, 'password' => 'OtraClave99', 'password_confirmation' => 'OtraClave99',
        ])->assertStatus(422);
    }

    public function test_el_enlace_vence_a_las_72_horas_y_no_vale_como_olvide_mi_contrasena(): void
    {
        $this->darDeAlta();
        $token = $this->tokenDelCorreo();

        // Un enlace de acceso no sirve en «olvidé mi contraseña» (otra tabla).
        $this->postJson('/api/reset-password', [
            'token' => $token, 'email' => self::CORREO, 'password' => 'Juliaca2026', 'password_confirmation' => 'Juliaca2026',
        ])->assertStatus(422);

        $this->travel(73)->hours();
        $this->postJson('/api/create-password', [
            'token' => $token, 'email' => self::CORREO, 'password' => 'Juliaca2026', 'password_confirmation' => 'Juliaca2026',
        ])->assertStatus(422)->assertJsonPath('errors.token.0', fn ($m) => str_contains($m, 'venció'));
    }

    public function test_enviar_acceso_a_los_marcados(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $conCuenta = $this->crearEmpleado(['dni' => '40000001']);
        $usuario = $this->crearUsuario('empleado');
        $usuario->forceFill(['empleado_id' => (string) $conCuenta->id, 'password' => Hash::make('ClaveVieja1')])->save();
        $sinCuenta = $this->crearEmpleado(['dni' => '40000002']);

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/employees/send-access', [
            'ids' => [(string) $conCuenta->id, (string) $sinCuenta->id],
        ])->assertOk()->assertJsonPath('data.enviados', 1)->assertJsonCount(1, 'data.omitidos');

        Mail::assertQueued(AccesoAlSistema::class, fn ($m) => $m->email === $usuario->email);
        // La clave que tenía deja de servir.
        $this->assertFalse(Hash::check('ClaveVieja1', $usuario->fresh()->password));

        // Un trabajador no puede mandar accesos.
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')->postJson('/api/employees/send-access', ['ids' => [(string) $conCuenta->id]])->assertForbidden();
    }

    public function test_restablecer_de_rrhh_nunca_pone_el_dni(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $empleado = $this->crearEmpleado(['dni' => '40000003']);
        $usuario = $this->crearUsuario('empleado');
        $usuario->forceFill(['empleado_id' => (string) $empleado->id])->save();

        // Con correo: le llega el enlace.
        $this->actingAs($rrhh, 'sanctum')->postJson("/api/users/{$usuario->id}/reset-password")
            ->assertOk()->assertJsonPath('data.por_correo', true)->assertJsonMissingPath('data.password_temporal');
        Mail::assertQueued(AccesoAlSistema::class, fn ($m) => $m->email === $usuario->email);
        $this->assertFalse(Hash::check('40000003', $usuario->fresh()->password));

        // Sin correo válido: una clave temporal aleatoria, que no es el DNI.
        $usuario->forceFill(['email' => 'sin-correo-' . $usuario->id])->save();
        $temporal = $this->actingAs($rrhh, 'sanctum')->postJson("/api/users/{$usuario->id}/reset-password")
            ->assertOk()->assertJsonPath('data.por_correo', false)->json('data.password_temporal');
        $this->assertNotSame('40000003', $temporal);
        $this->assertGreaterThanOrEqual(12, strlen($temporal));
        $this->assertTrue(Hash::check($temporal, $usuario->fresh()->password));
    }
}
