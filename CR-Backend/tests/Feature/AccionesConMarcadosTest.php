<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Planilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Lo que se hace con los marcados (la barra de abajo en Empleados,
 * Planillas y Emisión de boletas): marcar los de todas las páginas, y
 * exportar, emitir o bajar en .zip solo a esos.
 */
class AccionesConMarcadosTest extends TestCase
{
    use RefreshDatabase;

    private $rrhh;
    private int $mes;
    private int $anio;
    private array $empleados = [];
    private array $planillas = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->mes = (int) now()->month;
        $this->anio = (int) now()->year;
        $this->rrhh = $this->crearUsuario('rrhh');

        foreach ([['42083098', 'Gatica Quispe'], ['40000001', 'Ñahui Pérez'], ['40000002', 'Zapana Quispe']] as [$dni, $apellido]) {
            $e = $this->crearEmpleado(['dni' => $dni, 'apellido' => $apellido, 'sueldo_base' => 2000]);
            $this->empleados[] = $e;
            $this->planillas[] = Planilla::create(['empleado_id' => $e->id, 'mes' => $this->mes, 'anio' => $this->anio, 'sueldo_base' => 2000, 'total' => 2000]);
        }
    }

    /** Todas las celdas de la primera hoja, como texto. */
    private function textoDelExcel($respuesta): string
    {
        $hoja = IOFactory::load($respuesta->baseResponse->getFile()->getPathname())->getActiveSheet();

        return implode('|', array_map(fn ($fila) => implode('|', array_map('strval', $fila)), $hoja->toArray()));
    }

    public function test_marcar_los_de_todas_las_paginas_respeta_el_buscador(): void
    {
        $todos = $this->actingAs($this->rrhh, 'sanctum')->getJson('/api/employees?todos=1&search=quispe')->assertOk();
        $this->assertSame(2, $todos->json('data.totalElements'));
        $this->assertEqualsCanonicalizing(
            [(string) $this->empleados[0]->id, (string) $this->empleados[2]->id],
            array_map('strval', array_column($todos->json('data.content'), 'id'))
        );

        // Sin «todos» la lista pagina como siempre.
        $this->actingAs($this->rrhh, 'sanctum')->getJson('/api/employees?page=0&size=1')
            ->assertOk()->assertJsonCount(1, 'data.content')->assertJsonPath('data.totalElements', 3);
    }

    public function test_exportar_solo_los_empleados_marcados(): void
    {
        $respuesta = $this->actingAs($this->rrhh, 'sanctum')->post('/api/employees/export', [
            'ids' => [(string) $this->empleados[0]->id, (string) $this->empleados[2]->id],
        ])->assertOk();

        $texto = $this->textoDelExcel($respuesta);
        $this->assertStringContainsString('42083098', $texto);
        $this->assertStringContainsString('40000002', $texto);
        $this->assertStringNotContainsString('40000001', $texto);

        // Sin marcados, salen todos (como antes).
        $this->assertStringContainsString('40000001', $this->textoDelExcel(
            $this->actingAs($this->rrhh, 'sanctum')->get('/api/employees/export')->assertOk()
        ));

        // Ids que no son ids: se rechazan.
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/employees/export', ['ids' => ['1 OR 1=1']])->assertStatus(422);
    }

    public function test_exportar_solo_las_planillas_marcadas(): void
    {
        $respuesta = $this->actingAs($this->rrhh, 'sanctum')->post('/api/payrolls/export', [
            'mes' => $this->mes, 'anio' => $this->anio, 'ids' => [(string) $this->planillas[1]->id],
        ])->assertOk();

        $texto = $this->textoDelExcel($respuesta);
        $this->assertStringContainsString('40000001', $texto);
        $this->assertStringNotContainsString('42083098', $texto);
        $this->assertStringNotContainsString('40000002', $texto);
    }

    public function test_emitir_solo_a_los_marcados(): void
    {
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/payslips/generate-bulk', [
            'mes' => $this->mes, 'anio' => $this->anio, 'empleado_ids' => [(string) $this->empleados[1]->id],
        ])->assertOk();

        $emitidas = Documento::where('tipo', 'boleta')->pluck('empleado_id')->map(fn ($id) => (string) $id)->all();
        $this->assertSame([(string) $this->empleados[1]->id], $emitidas);
    }

    public function test_el_zip_trae_solo_a_los_marcados_y_el_enlace_vence(): void
    {
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/payslips/generate-bulk', ['mes' => $this->mes, 'anio' => $this->anio])->assertOk();
        $this->assertSame(3, Documento::where('tipo', 'boleta')->count());

        $enlace = $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/employees/payslips-zip/link', [
            'mes' => $this->mes, 'anio' => $this->anio, 'ids' => [(string) $this->empleados[0]->id, (string) $this->empleados[2]->id],
        ])->assertOk()->assertJsonPath('data.cantidad', 2)->json('data.url');

        $this->app['auth']->forgetGuards();
        $zip = new \ZipArchive();
        $zip->open($this->get($enlace)->assertOk()->baseResponse->getFile()->getPathname());
        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $this->assertCount(2, $nombres);
        $this->assertStringNotContainsString('40000001', implode(' ', $nombres));

        // La selección guardada vence: el enlace ya no sirve.
        Cache::flush();
        $this->get($enlace)->assertStatus(410);
    }
}
