<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesYAccesosTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_trabajador_no_entra_a_las_pantallas_de_rrhh(): void
    {
        $trabajador = $this->crearUsuario('empleado');

        foreach (['/api/employees', '/api/payrolls', '/api/contracts', '/api/users', '/api/payroll-runs'] as $ruta) {
            $this->actingAs($trabajador, 'sanctum')->getJson($ruta)->assertForbidden();
        }
    }

    public function test_rrhh_si_lista_empleados_y_planillas(): void
    {
        $rrhh = $this->crearUsuario('rrhh');

        $this->actingAs($rrhh, 'sanctum')->getJson('/api/employees')->assertOk();
        $this->actingAs($rrhh, 'sanctum')->getJson('/api/payrolls')->assertOk();
    }

    public function test_el_listado_de_empleados_trae_a_los_dados_de_alta(): void
    {
        $admin = $this->crearUsuario('admin');
        $this->crearEmpleado(['dni' => '11112222', 'nombre' => 'Lucía']);

        $r = $this->actingAs($admin, 'sanctum')->getJson('/api/employees');

        $r->assertOk();
        $this->assertStringContainsString('11112222', $r->getContent());
    }

    public function test_el_trabajador_solo_ve_su_propia_planilla(): void
    {
        $miEmpleado = $this->crearEmpleado(['dni' => '30303030']);
        $otro       = $this->crearEmpleado(['dni' => '40404040']);
        $yo         = $this->crearUsuario('empleado', ['empleado_id' => $miEmpleado->id]);

        \App\Models\Planilla::create(['empleado_id' => $miEmpleado->id, 'mes' => 1, 'anio' => 2026, 'sueldo_base' => 2000, 'total' => 2000]);
        \App\Models\Planilla::create(['empleado_id' => $otro->id, 'mes' => 1, 'anio' => 2026, 'sueldo_base' => 9999, 'total' => 9999]);

        $r = $this->actingAs($yo, 'sanctum')->getJson('/api/my-payroll');

        $r->assertOk();
        $this->assertStringNotContainsString('9999', $r->getContent());
    }

    public function test_la_auditoria_solo_se_lee_no_hay_rutas_para_escribirla(): void
    {
        $admin = $this->crearUsuario('admin');

        $this->actingAs($admin, 'sanctum')->getJson('/api/audit-log')->assertOk();
        // Da 404 o 405 según cómo se resuelva la ruta; lo que importa es que no exista.
        $this->assertContains($this->actingAs($admin, 'sanctum')->postJson('/api/audit-log', [])->status(), [404, 405]);
        $this->assertContains($this->actingAs($admin, 'sanctum')->deleteJson('/api/audit-log/1')->status(), [404, 405]);
    }

    public function test_la_auditoria_es_solo_para_admin(): void
    {
        $rrhh = $this->crearUsuario('rrhh');

        $this->actingAs($rrhh, 'sanctum')->getJson('/api/audit-log')->assertForbidden();
    }

    public function test_dar_de_alta_desde_la_api_deja_rastro_de_quien_lo_hizo(): void
    {
        $admin = $this->crearUsuario('admin');
        $this->actingAs($admin);

        $this->crearEmpleado(['nombre' => 'Rosa', 'apellido' => 'Quispe', 'dni' => '55556666']);

        $fila = Auditoria::where('accion', 'creó')->where('entidad', 'empleado')->firstOrFail();
        $this->assertSame($admin->id, $fila->user_id);
        $this->assertStringContainsString('Rosa Quispe', $fila->descripcion);
        $this->assertStringContainsString('55556666', $fila->descripcion);
    }
}
