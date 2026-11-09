<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Modulo;
use App\Models\Planilla;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Cada respuesta lleva lo que su pantalla necesita, y los errores tienen una sola forma. */
class RespuestasDelApiTest extends TestCase
{
    use RefreshDatabase;

    private const DATOS_RESERVADOS = ['sueldo_base', 'bonificacion_cargo', 'numero_cuenta', 'cci', 'cts_entidad_financiera', 'cts_numero_cuenta', 'cuspp', 'entidad_financiera', 'direccion', 'fecha_nacimiento'];

    private function empleadoCompleto()
    {
        return $this->crearEmpleado([
            'sueldo_base' => 4321, 'numero_cuenta' => 'CUENTA-RESERVADA', 'cci' => '12345678901234567890',
            'cuspp' => 'CUSPPRESERVA', 'direccion' => 'Direccion reservada', 'entidad_financiera' => 'Banco reservado',
        ]);
    }

    private function sinDatosReservados(?array $empleado): void
    {
        $this->assertNotNull($empleado);
        foreach (self::DATOS_RESERVADOS as $campo) {
            $this->assertArrayNotHasKey($campo, $empleado);
        }
    }

    private function rolConModulos(string $nombre, array $rutas): void
    {
        $this->sembrarRolesYModulos();
        Rol::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->modulos()->sync(Modulo::whereIn('ruta', $rutas)->pluck('id'));
        \App\Support\Permisos::refrescar();
    }

    public function test_quien_solo_lleva_contratos_no_recibe_sueldos_ni_cuentas(): void
    {
        $e = $this->empleadoCompleto();
        $c = Contrato::create(['empleado_id' => $e->id, 'tipo_contrato_id' => $e->tipo_contrato_id, 'fecha_inicio' => '2026-03-01', 'estado' => 'vigente']);
        $d = Documento::create(['empleado_id' => $e->id, 'tipo' => 'contrato', 'archivo' => 'documentos/secreto.pdf', 'estado_firma' => 'pendiente', 'estado_registro' => 'activo']);
        $this->rolConModulos('contratos', ['/contratos']);
        $this->actingAs($this->crearUsuario('contratos'), 'sanctum');

        $fila = $this->getJson('/api/contracts')->assertOk()->json('data.0');
        $this->sinDatosReservados($fila['empleado']);
        $this->assertSame($e->dni, $fila['empleado']['dni']);

        $this->sinDatosReservados($this->getJson("/api/contracts/{$c->id}")->assertOk()->json('data.empleado'));
        $this->sinDatosReservados($this->getJson('/api/documents')->assertOk()->json('data.0.empleado'));
        $this->sinDatosReservados($this->getJson("/api/documents/{$d->id}")->assertOk()->json('data.empleado'));
    }

    public function test_un_documento_no_enseña_su_ruta_ni_el_rastro_de_la_firma(): void
    {
        $e = $this->crearEmpleado();
        Documento::create(['empleado_id' => $e->id, 'tipo' => 'contrato', 'archivo' => 'documentos/x/contrato.pdf', 'estado_firma' => 'firmado', 'estado_registro' => 'activo',
            'conformidad_ip' => '10.0.0.1', 'conformidad_sha256' => 'abc']);

        $fila = $this->actingAs($this->crearUsuario('empleado', ['empleado_id' => $e->id]), 'sanctum')
            ->getJson('/api/my-documents')->assertOk()->json('data.0');

        foreach (['archivo', 'archivo_sin_firma', 'huella', 'conformidad_ip', 'conformidad_dispositivo', 'conformidad_sha256'] as $campo) {
            $this->assertArrayNotHasKey($campo, $fila);
        }
        $this->assertSame('pdf', $fila['extension']);
    }

    public function test_el_ingreso_no_trae_la_ficha_ni_datos_repetidos(): void
    {
        $e = $this->empleadoCompleto();
        $cuenta = $this->crearUsuario('empleado', ['empleado_id' => $e->id]);

        $datos = $this->postJson('/api/login', ['email' => $cuenta->email, 'password' => 'ClaveSegura#2026'])->assertOk()->json('data');

        $this->assertArrayNotHasKey('empleado', $datos['user']);
        $this->assertArrayNotHasKey('es_institucional', $datos);
        $this->assertArrayNotHasKey('terminos_ip', $datos['user']);
        $this->assertSame((string) $e->id, $datos['user']['empleado_id']);
        $this->assertSame(['id', 'nombre', 'es_superadmin'], array_keys($datos['user']['rol']));

        $this->app['auth']->forgetGuards();
        $yo = $this->withToken($datos['token'])->getJson('/api/me')->assertOk()->json('data');
        $this->assertArrayNotHasKey('empleado', $yo);
    }

    public function test_usuarios_planillas_y_expediente_no_llevan_sueldo_ficha_ni_banco_de_mas(): void
    {
        $e = $this->empleadoCompleto();
        $cuenta = $this->crearUsuario('empleado', ['empleado_id' => $e->id]);
        $p = Planilla::create(['empleado_id' => $e->id, 'mes' => 10, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 2610]);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');

        $usuario = $this->getJson("/api/users/{$cuenta->id}")->assertOk()->json('data');
        $this->sinDatosReservados($usuario['empleado']);
        $this->assertArrayNotHasKey('terminos_ip', $usuario);

        $planilla = $this->getJson("/api/payrolls/{$p->id}")->assertOk()->json('data');
        $this->sinDatosReservados($planilla['empleado']);
        foreach (['bonificaciones', 'descuentos', 'total_exacto'] as $campo) {
            $this->assertArrayNotHasKey($campo, $planilla);
        }

        $this->sinDatosReservados($this->getJson('/api/payrolls?page=0&size=5')->assertOk()->json('data.content.0.empleado'));
        $this->sinDatosReservados($this->getJson('/api/employee-files?page=0&size=5')->assertOk()->json('data.content.0'));
        $this->sinDatosReservados($this->getJson("/api/employee-files/{$e->id}")->assertOk()->json('data.empleado'));
    }

    public function test_quien_lleva_empleados_sigue_viendo_la_ficha_completa(): void
    {
        $e = $this->empleadoCompleto();

        $ficha = $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->getJson("/api/employees/{$e->id}")->assertOk()->json('data');

        $this->assertSame('CUENTA-RESERVADA', $ficha['numero_cuenta']);
    }

    public function test_el_contrato_y_el_cv_conservan_la_extension_para_el_nombre_al_bajarlos(): void
    {
        Storage::fake('local');
        $e = $this->crearEmpleado();
        Storage::disk('local')->put("documentos/{$e->id}/cv.docx", 'x');

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->postJson('/api/documents', ['empleado_id' => $e->id, 'tipo' => 'hoja_de_vida', 'archivo' => "documentos/{$e->id}/cv.docx"])
            ->assertCreated()->assertJsonPath('data.extension', 'docx')->assertJsonMissingPath('data.archivo');
    }

    public function test_todos_los_errores_tienen_la_misma_forma(): void
    {
        // 401 sin sesión.
        $this->getJson('/api/employees')->assertUnauthorized()->assertJsonPath('success', false)->assertJsonStructure(['success', 'message']);

        // 403 sin el módulo.
        $trabajador = $this->crearUsuario('empleado');
        $this->actingAs($trabajador, 'sanctum')->getJson('/api/users')->assertForbidden()
            ->assertJsonPath('success', false)->assertJsonStructure(['success', 'message'])->assertJsonMissingPath('data');

        // 404 de algo que no existe.
        $this->actingAs($this->crearUsuario('admin'), 'sanctum')->getJson('/api/users/999999')->assertNotFound()->assertJsonPath('success', false);

        // 422 de un formulario: el mensaje dice qué corregir, sin «and 1 more error».
        $r = $this->postJson('/api/employees', [])->assertStatus(422)->assertJsonPath('success', false);
        $this->assertArrayHasKey('errors', $r->json());
        $this->assertStringNotContainsString('more error', $r->json('message'));
        $this->assertStringNotContainsString('más error', $r->json('message'));
    }

    public function test_la_cuenta_trabada_responde_con_su_mensaje_arriba(): void
    {
        $cuenta = $this->crearUsuario('empleado', ['debe_cambiar_password' => true]);

        $this->actingAs($cuenta, 'sanctum')->getJson('/api/my-documents')->assertStatus(423)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'cambiar tu contraseña'))
            ->assertJsonPath('data.debe_cambiar_password', true);
    }

    public function test_un_correo_sin_dominio_completo_no_se_acepta(): void
    {
        $otro = $this->crearUsuario('empleado');

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->putJson("/api/users/{$otro->id}", ['email' => 'persona@empresa'])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_los_datos_de_demostracion_no_se_siembran_en_produccion(): void
    {
        $e = $this->crearEmpleado();
        $this->app['env'] = 'production';

        (new \Database\Seeders\UsuarioDemoSeeder())->run();

        $this->assertNotNull($e->fresh(), 'El sembrador de demostración borró a una persona real.');
    }
}
