<?php

namespace Tests\Feature;

use App\Models\CtsLiquidacion;
use App\Models\Planilla;
use App\Models\RentaQuintaPrevia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los filtros de año de CTS, Gratificaciones y 5ta salen de los datos: un año
 * con planillas no desaparece con el paso del tiempo y uno vacío no se ofrece.
 */
class AniosDeLosFiltrosTest extends TestCase
{
    use RefreshDatabase;

    private function anios(string $ruta): array
    {
        return $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson($ruta)->assertOk()->json('data.anios');
    }

    private function planilla(int $anio, int $mes): void
    {
        Planilla::create([
            'empleado_id' => $this->crearEmpleado(['fecha_ingreso' => '2020-01-01'])->id,
            'mes' => $mes, 'anio' => $anio, 'sueldo_base' => 3000, 'total' => 2610,
        ]);
    }

    public function test_un_anio_con_planillas_sigue_en_los_filtros_tres_anios_despues(): void
    {
        $this->travelTo('2029-03-10');
        $this->planilla(2026, 10);

        $this->assertSame([2029, 2026], $this->anios('/api/cts'));
        $this->assertSame([2029, 2026], $this->anios('/api/bonuses'));
        $this->assertSame([2029, 2026], $this->anios('/api/income-tax'));
    }

    public function test_sin_datos_solo_se_ofrece_el_anio_en_curso(): void
    {
        $this->travelTo('2026-03-10');

        $this->assertSame([2026], $this->anios('/api/cts'));
        $this->assertSame([2026], $this->anios('/api/bonuses'));
        $this->assertSame([2026], $this->anios('/api/income-tax'));
    }

    public function test_cts_ofrece_el_deposito_de_mayo_siguiente_desde_noviembre(): void
    {
        $this->travelTo('2026-11-04');

        $this->assertSame([2027, 2026], $this->anios('/api/cts'));
        $this->assertSame([2026], $this->anios('/api/bonuses'));
    }

    public function test_cts_ofrece_el_anio_siguiente_si_hay_planilla_de_noviembre_o_diciembre(): void
    {
        $this->travelTo('2029-03-10');
        $this->planilla(2026, 12);

        $this->assertSame([2029, 2027, 2026], $this->anios('/api/cts'));
        $this->assertSame([2029, 2026], $this->anios('/api/bonuses'));
    }

    public function test_cuentan_la_cts_guardada_y_el_historial_de_5ta(): void
    {
        $this->travelTo('2029-03-10');
        $e = $this->crearEmpleado(['fecha_ingreso' => '2020-01-01']);
        CtsLiquidacion::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'empleado_id' => $e->id, 'anio' => 2025, 'periodo' => 'mayo',
            'desde' => '2024-11-01', 'hasta' => '2025-04-30',
        ]);
        RentaQuintaPrevia::create(['empleado_id' => $e->id, 'anio' => 2024, 'mes' => 1, 'remuneracion' => 3000, 'retencion' => 0]);

        $this->assertSame([2029, 2025], $this->anios('/api/cts'));
        $this->assertSame([2029, 2024], $this->anios('/api/income-tax'));
    }

    public function test_el_anio_elegido_siempre_esta_en_la_lista(): void
    {
        $this->travelTo('2026-03-10');

        $this->assertSame([2026, 2024], $this->anios('/api/bonuses?anio=2024'));
    }
}
