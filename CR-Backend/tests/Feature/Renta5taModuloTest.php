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

    public function test_la_lista_de_un_mes_trae_lo_que_correspondia_y_lo_retenido(): void
    {
        $e = $this->trabajador();
        $bajo = $this->crearEmpleado(['sueldo_base' => 1500, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);
        $rrhh = $this->crearUsuario('rrhh');

        // En enero le correspondían 240.50 y se le retuvo 100.
        $this->actingAs($rrhh, 'sanctum')->putJson("/api/income-tax/{$e->id}/history", [
            'anio' => 2026, 'mes' => 1, 'remuneracion' => 5000, 'retencion' => 100,
        ])->assertOk();

        $lista = $this->actingAs($rrhh, 'sanctum')->getJson('/api/income-tax?anio=2026&mes=1')->assertOk();
        $this->assertSame(1, $lista->json('data.mes'));

        $fila = collect($lista->json('data.filas'))->firstWhere('id', $e->id);
        $this->assertEqualsWithDelta(240.5, $fila['corresponde_mes'], 0.001);
        $this->assertEqualsWithDelta(100, $fila['retenido_mes'], 0.001);
        $this->assertEqualsWithDelta(-140.5, $fila['diferencia_mes'], 0.001);
        $this->assertSame('historial', $fila['fuente_mes']);
        $this->assertSame([1], $fila['meses_con_diferencia']);
        $this->assertTrue($fila['paga_5ta']);

        // Quien gana menos de 7 UIT al año no paga 5ta.
        $this->assertFalse(collect($lista->json('data.filas'))->firstWhere('id', $bajo->id)['paga_5ta']);
        $this->assertSame(1, $lista->json('data.resumen.pagan_5ta'));
        $this->assertSame(1, $lista->json('data.resumen.con_diferencias'));

        // Un mes sin planilla ni historial: se calcula lo que le toca, pero no hay retenido.
        $fila = collect($this->actingAs($rrhh, 'sanctum')->getJson('/api/income-tax?anio=2026&mes=3')->json('data.filas'))->firstWhere('id', $e->id);
        $this->assertNull($fila['retenido_mes']);
        $this->assertNull($fila['diferencia_mes']);

        $this->actingAs($rrhh, 'sanctum')->getJson('/api/income-tax?anio=2026&mes=13')->assertStatus(422);
    }

    public function test_el_excel_baja_con_los_filtros_y_la_busqueda_de_la_pantalla(): void
    {
        $alto = $this->trabajador();
        $this->crearEmpleado(['sueldo_base' => 1500, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);
        $rrhh = $this->crearUsuario('rrhh');

        $lista = function (string $query) use ($rrhh) {
            $r = $this->actingAs($rrhh, 'sanctum')->get('/api/income-tax/export?anio=2026&mes=1' . $query)->assertOk();
            $hoja = \PhpOffice\PhpSpreadsheet\IOFactory::load($r->baseResponse->getFile()->getPathname())->getSheetByName('Lista');

            return [$hoja, $r->headers->get('content-disposition')];
        };

        // Solo los que pagan 5ta: el de 5,000.
        [$hoja, $nombre] = $lista('&situacion=pagan');
        $this->assertSame('Renta de 5ta · Enero 2026', $hoja->getCell('A1')->getValue());
        $this->assertStringContainsString('Situación: Pagan 5ta', $hoja->getCell('A2')->getValue());
        $this->assertSame($alto->dni, (string) $hoja->getCell('B5')->getValue());
        $this->assertSame('TOTAL', $hoja->getCell('C6')->getValue());
        $this->assertEqualsWithDelta(240.5, $hoja->getCell('L5')->getValue(), 0.001);
        $this->assertStringContainsString('(filtrado)', $nombre);

        // Sin filtros, los dos; con una búsqueda que no calza, ninguno.
        [$hoja] = $lista('');
        $this->assertSame('Todo el personal · 2 de 2 trabajador(es)', $hoja->getCell('A2')->getValue());
        $this->assertSame('TOTAL', $hoja->getCell('C7')->getValue());
        [$hoja] = $lista('&search=nadie-se-llama-asi');
        $this->assertSame('TOTAL', $hoja->getCell('C5')->getValue());

        $this->actingAs($rrhh, 'sanctum')->getJson('/api/income-tax/export?anio=2026&situacion=otra')->assertStatus(422);
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
