<?php

namespace Tests\Feature;

use App\Models\PaymentConcept;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use App\Models\RentaQuintaPrevia;
use App\Support\ConceptosDePago;
use App\Support\Renta5ta\MotorRenta5ta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La 5ta con el procedimiento de SUNAT (Art. 40). Casos hechos a mano con
 * UIT 2026 = 5,500 (7 UIT = 38,500) y EsSalud 9%:
 *
 *   Sueldo 5,000 todo el año → renta 5,000×12 + gratificaciones 5,450×2
 *   = 70,900 − 38,500 = 32,400 → 27,500×8% + 4,900×14% = 2,886 al año.
 */
class Renta5taSunatTest extends TestCase
{
    use RefreshDatabase;

    private MotorRenta5ta $motor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->motor = new MotorRenta5ta();
    }

    private function trabajador(float $sueldo = 5000)
    {
        return $this->crearEmpleado(['sueldo_base' => $sueldo, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);
    }

    private function historial($e, array $meses): void
    {
        foreach ($meses as $mes => [$remuneracion, $retencion]) {
            RentaQuintaPrevia::create(['empleado_id' => $e->id, 'anio' => 2026, 'mes' => $mes, 'remuneracion' => $remuneracion, 'retencion' => $retencion]);
        }
    }

    public function test_enero_proyecta_el_anio_y_divide_entre_12(): void
    {
        $fila = $this->motor->retencionDelMes($this->trabajador(), 1, 2026);

        $this->assertEqualsWithDelta(70900, $fila['renta_bruta'], 0.01);
        $this->assertEqualsWithDelta(2886, $fila['impuesto_anual'], 0.01);
        $this->assertSame(12, $fila['divisor']);
        $this->assertEqualsWithDelta(240.50, $fila['retencion'], 0.001);
    }

    public function test_abril_resta_lo_retenido_de_enero_a_marzo_y_divide_entre_9(): void
    {
        $e = $this->trabajador();
        $this->historial($e, [1 => [5000, 240.5], 2 => [5000, 240.5], 3 => [5000, 240.5]]);

        $fila = $this->motor->retencionDelMes($e, 4, 2026);

        $this->assertEqualsWithDelta(15000, $fila['remuneraciones_anteriores'], 0.01);
        $this->assertEqualsWithDelta(721.50, $fila['retenido_antes'], 0.01);
        $this->assertEqualsWithDelta(240.50, $fila['retencion'], 0.001);
    }

    public function test_un_aumento_en_abril_se_reparte_en_los_meses_que_quedan(): void
    {
        // Ganaba 5,000 hasta marzo; desde abril 6,000.
        $e = $this->trabajador(6000);
        $this->historial($e, [1 => [5000, 240.5], 2 => [5000, 240.5], 3 => [5000, 240.5]]);

        $fila = $this->motor->retencionDelMes($e, 4, 2026);

        // 15,000 + 6,000×9 + 6,540×2 = 82,080 − 38,500 = 43,580 → 2,200 + 16,080×14% = 4,451.20
        $this->assertEqualsWithDelta(82080, $fila['renta_bruta'], 0.01);
        $this->assertEqualsWithDelta(4451.20, $fila['impuesto_anual'], 0.01);
        // (4,451.20 − 721.50) ÷ 9 = 414.41
        $this->assertEqualsWithDelta(414.41, $fila['retencion'], 0.001);
    }

    public function test_un_bono_del_mes_se_retiene_entero_ese_mes(): void
    {
        $e = $this->trabajador();
        $this->historial($e, [1 => [5000, 240.5], 2 => [5000, 240.5], 3 => [5000, 240.5], 4 => [5000, 240.5]]);

        $mayo = Planilla::create(['empleado_id' => $e->id, 'mes' => 5, 'anio' => 2026, 'sueldo_base' => 5000, 'total' => 0]);
        $otros = PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::OTROS_INGRESOS], ['tipo' => 'bonificacion']);
        PayrollDetalle::create(['planilla_id' => $mayo->id, 'payment_concept_id' => $otros->id, 'monto_calculado' => 2000]);

        $fila = $this->motor->retencionDelMes($e, 5, 2026);

        // Lo ordinario: (2,886 − 962) ÷ 8 = 240.50. El bono: 2,000 × 14% = 280 más, ese mes.
        $this->assertEqualsWithDelta(240.50, $fila['retencion_ordinaria'], 0.001);
        $this->assertEqualsWithDelta(280.00, $fila['retencion_adicional'], 0.001);
        $this->assertEqualsWithDelta(520.50, $fila['retencion'], 0.001);
    }

    public function test_diciembre_regulariza_con_todo_lo_retenido(): void
    {
        $e = $this->trabajador();
        $meses = [];
        for ($m = 1; $m <= 11; $m++) {
            // Julio cobró además su gratificación con el 9% (5,450).
            $meses[$m] = [$m === 7 ? 10450 : 5000, 240.5];
        }
        $this->historial($e, $meses);

        $fila = $this->motor->retencionDelMes($e, 12, 2026);

        $this->assertEqualsWithDelta(70900, $fila['renta_bruta'], 0.01);
        $this->assertEqualsWithDelta(2645.50, $fila['retenido_antes'], 0.01);
        $this->assertEqualsWithDelta(240.50, $fila['retencion'], 0.001);
    }

    public function test_por_debajo_de_7_uit_no_se_retiene(): void
    {
        $this->assertSame(0.0, $this->motor->retencionDelMes($this->trabajador(2500), 1, 2026)['retencion']);
    }

    public function test_la_hoja_del_anio_marca_los_meses_sin_dato(): void
    {
        $e = $this->trabajador();
        $this->historial($e, [1 => [5000, 240.5], 2 => [5000, 240.5]]);

        $hoja = $this->motor->hoja($e, 2026);

        $this->assertCount(12, $hoja['meses']);
        $this->assertSame([1, 2], $hoja['resumen']['meses_con_dato']);
        // Marzo en adelante no tiene planilla ni historial: se proyectan.
        $this->assertSame('proyectado', $hoja['meses'][2]['fuente']);
        $this->assertEqualsWithDelta(2886, $hoja['resumen']['impuesto_anual'], 0.01);
        $this->assertEqualsWithDelta(481, $hoja['resumen']['retenido'], 0.01);
    }
}
