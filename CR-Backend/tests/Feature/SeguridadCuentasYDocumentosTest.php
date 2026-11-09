<?php

namespace Tests\Feature;

use App\Mail\AccesoAlSistema;
use App\Mail\RestablecerPassword;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\User;
use App\Models\Vacacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Lo que una cuenta de gestión no debe poder hacer con las cuentas y los documentos ajenos. */
class SeguridadCuentasYDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'ClaveSegura#2026';

    public function test_el_administrador_edita_usuarios_y_cambia_el_rol(): void
    {
        $admin = $this->crearUsuario('admin');
        $otro = $this->crearUsuario('empleado');
        $rrhh = Rol::where('nombre', 'rrhh')->value('id');

        $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$otro->id}", ['name' => 'Nuevo', 'email' => 'nuevo@prueba.test', 'rol_id' => $rrhh, 'empleado_id' => null])
            ->assertOk();

        $this->assertSame('nuevo@prueba.test', $otro->fresh()->email);
        $this->assertSame($rrhh, $otro->fresh()->rol_id);
    }

    public function test_la_migracion_marca_al_administrador_si_nadie_tiene_la_marca(): void
    {
        $this->sembrarRolesYModulos();
        DB::table('roles')->update(['es_superadmin' => false]);
        (require database_path('migrations/2026_11_04_000001_marcar_superadministrador_inicial.php'))->up();

        $this->assertTrue((bool) Rol::where('nombre', 'admin')->value('es_superadmin'));
        $this->assertFalse((bool) Rol::where('nombre', 'rrhh')->value('es_superadmin'));
    }

    public function test_el_formulario_de_rrhh_no_falla_si_el_rol_no_cambia(): void
    {
        $otro = $this->crearUsuario('empleado');

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->putJson("/api/users/{$otro->id}", [
            'name' => 'Corregido', 'email' => $otro->email, 'rol_id' => $otro->rol_id, 'empleado_id' => null,
        ])->assertOk();

        $this->assertSame('Corregido', $otro->fresh()->name);
    }

    public function test_rrhh_no_toma_la_cuenta_de_un_administrador(): void
    {
        Mail::fake();
        $admin = $this->crearUsuario('admin', ['email' => 'jefe@prueba.test']);

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->putJson("/api/users/{$admin->id}", ['email' => 'otro@prueba.test'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/forgot-password', ['email' => 'otro@prueba.test'])->assertOk();

        $this->assertSame('jefe@prueba.test', $admin->fresh()->email);
        Mail::assertNotQueued(RestablecerPassword::class);
    }

    public function test_rrhh_no_se_vincula_a_otro_para_firmar_por_el(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $victima = $this->crearEmpleado();

        $this->actingAs($rrhh, 'sanctum')->putJson("/api/users/{$rrhh->id}", ['empleado_id' => $victima->id])->assertForbidden();

        $this->assertNull($rrhh->fresh()->empleado_id);
    }

    public function test_una_ficha_no_se_vincula_a_dos_cuentas_y_el_cambio_queda_en_auditoria(): void
    {
        $ficha = $this->crearEmpleado();
        $this->crearUsuario('empleado', ['empleado_id' => $ficha->id]);
        $otro = $this->crearUsuario('empleado');
        $admin = $this->crearUsuario('admin');

        $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$otro->id}", ['empleado_id' => $ficha->id])->assertStatus(422);

        $libre = $this->crearEmpleado();
        $this->putJson("/api/users/{$otro->id}", ['empleado_id' => $libre->id])->assertOk();
        $this->assertTrue(Auditoria::where('entidad', 'usuario')->where('cambios', 'like', '%empleado_id%')->exists());
    }

    /** Darlo de baja desde su ficha sigue siendo de RR.HH. (ver CuentasProtegidasTest). */
    public function test_rrhh_no_toca_la_cuenta_de_un_administrador_desde_usuarios_ni_con_accesos(): void
    {
        Mail::fake();
        $rrhh = $this->crearUsuario('rrhh');
        $ficha = $this->crearEmpleado();
        $admin = $this->crearUsuario('admin', ['empleado_id' => $ficha->id]);
        $clave = $admin->password;

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/employees/send-access', ['ids' => [$ficha->id]])
            ->assertOk()->assertJsonPath('data.enviados', 0);
        $this->postJson("/api/users/{$admin->id}/reset-password")->assertForbidden();
        $this->deleteJson("/api/users/{$admin->id}")->assertForbidden();

        $this->assertSame($clave, $admin->fresh()->password);
        $this->assertSame('activo', $admin->fresh()->estado_registro);
        Mail::assertNothingOutgoing();
    }

    public function test_no_se_puede_dejar_el_sistema_sin_administrador(): void
    {
        $admin = $this->crearUsuario('admin');
        $rrhh = Rol::where('nombre', 'rrhh')->value('id');

        $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$admin->id}", ['rol_id' => $rrhh])->assertStatus(422);
        $this->putJson("/api/users/{$admin->id}", ['estado_registro' => 'inactivo'])->assertStatus(422);
        $this->deleteJson("/api/users/{$admin->id}")->assertStatus(422);

        $this->assertTrue($admin->fresh()->esSuperadmin());
    }

    public function test_nadie_alcanza_un_archivo_ajeno_con_un_documento_propio(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documentos/ajeno/boleta.pdf', 'PRIVADO');
        $this->sembrarRolesYModulos();
        Rol::firstOrCreate(['nombre' => 'contratos'], ['descripcion' => 'x'])->modulos()->sync([Modulo::where('ruta', '/contratos')->value('id')]);
        \App\Support\Permisos::refrescar();
        $mio = $this->crearEmpleado();
        $cuenta = $this->crearUsuario('contratos', ['empleado_id' => $mio->id]);

        $this->actingAs($cuenta, 'sanctum')->postJson('/api/documents', ['empleado_id' => $mio->id, 'tipo' => 'hoja_de_vida', 'archivo' => 'documentos/ajeno/boleta.pdf'])
            ->assertStatus(422);

        Storage::disk('local')->put("documentos/{$mio->id}/cv.pdf", 'MIO');
        $doc = $this->postJson('/api/documents', ['empleado_id' => $mio->id, 'tipo' => 'hoja_de_vida', 'archivo' => "documentos/{$mio->id}/cv.pdf"])->assertCreated()->json('data.id');
        $this->putJson("/api/documents/{$doc}", ['archivo' => 'documentos/ajeno/boleta.pdf'])->assertStatus(422);
    }

    public function test_un_documento_firmado_no_se_cambia_ni_se_borra_y_lo_demas_queda_en_auditoria(): void
    {
        Storage::fake('local');
        $e = $this->crearEmpleado();
        Storage::disk('local')->put("documentos/{$e->id}/a.pdf", 'A');
        Storage::disk('local')->put("documentos/{$e->id}/b.pdf", 'B');
        $firmado = Documento::create(['empleado_id' => $e->id, 'tipo' => 'contrato', 'archivo' => "documentos/{$e->id}/a.pdf", 'estado_firma' => 'firmado', 'estado_registro' => 'activo']);
        $abierto = Documento::create(['empleado_id' => $e->id, 'tipo' => 'contrato', 'archivo' => "documentos/{$e->id}/a.pdf", 'estado_firma' => 'pendiente', 'estado_registro' => 'activo']);
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        $this->putJson("/api/documents/{$firmado->id}", ['archivo' => "documentos/{$e->id}/b.pdf"])->assertStatus(409);
        $this->deleteJson("/api/documents/{$firmado->id}")->assertStatus(409);
        $this->assertSame("documentos/{$e->id}/a.pdf", $firmado->fresh()->archivo);

        $this->putJson("/api/documents/{$abierto->id}", ['archivo' => "documentos/{$e->id}/b.pdf"])->assertOk();
        $this->deleteJson("/api/documents/{$abierto->id}")->assertOk();
        $this->assertSame(2, Auditoria::where('entidad', 'documento')->count());
    }

    public function test_la_huella_de_la_firma_es_la_del_archivo_que_queda(): void
    {
        Storage::fake('local');
        $e = $this->crearEmpleado();
        Storage::disk('local')->put("documentos/{$e->id}/c.pdf", 'CONTENIDO');
        $doc = Documento::create(['empleado_id' => $e->id, 'tipo' => 'contrato', 'archivo' => "documentos/{$e->id}/c.pdf", 'estado_firma' => 'pendiente', 'estado_registro' => 'activo']);
        $cuenta = $this->crearUsuario('empleado', ['empleado_id' => $e->id]);

        $this->actingAs($cuenta, 'sanctum')->postJson("/api/my-documents/{$doc->id}/sign", ['password' => self::CLAVE])->assertOk();

        $this->assertSame(hash('sha256', 'CONTENIDO'), $doc->fresh()->conformidad_sha256);
        $this->postJson("/api/my-documents/{$doc->id}/sign", ['password' => self::CLAVE])->assertStatus(422);
    }

    public function test_un_documento_dado_de_baja_no_se_firma(): void
    {
        Storage::fake('local');
        $e = $this->crearEmpleado();
        $doc = Documento::create(['empleado_id' => $e->id, 'tipo' => 'contrato', 'archivo' => 'documentos/x.pdf', 'estado_firma' => 'pendiente', 'estado_registro' => 'inactivo']);

        $this->actingAs($this->crearUsuario('empleado', ['empleado_id' => $e->id]), 'sanctum')
            ->postJson("/api/my-documents/{$doc->id}/sign", ['password' => self::CLAVE])->assertNotFound();
    }

    public function test_enviar_el_acceso_no_le_quita_la_clave_a_nadie(): void
    {
        Mail::fake();
        $e = $this->crearEmpleado();
        $cuenta = $this->crearUsuario('empleado', ['empleado_id' => $e->id]);

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->postJson('/api/employees/send-access', ['ids' => [$e->id]])
            ->assertOk()->assertJsonPath('data.enviados', 1);

        Mail::assertQueued(AccesoAlSistema::class);
        $this->assertTrue(Hash::check(self::CLAVE, $cuenta->fresh()->password));
    }

    public function test_si_el_correo_falla_al_restablecer_la_clave_no_cambia(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        $e = $this->crearEmpleado();
        $cuenta = $this->crearUsuario('empleado', ['empleado_id' => $e->id]);
        $cuenta->createToken('sesion');

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->postJson("/api/users/{$cuenta->id}/reset-password")
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'contraseña no cambió'));

        $this->assertTrue(Hash::check(self::CLAVE, $cuenta->fresh()->password));
        $this->assertSame(1, $cuenta->tokens()->count());
    }

    public function test_si_el_correo_sale_al_restablecer_se_anula_la_clave(): void
    {
        Mail::fake();
        $e = $this->crearEmpleado();
        $cuenta = $this->crearUsuario('empleado', ['empleado_id' => $e->id]);
        $cuenta->createToken('sesion');

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->postJson("/api/users/{$cuenta->id}/reset-password")->assertOk();

        Mail::assertSent(AccesoAlSistema::class);
        $this->assertFalse(Hash::check(self::CLAVE, $cuenta->fresh()->password));
        $this->assertSame(0, $cuenta->tokens()->count());
    }

    public function test_olvide_mi_clave_responde_igual_exista_o_no_el_correo(): void
    {
        Mail::fake();
        $this->crearUsuario('empleado', ['email' => 'existe@prueba.test']);

        foreach (['existe@prueba.test', 'existe@prueba.test', 'noexiste@prueba.test', 'noexiste@prueba.test'] as $correo) {
            $this->postJson('/api/forgot-password', ['email' => $correo])->assertOk();
        }
        Mail::assertQueued(RestablecerPassword::class, 1);
    }

    public function test_el_ingreso_tambien_se_frena_por_cuenta_aunque_cambie_la_ip(): void
    {
        $this->crearUsuario('empleado', ['email' => 'blanco@prueba.test']);

        for ($i = 1; $i <= 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/login', ['email' => 'blanco@prueba.test', 'password' => 'Mala1234'])->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson('/api/login', ['email' => 'blanco@prueba.test', 'password' => self::CLAVE])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson('/api/login', ['email' => 'otra@prueba.test', 'password' => 'Mala1234'])->assertStatus(422);
    }

    public function test_nadie_resuelve_sus_propias_vacaciones(): void
    {
        $e = $this->crearEmpleado();
        $v = Vacacion::create(['empleado_id' => $e->id, 'fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-12-03', 'dias_solicitados' => 3, 'estado' => 'pendiente', 'estado_registro' => 'activo']);

        $this->actingAs($this->crearUsuario('rrhh', ['empleado_id' => $e->id]), 'sanctum')
            ->putJson("/api/vacations/{$v->id}", ['estado' => 'aprobado'])->assertForbidden();

        $this->assertSame('pendiente', $v->fresh()->estado);
    }

    public function test_las_respuestas_llevan_las_cabeceras_de_seguridad(): void
    {
        $this->getJson('/api/me')->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }
}
