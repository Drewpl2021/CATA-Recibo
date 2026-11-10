<?php

namespace Tests\Feature;

use App\Models\CtsLiquidacion;
use App\Models\Documento;
use App\Models\Planilla;
use App\Support\Pdf\LectorPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La boleta de noviembre (y la de mayo) lleva la CTS que se le depositó:
 * como en el PLAME, figura como ingreso y sale igual como «Adelanto de CTS»,
 * así que el neto no cambia.
 */
class BoletaConCtsDepositadaTest extends TestCase
{
    use RefreshDatabase;

    private function textoDeLaBoleta($admin, $empleado, int $mes): string
    {
        $this->actingAs($admin, 'sanctum')->get("/api/payslips/{$empleado->id}/{$mes}/2026")->assertOk();
        $documento = Documento::where('empleado_id', $empleado->id)->where('tipo', 'boleta')
            ->whereHas('planilla', fn ($q) => $q->where('mes', $mes))->firstOrFail();
        $pdf = Storage::disk('local')->get($documento->archivo);
        $lector = new LectorPdf($pdf);
        $paginas = $lector->diccionario($lector->referencia($lector->diccionario($lector->referencia($lector->trailer()['Root'])[0])['Pages'])[0]);
        preg_match('/(\d+)\s+\d+\s+R/', $lector->valor($paginas['Kids']), $m);

        return $lector->contenidoDePagina($lector->diccionario((int) $m[1]));
    }

    /** El monto que sigue a «Total líquido» en la boleta. */
    private function neto(string $texto): ?string
    {
        return preg_match('/QUIDO\).*?\(S\/ ([\d,]+\.\d{2})\)/si', $texto, $m) ? $m[1] : null;
    }

    public function test_la_boleta_de_noviembre_lleva_la_cts_depositada_sin_cambiar_el_neto(): void
    {
        Storage::fake('local');
        Mail::fake();
        $admin = $this->crearUsuario('admin');
        $empleado = $this->crearEmpleado(['sueldo_base' => 2000]);
        foreach ([10, 11] as $mes) {
            Planilla::create(['empleado_id' => $empleado->id, 'mes' => $mes, 'anio' => 2026, 'sueldo_base' => 2000, 'total' => 2000]);
        }

        $sinCts = $this->textoDeLaBoleta($admin, $empleado, 11);
        Documento::where('empleado_id', $empleado->id)->delete();

        CtsLiquidacion::create([
            'empleado_id' => $empleado->id, 'anio' => 2026, 'periodo' => 'noviembre',
            'desde' => '2026-05-01', 'hasta' => '2026-10-31', 'monto' => 1489.12,
            'estado' => CtsLiquidacion::DEPOSITADA, 'fecha_deposito' => '2026-11-06',
        ]);
        $conCts = $this->textoDeLaBoleta($admin, $empleado, 11);

        $this->assertStringNotContainsString('1,489.12', $sinCts);
        // Como ingreso, como adelanto y en el total de adelantos.
        $this->assertSame(3, substr_count($conCts, '(S/ 1,489.12)'));
        $this->assertStringContainsString('Adelanto de CTS', $conCts);
        // El neto es el mismo con o sin la CTS: se depositó aparte.
        $this->assertSame($this->neto($sinCts), $this->neto($conCts));
        $this->assertNotNull($this->neto($conCts));

        // Octubre no es mes de CTS: no la lleva.
        $this->assertStringNotContainsString('Adelanto de CTS', $this->textoDeLaBoleta($admin, $empleado, 10));
    }
}
