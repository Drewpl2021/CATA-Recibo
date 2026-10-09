<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Contrato;
use App\Models\PaymentConcept;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use App\Models\Sede;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El Panel de Control: cada bloque respeta la sede elegida, compara con el
 * año anterior y cuenta en la base (edades, antigüedad, altas y bajas).
 */
class PanelDeControlTest extends TestCase
{
    use RefreshDatabase;

    private Sede $central;
    private Sede $jerusalen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 9)->setTime(10, 0));

        $this->central = Sede::create(['nombre' => 'Central']);
        $this->jerusalen = Sede::create(['nombre' => 'Jerusalén']);
        $primaria = Area::create(['nombre' => 'Primaria']);

        // Central: dos personas, una con su planilla de octubre y setiembre.
        $rosa = $this->crearEmpleado([
            'sede_id' => $this->central->id, 'area_id' => $primaria->id,
            'fecha_nacimiento' => '1990-10-20', 'fecha_ingreso' => '2026-10-01', 'sistema_pensiones' => 'AFP',
        ]);
        $this->crearEmpleado([
            'sede_id' => $this->central->id, 'fecha_nacimiento' => '1960-10-02', 'fecha_ingreso' => '2010-03-01',
        ]);
        // Jerusalén: una persona, y una que se fue en marzo.
        $luis = $this->crearEmpleado([
            'sede_id' => $this->jerusalen->id, 'fecha_nacimiento' => '1985-10-09', 'fecha_ingreso' => '2024-03-01',
        ]);
        $this->crearEmpleado([
            'sede_id' => $this->jerusalen->id, 'estado' => 'inactivo', 'fecha_cese' => '2026-03-15',
            'fecha_nacimiento' => '1970-01-01',
        ]);

        $oct = Planilla::create(['empleado_id' => $rosa->id, 'mes' => 10, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 2700]);
        Planilla::create(['empleado_id' => $rosa->id, 'mes' => 9, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 2500]);
        Planilla::create(['empleado_id' => $rosa->id, 'mes' => 10, 'anio' => 2025, 'sueldo_base' => 2800, 'total' => 2400]);
        Planilla::create(['empleado_id' => $luis->id, 'mes' => 10, 'anio' => 2026, 'sueldo_base' => 2000, 'total' => 1800]);

        $essalud = PaymentConcept::create(['nombre' => 'Aporte de prueba', 'tipo' => 'aportacion']);
        PayrollDetalle::create(['planilla_id' => $oct->id, 'payment_concept_id' => $essalud->id, 'monto_calculado' => 270]);

        Contrato::create([
            'empleado_id' => $luis->id, 'tipo_contrato_id' => $this->idTipoContrato('Plazo indeterminado'),
            'fecha_inicio' => '2024-03-01', 'fecha_fin' => '2026-10-30', 'estado' => 'vigente', 'estado_registro' => 'activo',
        ]);
    }

    private function panel(?Sede $sede = null): array
    {
        return $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson('/api/dashboard?mes=10&anio=2026' . ($sede ? '&sede_id=' . $sede->id : ''))
            ->assertOk()->json('data');
    }

    public function test_todo_el_colegio(): void
    {
        $d = $this->panel();

        $this->assertSame(3, $d['resumen']['empleadosActivos']);
        $this->assertEquals(4500, $d['resumen']['nominaDelMes']);
        $this->assertEquals(2500, $d['resumen']['nominaMesAnterior']);
        $this->assertEquals(270, $d['resumen']['aportesColegio']);
        // Las altas del mes que se mira.
        $this->assertSame(1, $d['resumen']['altasDelMes']);

        // El año y el anterior, mes a mes.
        $this->assertCount(12, $d['tendenciaNomina']);
        $this->assertEquals(4500, $d['tendenciaNomina'][9]['valor']);
        $this->assertEquals(2400, $d['tendenciaAnterior'][9]['valor']);

        // Contadas en la base: 1990 → 35, 1960 → 66, 1985 → 41.
        $this->assertSame([0, 1, 1, 0, 1], array_column($d['edades'], 'valor'));
        // Ingresos: este mes (<1), 2024 (1 a 3), 2010 (>10).
        $this->assertSame([1, 1, 0, 0, 1], array_column($d['antiguedad'], 'valor'));

        // Altas y bajas por mes: tres entraron en su año (solo Rosa en 2026), una se fue en marzo.
        $this->assertSame(1, $d['movimientoPersonal'][9]['altas']);
        $this->assertSame(1, $d['movimientoPersonal'][2]['bajas']);

        // Cumpleaños de octubre, por día; los inactivos no.
        $this->assertSame([2, 9, 20], array_column($d['cumpleanos'], 'dia'));
        $this->assertTrue($d['cumpleanos'][1]['es_hoy']);

        $this->assertCount(1, $d['contratosPorVencer']);
    }

    public function test_cada_bloque_respeta_la_sede(): void
    {
        $d = $this->panel($this->central);

        $this->assertSame(2, $d['resumen']['empleadosActivos']);
        $this->assertEquals(2700, $d['resumen']['nominaDelMes']);
        $this->assertEquals(2700, $d['tendenciaNomina'][9]['valor']);
        $this->assertEquals(2700, array_sum(array_column($d['remuneracionPorArea'], 'valor')));
        $this->assertSame(2, array_sum(array_column($d['sistemaPensiones'], 'valor')));
        $this->assertSame(2, array_sum(array_column($d['edades'], 'valor')));
        $this->assertSame([2, 20], array_column($d['cumpleanos'], 'dia'));
        // El contrato que vence es de Jerusalén: aquí no sale.
        $this->assertSame([], $d['contratosPorVencer']);
        $this->assertSame([], $d['tipoContrato']);
        $this->assertSame(0, $d['movimientoPersonal'][2]['bajas']);
    }

    public function test_el_excel_sale_con_las_hojas_nuevas(): void
    {
        $respuesta = $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->get('/api/dashboard/export?mes=10&anio=2026')->assertOk();

        $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($respuesta->baseResponse->getFile()->getPathname());
        $this->assertContains('Altas y bajas', $libro->getSheetNames());
    }

    public function test_un_trabajador_no_ve_el_panel(): void
    {
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')->getJson('/api/dashboard')->assertForbidden();
    }
}
