<?php

namespace Tests\Unit;

use App\Models\Auditoria;
use App\Models\Contrato;
use App\Models\PayrollDetalle;
use App\Models\PaymentConcept;
use App\Models\Planilla;
use App\Support\ConceptosDePago;
use App\Support\Meses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelosDelDominioTest extends TestCase
{
    use RefreshDatabase;

    // ── Planilla::recalcularTotal ─────────────────────────────────

    private function planillaConLineas(array $lineas, float $sueldo = 3000): Planilla
    {
        $empleado = $this->crearEmpleado(['sueldo_base' => $sueldo]);
        $planilla = Planilla::create([
            'empleado_id' => $empleado->id, 'mes' => 3, 'anio' => 2026,
            'sueldo_base' => $sueldo, 'total' => 0,
        ]);

        foreach ($lineas as $i => [$tipo, $monto]) {
            $concepto = PaymentConcept::create(['nombre' => "Concepto $i", 'tipo' => $tipo]);
            PayrollDetalle::create([
                'planilla_id' => $planilla->id,
                'payment_concept_id' => $concepto->id,
                'monto_calculado' => $monto,
            ]);
        }

        return $planilla;
    }

    public function test_el_neto_es_sueldo_mas_bonificaciones_menos_descuentos_y_adelantos(): void
    {
        $planilla = $this->planillaConLineas([
            ['bonificacion', 200], ['bonificacion', 50],
            ['descuento', 390], ['adelanto', 100],
        ]);

        $this->assertSame(2760.00, $planilla->recalcularTotal());
        $this->assertEquals(2760.00, $planilla->fresh()->total);
    }

    public function test_sin_conceptos_el_neto_es_el_sueldo(): void
    {
        $this->assertSame(3000.00, $this->planillaConLineas([])->recalcularTotal());
    }

    public function test_las_aportaciones_del_colegio_no_se_restan_al_trabajador(): void
    {
        // ESSALUD lo paga el colegio: es informativo y no baja el neto.
        $planilla = $this->planillaConLineas([['aportacion', 270]]);

        $this->assertSame(3000.00, $planilla->recalcularTotal());
    }

    public function test_el_id_de_una_planilla_es_un_uuid_generado_al_crear(): void
    {
        $planilla = $this->planillaConLineas([]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-/', $planilla->id);
    }

    // ── Vacaciones: solo indeterminado ────────────────────────────

    public function test_solo_el_contrato_indeterminado_puede_tomar_vacaciones(): void
    {
        foreach (['indeterminado' => true, 'plazo_fijo' => false, 'suplencia' => false, 'practicas' => false] as $tipo => $puede) {
            $empleado = $this->crearEmpleado(['tipo_contrato' => $tipo]);
            $this->assertSame($puede, $empleado->puedeTomarVacaciones(), $tipo);
        }
    }

    public function test_el_contrato_vigente_manda_sobre_la_copia_de_la_ficha(): void
    {
        $empleado = $this->crearEmpleado(['tipo_contrato' => 'suplencia']);

        Contrato::unguard();
        Contrato::create([
            'empleado_id' => $empleado->id, 'tipo_contrato' => 'indeterminado',
            'estado' => 'vigente', 'fecha_inicio' => '2026-01-01',
        ]);
        Contrato::reguard();

        $this->assertSame('indeterminado', $empleado->fresh()->tipoContratoVigente());
        $this->assertTrue($empleado->fresh()->puedeTomarVacaciones());
    }

    /**
     * Bug real, encontrado el 2026-09-27: el botón "Eliminar" de Contratos
     * solo apaga estado_registro, nunca toca 'estado'. Sin filtrar por
     * estado_registro, un contrato ya eliminado seguía mandando: el
     * trabajador conservaba el derecho a vacaciones de un contrato que
     * RR.HH. ya había quitado.
     */
    public function test_eliminar_el_contrato_vigente_le_quita_su_efecto(): void
    {
        $empleado = $this->crearEmpleado(['tipo_contrato' => 'indeterminado']);

        Contrato::unguard();
        $contrato = Contrato::create([
            'empleado_id' => $empleado->id, 'tipo_contrato' => 'indeterminado',
            'estado' => 'vigente', 'fecha_inicio' => '2026-01-01',
        ]);
        Contrato::reguard();

        $this->assertTrue($empleado->fresh()->puedeTomarVacaciones());

        // Como hace ContratoController::destroy(): solo estado_registro.
        $contrato->update(['estado_registro' => 'inactivo']);

        $this->assertNull($empleado->fresh()->contratoVigente);
        $this->assertFalse($empleado->fresh()->puedeTomarVacaciones(), 'un contrato eliminado no debe seguir dando derecho a vacaciones');
    }

    // ── Auditoría ─────────────────────────────────────────────────

    public function test_sin_usuario_no_se_anota_nada_en_la_auditoria(): void
    {
        $this->crearEmpleado();

        $this->assertSame(0, Auditoria::count(), 'seeders y consola no llenan el registro');
    }

    public function test_un_cambio_de_sueldo_queda_con_su_antes_y_su_despues(): void
    {
        $admin = $this->crearUsuario('admin');
        $empleado = $this->crearEmpleado(['sueldo_base' => 2700]);
        $this->actingAs($admin);

        $empleado->update(['sueldo_base' => 3000]);

        $fila = Auditoria::where('accion', 'cambió')->firstOrFail();
        $this->assertSame('empleado', $fila->entidad);
        $this->assertSame($admin->id, $fila->user_id);
        // Según el motor de base el decimal vuelve como "2700" o "2700.00".
        $this->assertEquals([2700.0, 3000.0], array_map('floatval', $fila->cambios['sueldo_base']));
        $this->assertStringContainsString('sueldo base', $fila->descripcion);
    }

    public function test_un_cambio_en_un_campo_no_vigilado_no_deja_rastro(): void
    {
        $admin = $this->crearUsuario('admin');
        $empleado = $this->crearEmpleado();
        $this->actingAs($admin);

        $empleado->update(['telefono' => '999888777']);

        $this->assertSame(0, Auditoria::where('accion', 'cambió')->count());
    }

    public function test_la_contrasena_se_anota_como_oculta_nunca_su_valor(): void
    {
        $admin = $this->crearUsuario('admin');
        $this->actingAs($admin);

        $admin->update(['password' => 'OtraClave#2027']);

        $fila = Auditoria::where('entidad', 'usuario')->where('accion', 'cambió')->firstOrFail();
        $this->assertSame(['(oculto)', '(oculto)'], $fila->cambios['password']);
        $this->assertStringNotContainsString('OtraClave', json_encode($fila->cambios));
    }

    // ── Piezas de soporte ─────────────────────────────────────────

    public function test_los_meses_tienen_nombre_en_espanol(): void
    {
        $this->assertSame('Enero', Meses::nombre(1));
        $this->assertSame('Septiembre', Meses::nombre('9'));
        $this->assertSame('Diciembre', Meses::nombre(12));
        $this->assertSame('', Meses::nombre(13));
        $this->assertSame('', Meses::nombre(null));
        $this->assertCount(12, Meses::NOMBRES);
    }

    public function test_los_conceptos_de_calculo_especial_nunca_son_editables_a_mano(): void
    {
        foreach (ConceptosDePago::CALCULO_ESPECIAL as $nombre) {
            $this->assertContains($nombre, ConceptosDePago::NO_EDITABLES);
        }
        $this->assertContains(ConceptosDePago::ASIGNACION_FAMILIAR, ConceptosDePago::NO_EDITABLES);
        $this->assertContains(ConceptosDePago::REMUNERACION_BASICA, ConceptosDePago::NO_EDITABLES);
    }

    public function test_los_nombres_de_conceptos_no_se_repiten(): void
    {
        $this->assertSame(
            count(ConceptosDePago::CALCULO_ESPECIAL),
            count(array_unique(ConceptosDePago::CALCULO_ESPECIAL))
        );
    }

    public function test_el_atributo_calculo_especial_del_concepto(): void
    {
        $especial = new PaymentConcept(['nombre' => ConceptosDePago::ONP]);
        $comun    = new PaymentConcept(['nombre' => 'Bono de alimentación']);

        $this->assertTrue($especial->calculo_especial);
        $this->assertFalse($comun->calculo_especial);
    }

    public function test_como_se_calculo_una_linea(): void
    {
        $porcentaje = new PayrollDetalle(['calculo' => 'porcentaje', 'valor' => 5]);
        $fijo       = new PayrollDetalle(['calculo' => 'fijo', 'valor' => 100]);
        $suelto     = new PayrollDetalle([]);

        $this->assertSame('5%', $porcentaje->como_se_calculo);
        $this->assertSame('S/ 100.00', $fijo->como_se_calculo);
        $this->assertNull($suelto->como_se_calculo);
    }
}
