<?php

namespace Tests\Feature;

use App\Models\PaymentConcept;
use App\Models\Planilla;
use App\Models\PayrollDetalle;
use App\Models\Sede;
use App\Support\ConceptosDePago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * El Excel de CTS y de Gratificaciones baja lo mismo que se ve en la
 * pantalla: con sus filtros (situación, sede, área, cargo y búsqueda), y
 * dice qué filtros lleva.
 */
class ExcelDeCtsYGratificacionTest extends TestCase
{
    use RefreshDatabase;

    /** Las filas de la primera hoja del Excel bajado. */
    private function leer($respuesta): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xls');
        file_put_contents($ruta, $respuesta->streamedContent());
        $filas = IOFactory::load($ruta)->getActiveSheet()->toArray(null, true, false);
        unlink($ruta);

        return $filas;
    }

    public function test_el_excel_de_cts_respeta_los_filtros_de_la_pantalla(): void
    {
        $central = Sede::firstOrCreate(['nombre' => 'Sede Central de Prueba']);
        $otra = Sede::firstOrCreate(['nombre' => 'Sede Anexa de Prueba']);
        $this->crearEmpleado(['dni' => '41111111', 'apellido' => 'Alfa', 'sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0, 'sede_id' => $central->id]);
        $this->crearEmpleado(['dni' => '42222222', 'apellido' => 'Beta', 'sueldo_base' => 2000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0, 'sede_id' => $otra->id]);
        $rrhh = $this->crearUsuario('rrhh');

        $todo = $this->leer($this->actingAs($rrhh, 'sanctum')->get('/api/cts/export?anio=2026&periodo=mayo')->assertOk());
        $this->assertSame('CTS - depósito de mayo 2026', $todo[0][0]);
        $this->assertStringStartsWith('Todo el personal · 2 de 2', $todo[1][0]);

        $filtrado = $this->leer($this->actingAs($rrhh, 'sanctum')->get("/api/cts/export?anio=2026&periodo=mayo&sede_id={$central->id}")->assertOk());
        $this->assertStringContainsString('Sede: Sede Central de Prueba', $filtrado[1][0]);
        $this->assertStringContainsString('1 de 2', $filtrado[1][0]);
        $this->assertSame('41111111', (string) $filtrado[4][1]);
        $this->assertSame('TOTAL', $filtrado[5][2]);
        // 3000 + 3270/6 = 3545 → la CTS de 6 meses es 1772.50.
        $this->assertEqualsWithDelta(1772.5, (float) $filtrado[5][16], 0.001);

        $buscado = $this->leer($this->actingAs($rrhh, 'sanctum')->get('/api/cts/export?anio=2026&periodo=mayo&search=beta')->assertOk());
        $this->assertSame('42222222', (string) $buscado[4][1]);
        $this->assertStringContainsString('Búsqueda: «beta»', $buscado[1][0]);

        $this->actingAs($rrhh, 'sanctum')->getJson('/api/cts/export?anio=2026&periodo=mayo&situacion=inventada')->assertStatus(422);
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')->get('/api/cts/export?anio=2026&periodo=mayo')->assertForbidden();
    }

    public function test_el_excel_de_gratificaciones_filtra_por_situacion(): void
    {
        foreach ([ConceptosDePago::GRATIFICACION, ConceptosDePago::BONIF_EXTRAORDINARIA] as $nombre) {
            PaymentConcept::firstOrCreate(['nombre' => $nombre], ['tipo' => 'bonificacion']);
        }
        $pagada = $this->crearEmpleado(['dni' => '43333333', 'sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);
        $this->crearEmpleado(['dni' => '44444444', 'sueldo_base' => 2000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);
        $planilla = Planilla::create(['empleado_id' => $pagada->id, 'mes' => 7, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 0]);
        foreach ([ConceptosDePago::GRATIFICACION => 3000, ConceptosDePago::BONIF_EXTRAORDINARIA => 270] as $nombre => $monto) {
            PayrollDetalle::create(['planilla_id' => $planilla->id, 'payment_concept_id' => PaymentConcept::where('nombre', $nombre)->value('id'), 'monto_calculado' => $monto]);
        }
        $rrhh = $this->crearUsuario('rrhh');

        $pagadas = $this->leer($this->actingAs($rrhh, 'sanctum')->get('/api/bonuses/export?anio=2026&periodo=julio&situacion=pagada')->assertOk());

        $this->assertSame('Gratificación de julio 2026', $pagadas[0][0]);
        $this->assertStringContainsString('Situación: Pagada', $pagadas[1][0]);
        $this->assertSame('43333333', (string) $pagadas[4][1]);
        $this->assertEqualsWithDelta(3270, (float) $pagadas[4][16], 0.001);
        $this->assertEqualsWithDelta(3270, (float) $pagadas[4][17], 0.001);
        $this->assertSame('TOTAL', $pagadas[5][2]);

        $porPagar = $this->leer($this->actingAs($rrhh, 'sanctum')->get('/api/bonuses/export?anio=2026&periodo=julio&situacion=por_pagar')->assertOk());
        $this->assertSame('44444444', (string) $porPagar[4][1]);
    }
}
