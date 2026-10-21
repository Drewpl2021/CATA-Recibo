<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Cargo;
use App\Support\DetalleDeAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El «Ver más» de la auditoría: el registro contado en palabras, con su contexto. */
class AuditoriaDetalleTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_alta_guarda_con_que_datos_se_registro(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');
        $empleado = $this->crearEmpleado(['sueldo_base' => 2700, 'tiene_hijos' => 1]);

        $alta = Auditoria::where('entidad', 'empleado')->where('entidad_id', $empleado->id)->where('accion', 'creó')->firstOrFail();

        $this->assertEquals(2700, $alta->cambios['sueldo_base']);
        $this->assertArrayHasKey('dni', $alta->cambios);
    }

    public function test_el_detalle_cuenta_los_cambios_en_palabras(): void
    {
        $admin = $this->crearUsuario('admin');
        $this->actingAs($admin, 'sanctum');
        $docente = Cargo::create(['nombre' => 'Docente de Primaria']);
        $empleado = $this->crearEmpleado(['sueldo_base' => 2700]);
        $empleado->update(['sueldo_base' => 3000, 'cargo_id' => $docente->id]);
        $cambio = Auditoria::where('entidad_id', $empleado->id)->where('accion', 'cambió')->firstOrFail();

        $res = $this->getJson("/api/audit-log/{$cambio->id}", ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/141.0'])->assertOk();

        $campos = collect($res->json('data.campos'))->keyBy('campo');
        $this->assertSame('Sueldo base', $campos['sueldo_base']['etiqueta']);
        $this->assertSame('S/ 2,700.00', $campos['sueldo_base']['antes']);
        $this->assertSame('S/ 3,000.00', $campos['sueldo_base']['despues']);
        // El cargo por su nombre, no por su id.
        $this->assertSame('Docente de Primaria', $campos['cargo_id']['despues']);

        $this->assertSame('Ficha de un trabajador', $res->json('data.sobre.entidad'));
        $this->assertSame("/inicio/empleados/ver/{$empleado->id}", $res->json('data.sobre.enlace'));
        $this->assertSame('Administración', $res->json('data.quien.rol'));
        // Lo demás que le pasó a esa ficha: su alta.
        $this->assertContains('creó', collect($res->json('data.historial'))->pluck('accion'));
    }

    public function test_el_navegador_se_cuenta_en_palabras(): void
    {
        $this->assertSame('Chrome en Windows', DetalleDeAuditoria::navegadorLegible('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/141.0 Safari/537.36'));
        $this->assertSame('Safari en un iPhone', DetalleDeAuditoria::navegadorLegible('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Version/18.0 Mobile Safari/604.1'));
        $this->assertNull(DetalleDeAuditoria::navegadorLegible(null));
    }

    public function test_los_datos_anidados_no_salen_como_object(): void
    {
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
        Auditoria::registrar('importó', 'planilla', null, 'Importó el PLAME de Marzo 2026', ['nuevos' => ['activos' => 1, 'cesados' => 0], 'neto_total' => 1500]);

        $res = $this->getJson('/api/audit-log/' . Auditoria::latest('id')->value('id'))->assertOk();
        $campos = collect($res->json('data.campos'))->keyBy('campo');

        $this->assertSame('activos: 1, cesados: 0', $campos['nuevos']['despues']);
        $this->assertSame('S/ 1,500.00', $campos['neto_total']['despues']);
    }

    public function test_solo_administracion_ve_el_detalle(): void
    {
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
        Auditoria::registrar('cambió', 'configuración', '1', 'Cambió un ajuste', ['valor' => ['0', '1']]);
        $id = Auditoria::latest('id')->value('id');

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->getJson("/api/audit-log/{$id}")->assertForbidden();
    }
}
