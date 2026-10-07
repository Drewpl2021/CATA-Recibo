<?php

namespace Tests\Feature;

use App\Models\PayrollDetalle;
use App\Models\Planilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * La boleta impresa: las mismas filas siempre, como la boleta física del
 * colegio (postman/guia_boleta.jpeg), con "-" donde el monto es cero.
 */
class BoletaImpresaTest extends TestCase
{
    use RefreshDatabase;

    private const FILAS_DEL_MODELO = [
        'Remuneración Básica', 'Bonificación por Cargo', 'Asignación Familiar', 'Vacaciones Truncas',
        'Gratificaciones Fiestas Patrias - Ley 29351 y 30334', 'Bonif. Extraord. Temporal - Ley 29351 y 30334',
        'Otros Conceptos', 'Bonificación', 'Compensación por Tiempo de Servicios',
        'ONP 13%', 'SPP: Fondo Pensiones', 'SPP: Prima de Seguro', 'SPP: Comisión', 'I.R. 5ta Categoría',
        'Descuento Serv. Alimentación', 'Descuento Serv. de Bazar', 'Descuento Autorizado - Diezmo',
        'Descuento Otros Conceptos', 'Descuento - Pago Escolaridad Mensual',
        'ESSALUD 9%', 'SCTR', 'Adelanto de Sueldo', 'Adelanto de Bonificación',
    ];

    public function test_la_boleta_lleva_todas_las_filas_del_modelo_aunque_esten_en_cero(): void
    {
        Storage::fake('local');
        Mail::fake();

        $empleado = $this->crearEmpleado(['sueldo_base' => 2000]);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->postJson('/api/payroll-runs', ['nombre' => 'Planilla General', 'mes' => 3, 'anio' => (int) now()->year, 'generar' => true])
            ->assertCreated();

        // Se guardan los datos con que se armó el PDF, para leer la boleta como HTML.
        $datos = null;
        View::composer('boleta', function ($vista) use (&$datos) {
            $datos ??= $vista->getData();
        });

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->get("/api/payslips/{$empleado->id}/3/" . now()->year)
            ->assertOk();

        $this->assertNotNull($datos);
        $html = view('boleta', $datos)->render();
        foreach (self::FILAS_DEL_MODELO as $fila) {
            $this->assertStringContainsString(e($fila), $html, "Falta la fila «{$fila}» en la boleta");
        }
        // Sueldo de 2 000 sin hijos ni bonos: varias filas en cero, que salen con guion.
        $this->assertMatchesRegularExpression('/class="monto">-<\/td>/', $html);
    }

    public function test_el_detalle_de_otros_conceptos_no_se_imprime_y_los_extras_van_debajo(): void
    {
        Storage::fake('local');
        Mail::fake();

        $empleado = $this->crearEmpleado(['sueldo_base' => 2000]);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->postJson('/api/payroll-runs', ['nombre' => 'Planilla General', 'mes' => 3, 'anio' => (int) now()->year, 'generar' => true])
            ->assertCreated();
        $planilla = Planilla::where('empleado_id', $empleado->id)->firstOrFail();

        $otros = \App\Models\PaymentConcept::firstOrCreate(['nombre' => \App\Support\ConceptosDePago::OTROS_DESCUENTOS], ['tipo' => 'descuento', 'etiqueta_boleta' => 'Otros Conceptos']);
        $corbata = \App\Models\PaymentConcept::firstOrCreate(['nombre' => 'Descuento Corbatas y Polos'], ['tipo' => 'descuento']);
        PayrollDetalle::create(['planilla_id' => $planilla->id, 'payment_concept_id' => $otros->id, 'monto_calculado' => 50, 'descripcion' => 'Préstamo']);
        PayrollDetalle::create(['planilla_id' => $planilla->id, 'payment_concept_id' => $corbata->id, 'monto_calculado' => 9]);

        $datos = null;
        View::composer('boleta', function ($vista) use (&$datos) {
            $datos ??= $vista->getData();
        });
        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->get("/api/payslips/{$empleado->id}/3/" . now()->year)
            ->assertOk();

        $html = view('boleta', $datos)->render();
        // El detalle es interno (RR.HH. y Admin lo ven en la planilla): en la
        // boleta sale solo "Descuento Otros Conceptos", con su monto.
        $this->assertStringNotContainsString('Préstamo', $html);
        $this->assertStringContainsString('S/ 50.00', $html);
        $this->assertSame('Préstamo', PayrollDetalle::where('planilla_id', $planilla->id)->where('payment_concept_id', $otros->id)->value('descripcion'));
        // La corbata, justo después de "Otros Conceptos" y antes de la escolaridad.
        $otrosEn = strpos($html, 'Descuento Otros Conceptos');
        $corbataEn = strpos($html, 'Descuento Corbatas y Polos');
        $escolaridadEn = strpos($html, 'Descuento - Pago Escolaridad Mensual');
        $this->assertTrue($otrosEn < $corbataEn && $corbataEn < $escolaridadEn, 'La corbata tiene que ir debajo de Otros Conceptos');
        $this->assertStringContainsString('class="otro-concepto"', $html);
    }

    /**
     * VIDAL QUISPE ERICK, PLAME de setiembre: el Excel suma las líneas con sus
     * decimales (prima 39.83275, tardanza 1.164375) y redondea el total:
     * descuentos 400.75 y neto 2,506.75. Redondeando cada línea antes salía
     * 400.74 y 2,506.76.
     */
    public function test_el_neto_se_redondea_al_final_como_el_plame(): void
    {
        $empleado = $this->crearEmpleado();
        $planilla = Planilla::create(['empleado_id' => $empleado->id, 'mes' => 9, 'anio' => 2026, 'sueldo_base' => 2794.50, 'total' => 0]);
        foreach ([['Asignación Familiar', 'bonificacion', 113], ['SPP. Fondo Pensiones', 'descuento', 290.75],
                  ['SPP. Prima de Seguro', 'descuento', 39.83275], ['Descuento Autorizado - Tardanzas y Faltas', 'descuento', 1.164375],
                  ['Descuento por Curso IA', 'descuento', 60], ['Descuento Corbatas y Polos', 'descuento', 9]] as [$nombre, $tipo, $monto]) {
            $c = \App\Models\PaymentConcept::firstOrCreate(['nombre' => $nombre], ['tipo' => $tipo]);
            PayrollDetalle::create(['planilla_id' => $planilla->id, 'payment_concept_id' => $c->id, 'monto_calculado' => $monto]);
        }

        $this->assertSame(2506.75, $planilla->recalcularTotal());
        $prima = PayrollDetalle::whereHas('paymentConcept', fn ($q) => $q->where('nombre', 'SPP. Prima de Seguro'))->first();
        $this->assertEqualsWithDelta(39.83275, (float) $prima->getRawOriginal('monto_calculado'), 0.0000001);
    }

    public function test_no_se_agrega_dos_veces_el_mismo_concepto_en_el_mes(): void
    {
        $empleado = $this->crearEmpleado();
        $planilla = Planilla::create(['empleado_id' => $empleado->id, 'mes' => 9, 'anio' => 2026, 'sueldo_base' => 2000, 'total' => 2000]);
        $corbata = \App\Models\PaymentConcept::firstOrCreate(['nombre' => 'Descuento Corbatas y Polos'], ['tipo' => 'descuento']);
        $admin = $this->crearUsuario('rrhh');
        $linea = ['planilla_id' => $planilla->id, 'payment_concept_id' => $corbata->id, 'monto_calculado' => 9];

        $this->actingAs($admin, 'sanctum')->postJson('/api/payroll-details', $linea)->assertCreated();
        $this->actingAs($admin, 'sanctum')->postJson('/api/payroll-details', $linea)->assertStatus(422);

        $this->assertSame(1, PayrollDetalle::where('planilla_id', $planilla->id)->count());
    }

    public function test_la_vista_previa_calcula_la_renta_de_5ta_sin_guardar_nada(): void
    {
        $empleado = $this->crearEmpleado(['sueldo_base' => 2000]);
        $anio = (int) now()->year;

        $alto = $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson("/api/payrolls/renta-5ta?empleado_id={$empleado->id}&mes=1&anio={$anio}&sueldo=12000")
            ->assertOk()->json('data.monto');
        $bajo = $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson("/api/payrolls/renta-5ta?empleado_id={$empleado->id}&mes=1&anio={$anio}&sueldo=1500")
            ->assertOk()->json('data.monto');

        $this->assertGreaterThan(0, $alto, 'Con 12 000 al mes sí le toca retención');
        $this->assertEquals(0, $bajo, 'Con 1 500 al mes no pasa las 7 UIT');
        $this->assertSame(0, Planilla::count());
        $this->assertSame(0, PayrollDetalle::count());
    }
}
