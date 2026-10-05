<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\ValorLegal;
use App\Traits\CalculaConceptosPlanilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorConValoresLegales
{
    use CalculaConceptosPlanilla;

    public function __call(string $metodo, array $args)
    {
        return $this->$metodo(...$args);
    }
}

/**
 * Los montos de ley por año: cada planilla se calcula con los de SU año.
 */
class ValoresLegalesTest extends TestCase
{
    use RefreshDatabase;

    private function empleado(array $atributos = []): Empleado
    {
        return new Empleado(array_merge([
            'sistema_pensiones' => 'ONP',
            'fecha_ingreso'     => '2020-03-01',
            'tiene_hijos'       => 0,
        ], $atributos));
    }

    /**
     * Enero, sueldo 2 700: proyecta 2 700 × 12 + dos gratificaciones con su
     * 9% = 38 286. Pasa las 7 UIT de 2025 (37 450) pero no las de 2026
     * (38 500): con la UIT fija de antes, 2025 salía sin retención.
     */
    public function test_la_renta_de_5ta_usa_la_uit_de_su_anio(): void
    {
        $motor = new MotorConValoresLegales();

        $this->assertGreaterThan(0, $motor->calcularRenta5taCategoria($this->empleado(), 2700, 0, 1, 2025));
        $this->assertSame(0.0, $motor->calcularRenta5taCategoria($this->empleado(), 2700, 0, 1, 2026));
    }

    public function test_la_asignacion_familiar_es_la_de_su_anio(): void
    {
        $motor = new MotorConValoresLegales();
        $conHijos = $this->empleado(['tiene_hijos' => 1]);

        $this->assertSame(102.5, $motor->calcularAsignacionFamiliar($conHijos, 2024));
        $this->assertSame(113.0, $motor->calcularAsignacionFamiliar($conHijos, 2025));
    }

    public function test_un_anio_sin_cargar_usa_el_ultimo_anterior_o_el_primero(): void
    {
        $this->assertSame(2026, ValorLegal::delAnio(2030)->anio);
        $this->assertSame(2024, ValorLegal::delAnio(2023)->anio);
    }

    public function test_el_administrador_cambia_un_anio_y_el_calculo_lo_usa(): void
    {
        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->putJson('/api/legal-values/2025', array_merge(ValorLegal::find(2025)->only(ValorLegal::CAMPOS), ['onp' => 12.5]))
            ->assertOk();

        $pension = (new MotorConValoresLegales())->calcularDescuentoPension($this->empleado(), 1000, 2025);
        $this->assertSame(125.0, $pension['total']);
    }

    public function test_rrhh_los_lee_pero_no_los_cambia(): void
    {
        $rrhh = $this->crearUsuario('rrhh');

        $this->actingAs($rrhh, 'sanctum')->getJson('/api/legal-values')->assertOk()->assertJsonCount(3, 'data');
        $this->actingAs($rrhh, 'sanctum')->putJson('/api/legal-values/2025', ['uit' => 1])->assertForbidden();
    }

    /**
     * El caso real del PLAME de septiembre 2026: SONCO RAMOS, 69 años,
     * Integra mixta, 582.80. Fondo 58.28, sin comisión (mixta) y sin prima.
     */
    public function test_con_65_anios_o_mas_no_paga_la_prima_del_seguro(): void
    {
        $motor = new MotorConValoresLegales();
        $sonco = $this->empleado([
            'sistema_pensiones' => 'AFP', 'afp' => 'Integra', 'tipo_comision_afp' => 'mixta',
            'fecha_nacimiento' => '1957-06-09',
        ]);

        $pension = $motor->calcularDescuentoPension($sonco, 582.80, 2026, 9);

        $this->assertSame(58.28, $pension['total']);
        $this->assertNotContains(\App\Support\ConceptosDePago::SPP_PRIMA_SEGURO, array_column($pension['detalle'], 'concepto'));
    }

    public function test_el_mes_en_que_cumple_65_todavia_paga_la_prima(): void
    {
        $motor = new MotorConValoresLegales();
        $cumple = $this->empleado([
            'sistema_pensiones' => 'AFP', 'afp' => 'Integra', 'tipo_comision_afp' => 'mixta',
            'fecha_nacimiento' => '1961-09-15',   // cumple 65 el 15/09/2026
        ]);

        // Septiembre: fondo 100 + prima 13.70. Octubre, ya con 65: solo el fondo.
        $this->assertSame(113.70, $motor->calcularDescuentoPension($cumple, 1000, 2026, 9)['total']);
        $this->assertSame(100.0, $motor->calcularDescuentoPension($cumple, 1000, 2026, 10)['total']);
    }

    /** SONCO RAMOS otra vez: EsSalud 101.70 (9% de la RMV 1 130), no 52.45. */
    public function test_essalud_nunca_se_calcula_sobre_menos_que_la_rmv(): void
    {
        $motor = new MotorConValoresLegales();

        $this->assertSame(101.70, $motor->calcularEssalud(582.80, 2026));
        $this->assertSame(270.00, $motor->calcularEssalud(3000, 2026));
        // Medio mes trabajado: el piso es media RMV (565 × 9% = 50.85).
        $this->assertSame(50.85, $motor->calcularEssalud(300, 2026, 0.5));
        // Sin remuneración no hay aporte.
        $this->assertSame(0.00, $motor->calcularEssalud(0, 2026));
    }

    public function test_el_nombre_oficial_del_anio_se_edita_y_no_pasa_a_otros_anios(): void
    {
        $this->assertSame('Año de la recuperación y consolidación de la economía peruana', ValorLegal::nombreDelAnio(2025));

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->putJson('/api/legal-values/2026', array_merge(ValorLegal::find(2026)->only(ValorLegal::CAMPOS), [
                'nombre_anio' => '  Año de prueba del sistema  ',
            ]))
            ->assertOk();

        $this->assertSame('Año de prueba del sistema', ValorLegal::nombreDelAnio(2026));
        // 2030 usa los MONTOS de 2026, pero no su nombre: cada año tiene el suyo.
        $this->assertSame(2026, ValorLegal::delAnio(2030)->anio);
        $this->assertNull(ValorLegal::nombreDelAnio(2030));
    }

    public function test_sin_nombre_la_boleta_no_lleva_esa_linea(): void
    {
        ValorLegal::find(2026)->update(['nombre_anio' => '   ']);

        $this->assertNull(ValorLegal::nombreDelAnio(2026));
    }

    public function test_un_anio_nuevo_copia_lo_que_no_se_mande_del_anterior(): void
    {
        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->postJson('/api/legal-values', ['anio' => 2027, 'uit' => 5700])
            ->assertCreated()
            ->assertJsonPath('data.uit', 5700)
            ->assertJsonPath('data.asignacion_familiar', 113)
            ->assertJsonPath('data.asignacion_familiar_pct', 10)
            // El nombre no se copia: el de 2026 no es el de 2027.
            ->assertJsonPath('data.nombre_anio', null);
    }

    /** La asignación no se tipea: es el % de la RMV. Si sube el mínimo, sube sola. */
    public function test_la_asignacion_familiar_sale_de_la_rmv_y_su_porcentaje(): void
    {
        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->putJson('/api/legal-values/2026', array_merge(ValorLegal::find(2026)->only(ValorLegal::CAMPOS), ['rmv' => 1200]))
            ->assertOk()
            ->assertJsonPath('data.asignacion_familiar', 120);

        $conHijos = $this->empleado(['tiene_hijos' => 1]);
        $this->assertSame(120.0, (new MotorConValoresLegales())->calcularAsignacionFamiliar($conHijos, 2026));
    }

    /** La bonificación extraordinaria de julio es la tasa de EsSalud del año, no un 9 fijo. */
    public function test_la_bonificacion_extraordinaria_usa_la_tasa_de_essalud_del_anio(): void
    {
        ValorLegal::find(2026)->update(['essalud' => 10]);

        $g = (new MotorConValoresLegales())->calcularGratificacion($this->empleado(), 2000, 7, 2026);

        $this->assertSame(200.0, $g['bonificacion_extraordinaria']);
    }

    /** Un concepto de ley no guarda monto en el catálogo ni se deja renombrar. */
    public function test_un_concepto_de_ley_no_lleva_monto_en_el_catalogo(): void
    {
        $onp = \App\Models\PaymentConcept::firstOrCreate(['nombre' => \App\Support\ConceptosDePago::ONP], ['tipo' => 'descuento']);

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->putJson("/api/payment-concepts/{$onp->id}", [
                'nombre' => 'ONP renombrada', 'calculo' => 'porcentaje', 'valor' => 13, 'aplica_a_todos' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.de_ley', true);

        $onp->refresh();
        $this->assertSame(\App\Support\ConceptosDePago::ONP, $onp->nombre);
        $this->assertNull($onp->valor);
        $this->assertNull($onp->calculo);
        $this->assertFalse($onp->aplica_a_todos);
    }
}
