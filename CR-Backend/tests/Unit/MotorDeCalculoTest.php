<?php

namespace Tests\Unit;

use App\Models\Empleado;
use App\Models\PayrollDetalle;
use App\Models\PaymentConcept;
use App\Models\Planilla;
use App\Support\ConceptosDePago;
use App\Traits\CalculaConceptosPlanilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Expone los métodos protegidos del motor para poder probarlos uno a uno. */
class MotorExpuesto
{
    use CalculaConceptosPlanilla;

    public function __call(string $metodo, array $args)
    {
        return $this->$metodo(...$args);
    }
}

class MotorDeCalculoTest extends TestCase
{
    use RefreshDatabase;

    private MotorExpuesto $motor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->motor = new MotorExpuesto();
    }

    private function empleado(array $atributos = []): Empleado
    {
        return new Empleado(array_merge([
            'sistema_pensiones' => 'ONP',
            'fecha_ingreso'     => '2020-03-01',
            'tiene_hijos'       => 0,
        ], $atributos));
    }

    // ── Pensión ───────────────────────────────────────────────────

    public function test_onp_descuenta_el_13_por_ciento(): void
    {
        $r = $this->motor->calcularDescuentoPension($this->empleado(), 3000);

        $this->assertSame('ONP', $r['tipo']);
        $this->assertSame(390.00, $r['total']);
    }

    public function test_afp_separa_fondo_prima_y_comision_de_profuturo(): void
    {
        $e = $this->empleado(['sistema_pensiones' => 'AFP', 'afp' => 'Profuturo']);

        $r = $this->motor->calcularDescuentoPension($e, 3000);

        $this->assertSame('AFP - Profuturo', $r['tipo']);
        $this->assertSame(
            ['SPP. Fondo Pensiones' => 300.00, 'SPP. Prima de Seguro' => 41.10, 'SPP. Comisión' => 50.70],
            array_column($r['detalle'], 'monto', 'concepto'),
            'la prima es la fija (1.37%) y la comisión la de la AFP (1.69%): no se cruzan'
        );
        $this->assertSame(391.80, $r['total']);
    }

    #[DataProvider('comisionesPorAfp')]
    public function test_cada_afp_cobra_su_comision(string $afp, float $comisionEsperada): void
    {
        $e = $this->empleado(['sistema_pensiones' => 'AFP', 'afp' => $afp]);

        $r = $this->motor->calcularDescuentoPension($e, 2000);

        $comision = array_column($r['detalle'], 'monto', 'concepto')['SPP. Comisión'];
        $this->assertSame($comisionEsperada, $comision);
    }

    public static function comisionesPorAfp(): array
    {
        return [
            'Habitat'   => ['Habitat', 29.40],
            'Integra'   => ['Integra', 31.00],
            'Prima'     => ['Prima', 32.00],
            'Profuturo' => ['Profuturo', 33.80],
        ];
    }

    public function test_sin_sistema_de_pensiones_no_se_descuenta_nada(): void
    {
        $r = $this->motor->calcularDescuentoPension($this->empleado(['sistema_pensiones' => null]), 3000);

        $this->assertSame('No aporta', $r['tipo']);
        $this->assertSame(0.0, $r['total']);
        $this->assertSame([], $r['detalle']);
    }

    // ── Asignación familiar y EsSalud ─────────────────────────────

    public function test_asignacion_familiar_solo_para_quien_tiene_hijos(): void
    {
        $this->assertSame(113.00, $this->motor->calcularAsignacionFamiliar($this->empleado(['tiene_hijos' => 1])));
        $this->assertSame(0.00, $this->motor->calcularAsignacionFamiliar($this->empleado(['tiene_hijos' => 0])));
    }

    public function test_essalud_es_el_9_por_ciento(): void
    {
        $this->assertSame(270.00, $this->motor->calcularEssalud(3000));
        $this->assertSame(0.00, $this->motor->calcularEssalud(0));
    }

    // ── Gratificación ─────────────────────────────────────────────

    public function test_gratificacion_de_semestre_completo_con_hijos(): void
    {
        $g = $this->motor->calcularGratificacion($this->empleado(['tiene_hijos' => 1]), 3000, 7, 2026);

        $this->assertTrue($g['aplica']);
        $this->assertSame(6, $g['meses_trabajados']);
        $this->assertSame(3000.00, $g['monto_base']);
        $this->assertSame(113.00, $g['asignacion_familiar']);
        $this->assertSame(280.17, $g['bonificacion_extraordinaria']);
        $this->assertSame(3393.17, $g['total']);
    }

    public function test_gratificacion_se_prorratea_por_meses_trabajados(): void
    {
        // Ingresó el 1 de mayo: mayo y junio = 2 de 6 meses.
        $e = $this->empleado(['tiene_hijos' => 1, 'fecha_ingreso' => '2026-05-01']);

        $g = $this->motor->calcularGratificacion($e, 3000, 7, 2026);

        $this->assertSame(2, $g['meses_trabajados']);
        $this->assertSame(1000.00, $g['monto_base']);
        $this->assertSame(37.67, $g['asignacion_familiar']);
        $this->assertSame(1131.06, $g['total']);
    }

    public function test_gratificacion_de_diciembre_usa_el_segundo_semestre(): void
    {
        $g = $this->motor->calcularGratificacion($this->empleado(), 3000, 12, 2026);

        $this->assertTrue($g['aplica']);
        $this->assertSame(6, $g['meses_trabajados']);
        $this->assertSame(3270.00, $g['total']);
    }

    public function test_no_hay_gratificacion_fuera_de_julio_y_diciembre(): void
    {
        foreach ([1, 3, 6, 8, 11] as $mes) {
            $g = $this->motor->calcularGratificacion($this->empleado(), 3000, $mes, 2026);
            $this->assertFalse($g['aplica'], "el mes $mes no paga gratificación");
            $this->assertSame(0.00, $g['total']);
        }
    }

    public function test_quien_ingreso_despues_del_semestre_no_cobra_esa_gratificacion(): void
    {
        $e = $this->empleado(['fecha_ingreso' => '2026-08-01']);

        $g = $this->motor->calcularGratificacion($e, 3000, 7, 2026);

        $this->assertFalse($g['aplica']);
        $this->assertSame(0.00, $g['total']);
    }

    // ── Renta de 5ta categoría ────────────────────────────────────

    public function test_sueldo_bajo_no_paga_renta_de_5ta(): void
    {
        // 1000 × 12 + gratificaciones < 7 UIT (38,500).
        $renta = $this->motor->calcularRenta5taCategoria($this->empleado(), 1000, 0, 1, 2026);

        $this->assertSame(0.00, $renta);
    }

    public function test_renta_de_enero_proyecta_el_ano_y_aplica_los_tramos(): void
    {
        // Ingreso anual: 5000×12 + 2 gratificaciones de 5450 = 70,900.
        // Menos 7 UIT (38,500) = 32,400 → 5 UIT al 8% + el resto al 14% = 2,886.
        // Enero divide entre 12.
        $renta = $this->motor->calcularRenta5taCategoria($this->empleado(), 5000, 0, 1, 2026);

        $this->assertSame(240.50, $renta);
    }

    public function test_la_asignacion_familiar_tambien_paga_renta(): void
    {
        $sin = $this->motor->calcularRenta5taCategoria($this->empleado(), 5000, 0, 1, 2026);
        $con = $this->motor->calcularRenta5taCategoria($this->empleado(['tiene_hijos' => 1]), 5000, 0, 1, 2026);

        $this->assertGreaterThan($sin, $con);
    }

    public function test_a_mayor_sueldo_mayor_retencion(): void
    {
        $anterior = -1.0;
        foreach ([3000, 5000, 8000, 15000, 30000] as $sueldo) {
            $renta = $this->motor->calcularRenta5taCategoria($this->empleado(), $sueldo, 0, 1, 2026);
            $this->assertGreaterThan($anterior, $renta, "sueldo $sueldo");
            $anterior = $renta;
        }
    }

    public function test_diciembre_regulariza_con_el_impuesto_anual_completo(): void
    {
        // Sin retenciones previas, diciembre cobra todo el impuesto anual.
        $renta = $this->motor->calcularRenta5taCategoria($this->empleado(), 5000, 0, 12, 2026);

        $this->assertSame(2886.00, $renta);
    }

    public function test_abril_descuenta_lo_ya_retenido_de_enero_a_marzo(): void
    {
        $e = $this->crearEmpleado(['sueldo_base' => 5000]);
        $this->registrarRetencion($e, mes: 1, monto: 240.50);

        // Abril proyecta 9 meses: 45,000 + 10,900 = 55,900 − 38,500 = 17,400
        // → impuesto 1,392. Menos los 240.50 ya retenidos, entre 9.
        $conHistorial = $this->motor->calcularRenta5taCategoria($e, 5000, 0, 4, 2026);
        $sinHistorial = $this->motor->calcularRenta5taCategoria($this->empleado(), 5000, 0, 4, 2026);

        $this->assertSame(127.94, $conHistorial);
        $this->assertSame(154.67, $sinHistorial);
    }

    // ── Reparto de días (solo lunes a viernes, decisión del colegio) ─

    public function test_el_reparto_solo_cuenta_dias_habiles(): void
    {
        // Jueves 24 de septiembre 2026: el mes tiene 22 hábiles, y del
        // 24 al 30 (jue, vie, lun, mar, mié — sin el sáb 26 ni el dom 27) son 5.
        // Empleado persistido (no el liviano en memoria): repartoDeDiasDelMes
        // también consulta vacaciones por su id, que un empleado sin guardar no tiene.
        $e = $this->crearEmpleado(['fecha_ingreso' => '2026-09-24']);

        $reparto = $this->motor->repartoDeDiasDelMes($e, 9, 2026);

        $this->assertSame(30, $reparto['dias_del_mes']);
        $this->assertSame(22, $reparto['dias_habiles_del_mes']);
        $this->assertSame(5, $reparto['dias_pagados']);
        $this->assertEqualsWithDelta(0.2273, $reparto['proporcion'], 0.0001);
    }

    public function test_quien_trabajo_el_mes_entero_da_proporcion_1(): void
    {
        // La fórmula tiene que dar 1.0 SIEMPRE que se trabajó el mes
        // completo, sin importar cuántos hábiles tenga ese mes en concreto.
        $e = $this->crearEmpleado(['fecha_ingreso' => '2020-01-01']);

        $septiembre = $this->motor->repartoDeDiasDelMes($e, 9, 2026);
        $octubre    = $this->motor->repartoDeDiasDelMes($e, 10, 2026);

        $this->assertSame(1.0, $septiembre['proporcion']);
        $this->assertSame(1.0, $octubre['proporcion']);
        // Y el sueldo, entonces, sale completo: no un 73% por dividir
        // hábiles entre días de calendario.
        $this->assertSame(800.00, round(800 * $septiembre['proporcion'], 2));
    }

    public function test_quien_entra_en_fin_de_semana_empieza_a_contar_el_lunes(): void
    {
        // Sábado 26 de septiembre 2026: no cuenta el sábado ni el domingo,
        // el primer día pagado es el lunes 28. Quedan 3 hábiles (28, 29, 30).
        $e = $this->crearEmpleado(['fecha_ingreso' => '2026-09-26']);

        $reparto = $this->motor->repartoDeDiasDelMes($e, 9, 2026);

        $this->assertSame(3, $reparto['dias_pagados']);
    }

    public function test_el_denominador_cambia_segun_los_habiles_de_cada_mes(): void
    {
        // Setiembre (30 días) y octubre (31 días) del 2026 tienen los dos 22
        // hábiles — no es el mismo número solo por casualidad de que ambos
        // tengan "muchos" días: el punto es que NO se fija en 30, se cuenta
        // mes a mes.
        $e = $this->crearEmpleado(['fecha_ingreso' => '2020-01-01']);

        $this->assertSame(22, $this->motor->repartoDeDiasDelMes($e, 9, 2026)['dias_habiles_del_mes']);
        $this->assertSame(22, $this->motor->repartoDeDiasDelMes($e, 10, 2026)['dias_habiles_del_mes']);
        $this->assertSame(31, $this->motor->repartoDeDiasDelMes($e, 10, 2026)['dias_del_mes']);
    }

    private function registrarRetencion(Empleado $empleado, int $mes, float $monto): void
    {
        $concepto = PaymentConcept::create([
            'nombre' => ConceptosDePago::RENTA_5TA,
            'tipo'   => 'descuento',
        ]);
        $planilla = Planilla::create([
            'empleado_id' => $empleado->id, 'mes' => $mes, 'anio' => 2026,
            'sueldo_base' => 5000, 'total' => 5000,
        ]);
        PayrollDetalle::create([
            'planilla_id'        => $planilla->id,
            'payment_concept_id' => $concepto->id,
            'monto_calculado'    => $monto,
        ]);
    }
}
