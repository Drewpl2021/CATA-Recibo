<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Planilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Decisión del colegio: al trabajador le llega el aviso de su boleta, la
 * firma con su contraseña, y recién entonces la ve y la descarga.
 */
class BoletaSeVeDespuesDeFirmarTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_trabajador_ve_su_boleta_solo_despues_de_firmarla(): void
    {
        Storage::fake('local');
        Mail::fake();

        $empleado = $this->crearEmpleado(['sueldo_base' => 2000]);
        $trabajador = $this->crearUsuario('empleado', ['password' => 'ClaveSegura#2026']);
        $trabajador->forceFill(['empleado_id' => (string) $empleado->id])->save();

        $anio = (int) now()->year;
        $mes = (int) now()->month;
        Planilla::create(['empleado_id' => $empleado->id, 'mes' => $mes, 'anio' => $anio, 'sueldo_base' => 2000, 'total' => 2000]);

        // RR.HH. la emite.
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->get("/api/payslips/{$empleado->id}/{$mes}/{$anio}")
            ->assertOk();
        $boleta = Documento::where('empleado_id', $empleado->id)->where('tipo', 'boleta')->firstOrFail();

        // Antes de firmar: ni verla ni bajarla, por ninguno de los dos caminos.
        $this->actingAs($trabajador, 'sanctum')->getJson("/api/my-payslips/{$mes}/{$anio}?ver=1")->assertForbidden();
        $this->actingAs($trabajador, 'sanctum')->getJson("/api/documents/{$boleta->id}/view")->assertForbidden();
        $this->actingAs($trabajador, 'sanctum')->getJson("/api/documents/{$boleta->id}/download")->assertForbidden();

        // La firma con su contraseña.
        $this->actingAs($trabajador, 'sanctum')
            ->postJson("/api/my-documents/{$boleta->id}/sign", ['password' => 'ClaveSegura#2026'])
            ->assertOk();

        // Ahora sí.
        $this->actingAs($trabajador, 'sanctum')->get("/api/my-payslips/{$mes}/{$anio}?ver=1")->assertOk();
        $this->actingAs($trabajador, 'sanctum')->get("/api/documents/{$boleta->id}/view")->assertOk();
        $this->actingAs($trabajador, 'sanctum')->get("/api/documents/{$boleta->id}/download")->assertOk();

        // El trabajador recibe solo SU copia; RR.HH., el ejemplar con las dos.
        $paginas = function ($respuesta) {
            $base = $respuesta->baseResponse;
            $pdf = $base instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $respuesta->streamedContent() : $base->getContent();
            return preg_match_all('#/Type\s*/Page[^s]#', (string) $pdf);
        };
        $this->assertSame(1, $paginas($this->actingAs($trabajador, 'sanctum')->get("/api/documents/{$boleta->id}/download")));
        $this->assertSame(1, $paginas($this->actingAs($trabajador, 'sanctum')->get("/api/documents/{$boleta->id}/view")));
        $this->assertSame(2, $paginas($this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->get("/api/documents/{$boleta->id}/download")));
    }
}
