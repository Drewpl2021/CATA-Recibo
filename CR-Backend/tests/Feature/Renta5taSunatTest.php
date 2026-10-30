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

    /**
     * El PLAME de mayo trae la CTS como ingreso (y la descuenta como
     * «Adelanto de CTS», porque se deposita aparte). La CTS está inafecta
     * (art. 18 de la Ley del IR): no puede subir la 5ta, ni en el método de
     * SUNAT ni en el de la hoja de RR.HH.
     */
    public function test_la_cts_que_figura_en_la_planilla_no_paga_5ta(): void
    {
        $e = $this->trabajador();
        $this->historial($e, [1 => [5000, 240.5], 2 => [5000, 240.5], 3 => [5000, 240.5], 4 => [5000, 240.5]]);

        $mayo = Planilla::create(['empleado_id' => $e->id, 'mes' => 5, 'anio' => 2026, 'sueldo_base' => 5000, 'total' => 0]);
        $cts = PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::CTS], ['tipo' => 'bonificacion']);
        PayrollDetalle::create(['planilla_id' => $mayo->id, 'payment_concept_id' => $cts->id, 'monto_calculado' => 2500]);

        $fila = $this->motor->retencionDelMes($e, 5, 2026);
        $this->assertEqualsWithDelta(0, $fila['retencion_adicional'], 0.001);
        $this->assertEqualsWithDelta(240.50, $fila['retencion'], 0.001);

        // El de la hoja de RR.HH. tampoco la cuenta en lo que va del año.
        $c = app(\App\Http\Controllers\BoletaController::class);
        $otros = new \ReflectionMethod($c, 'otrosIngresosDelAnio');
        $otros->setAccessible(true);
        $this->assertEqualsWithDelta(0, $otros->invoke($c, $e->id, 2026, 11), 0.001);
    }

    /** Reingresó en marzo: lo que cobró en enero con su contrato anterior también es renta del año. */
    public function test_lo_cobrado_antes_de_un_reingreso_cuenta_para_la_renta_del_anio(): void
    {
        $e = $this->crearEmpleado(['sueldo_base' => 5000, 'fecha_ingreso' => '2026-03-02', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);
        $this->historial($e, [1 => [5000, 240.5]]);

        $fila = $this->motor->retencionDelMes($e, 4, 2026);

        $this->assertEqualsWithDelta(5000 + 5000, $fila['remuneraciones_anteriores'], 0.01, 'Enero (real) y marzo (estimado); febrero no trabajó');
        // Y su retención de enero se resta, igual que antes.
        $this->assertGreaterThanOrEqual(240.5, $fila['retenido_antes']);
    }

    /**
     * Un contratado hasta el 31/12 cobra sus vacaciones truncas en diciembre:
     * es renta del año. Sin proyectarlas, a varios contratados de 2026 la renta
     * les salía justo debajo de las 7 UIT y el método decía «no paga».
     */
    public function test_al_contratado_se_le_proyectan_las_vacaciones_truncas_de_su_cese(): void
    {
        $contratado = $this->crearEmpleado(['sueldo_base' => 2900, 'fecha_ingreso' => '2026-03-02', 'fecha_cese' => '2026-12-31',
            'tiene_hijos' => 0, 'bonificacion_cargo' => 0, 'tipo_contrato_id' => $this->idTipoContrato('Contratado')]);
        $nombrado = $this->crearEmpleado(['sueldo_base' => 2900, 'fecha_ingreso' => '2026-03-02', 'fecha_cese' => null,
            'tiene_hijos' => 0, 'bonificacion_cargo' => 0, 'tipo_contrato_id' => $this->idTipoContrato('Plazo indeterminado')]);

        // 2,900 ÷ 12 × 10 meses (marzo a diciembre)
        $this->assertEqualsWithDelta(2416.67, $this->motor->retencionDelMes($contratado, 11, 2026)['vacaciones_truncas'], 0.01);
        $this->assertEqualsWithDelta(0, $this->motor->retencionDelMes($nombrado, 11, 2026)['vacaciones_truncas'], 0.001);
    }

    /**
     * El contratado que se liquidó en enero (cobró sus truncas de 2025), no
     * trabajó febrero y volvió en marzo: en diciembre le tocan solo las de
     * marzo a diciembre (10 meses), no las del año entero.
     */
    public function test_las_truncas_se_cuentan_desde_las_ultimas_cobradas_y_solo_los_meses_trabajados(): void
    {
        $e = $this->crearEmpleado(['sueldo_base' => 2400, 'fecha_ingreso' => '2025-03-01', 'fecha_cese' => '2026-12-31',
            'tiene_hijos' => 0, 'bonificacion_cargo' => 0, 'tipo_contrato_id' => $this->idTipoContrato('Contratado')]);
        $enero = Planilla::create(['empleado_id' => $e->id, 'mes' => 1, 'anio' => 2026, 'sueldo_base' => 1600, 'total' => 0]);
        $truncas = PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::VACACIONES_TRUNCAS], ['tipo' => 'bonificacion']);
        PayrollDetalle::create(['planilla_id' => $enero->id, 'payment_concept_id' => $truncas->id, 'monto_calculado' => 2000]);
        foreach (range(3, 10) as $mes) {
            Planilla::create(['empleado_id' => $e->id, 'mes' => $mes, 'anio' => 2026, 'sueldo_base' => 2400, 'total' => 0]);
        }
        // Febrero está cargado (otro sí cobró): que él no tenga planilla es que no trabajó.
        Planilla::create(['empleado_id' => $this->trabajador()->id, 'mes' => 2, 'anio' => 2026, 'sueldo_base' => 5000, 'total' => 0]);

        $fila = $this->motor->retencionDelMes($e, 11, 2026);
        $this->assertNotContains(2, $fila['meses_sin_dato']);
        // Enero: sueldo 1,600 + sus truncas 2,000; marzo a octubre: 2,400 × 8. Febrero, nada.
        $this->assertEqualsWithDelta(1600 + 2000 + 2400 * 8, $fila['remuneraciones_anteriores'], 0.01, 'Febrero no se estima');

        // 2,400 ÷ 12 × 10 (marzo a diciembre; febrero no lo trabajó)
        $this->assertEqualsWithDelta(2000, $this->motor->retencionDelMes($e, 11, 2026)['vacaciones_truncas'], 0.01);
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
