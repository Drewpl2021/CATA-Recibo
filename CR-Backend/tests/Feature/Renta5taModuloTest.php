<?php

namespace Tests\Feature;

use App\Models\PaymentConcept;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use App\Models\RentaQuintaPrevia;
use App\Support\ConceptosDePago;
use App\Support\LibroExcel;
use App\Support\Renta5ta\MetodoRenta5ta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** El módulo «Renta de 5ta»: lista, hoja de cada trabajador, historial, recálculo y exportación. */
class Renta5taModuloTest extends TestCase
{
    use RefreshDatabase;

    private function trabajador()
    {
        return $this->crearEmpleado(['sueldo_base' => 5000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);
    }

    public function test_la_lista_y_la_hoja_de_un_trabajador(): void
    {
        $e = $this->trabajador();
        $rrhh = $this->crearUsuario('rrhh');

        $lista = $this->actingAs($rrhh, 'sanctum')->getJson('/api/income-tax?anio=2026')->assertOk();
        $fila = collect($lista->json('data.filas'))->firstWhere('id', $e->id);
        $this->assertEqualsWithDelta(2886, $fila['impuesto_anual'], 0.01);

        $this->actingAs($rrhh, 'sanctum')->getJson("/api/income-tax/{$e->id}?anio=2026")
            ->assertOk()
            ->assertJsonCount(12, 'data.meses')
            ->assertJsonPath('data.empleado.dni', $e->dni)
            ->assertJsonPath('data.meses.0.retencion', 240.5);
    }

    public function test_guardar_un_mes_del_historial_recalcula_las_planillas_siguientes(): void
    {
        $e = $this->trabajador();
        $rrhh = $this->crearUsuario('rrhh');
        PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::RENTA_5TA], ['tipo' => 'descuento']);
        $abril = Planilla::create(['empleado_id' => $e->id, 'mes' => 4, 'anio' => 2026, 'sueldo_base' => 5000, 'total' => 0]);

        // Enero a marzo se pagaron fuera del sistema, sin retener nada.
        foreach ([1, 2, 3] as $m) {
            $this->actingAs($rrhh, 'sanctum')->putJson("/api/income-tax/{$e->id}/history", [
                'anio' => 2026, 'mes' => $m, 'remuneracion' => 5000, 'retencion' => 0,
            ])->assertOk();
        }

        // Abril: (2,886 − 0) ÷ 9 = 320.67, ya guardado en su planilla.
        $linea = PayrollDetalle::where('planilla_id', $abril->id)
            ->whereHas('paymentConcept', fn ($q) => $q->where('nombre', ConceptosDePago::RENTA_5TA))->value('monto_calculado');
        $this->assertEqualsWithDelta(320.67, (float) $linea, 0.001);

        // Un mes con planilla no se escribe a mano.
        $this->actingAs($rrhh, 'sanctum')->putJson("/api/income-tax/{$e->id}/history", [
            'anio' => 2026, 'mes' => 4, 'remuneracion' => 1, 'retencion' => 1,
        ])->assertStatus(422);
    }

    public function test_el_excel_del_historial_se_descarga_y_se_sube(): void
    {
        $e = $this->trabajador();
        $rrhh = $this->crearUsuario('rrhh');

        $this->actingAs($rrhh, 'sanctum')->get('/api/income-tax/history/template?anio=2026')->assertOk();

        // El mismo formato del modelo, con enero y febrero llenos.
        $titulos = ['N°', 'DNI', 'Apellidos y Nombres', 'Ene - Remuneración', 'Ene - Retención 5ta', 'Feb - Remuneración', 'Feb - Retención 5ta'];
        $libro = new LibroExcel();
        $libro->hoja('Historial 5ta', [$titulos, [1, (string) $e->dni, 'X', 5000, 240.5, 5000, 240.5], [2, '99999999', 'Nadie', 1, 1, null, null]]);
        $ruta = $libro->guardar();

        $this->actingAs($rrhh, 'sanctum')
            ->post('/api/income-tax/history', ['anio' => 2026, 'archivo' => new UploadedFile($ruta, 'historial.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.meses_guardados', 2)
            ->assertJsonPath('data.no_encontrados', ['99999999']);

        $this->assertSame(2, RentaQuintaPrevia::where('empleado_id', $e->id)->count());
    }

    public function test_exportar_recalcular_y_permisos(): void
    {
        $this->trabajador();
        $rrhh = $this->crearUsuario('rrhh');

        $this->actingAs($rrhh, 'sanctum')->get('/api/income-tax/export?anio=2026')->assertOk();
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/income-tax/recalculate', ['anio' => 2026])->assertOk();
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')->getJson('/api/income-tax?anio=2026')->assertForbidden();
    }

    public function test_el_ajuste_permite_volver_al_metodo_de_la_hoja_de_rrhh(): void
    {
        $this->assertFalse(MetodoRenta5ta::comoHojaDeRrhh());

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->putJson('/api/settings', [MetodoRenta5ta::AJUSTE => true])
            ->assertOk()
            ->assertJsonPath('data.' . MetodoRenta5ta::AJUSTE, true);

        $this->assertTrue(MetodoRenta5ta::comoHojaDeRrhh());
    }
}
