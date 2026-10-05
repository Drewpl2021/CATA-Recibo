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
        'Otros Conceptos - Subsidio de Maternidad', 'Bonificación', 'Compensación por Tiempo de Servicios',
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
