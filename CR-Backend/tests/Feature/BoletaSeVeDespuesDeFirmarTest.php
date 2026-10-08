<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Planilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FirmadorPdfDePrueba;
use Tests\TestCase;

/**
 * Decisión del colegio: el colegio firma la boleta digitalmente (ReFirma),
 * al trabajador le llega el aviso, da su conformidad con su contraseña, y
 * recién entonces la ve y la descarga.
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

        // Mientras el colegio no la firma, no es suya todavía.
        $this->actingAs($trabajador, 'sanctum')->getJson("/api/documents/{$boleta->id}/view")->assertForbidden();

        // El colegio la firma en ReFirma y RR.HH. la sube.
        [$cert, $llave] = FirmadorPdfDePrueba::certificado('ISIDORO', 'RODRIGUEZ', '40112233');
        $firmada = FirmadorPdfDePrueba::firmar(Storage::disk('local')->get($boleta->archivo), $cert, $llave);
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->post('/api/payslips/signed', [
            'archivo' => UploadedFile::fake()->createWithContent("{$empleado->dni}_firmada.pdf", $firmada),
            'mes' => $mes, 'anio' => $anio,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.resultado', 'completa');

        // Antes de dar su conformidad: ni verla ni bajarla, por ninguno de los dos caminos.
        $this->actingAs($trabajador, 'sanctum')->getJson("/api/my-payslips/{$mes}/{$anio}?ver=1")->assertForbidden();
        $this->actingAs($trabajador, 'sanctum')->getJson("/api/documents/{$boleta->id}/view")->assertForbidden();
        $this->actingAs($trabajador, 'sanctum')->getJson("/api/documents/{$boleta->id}/download")->assertForbidden();

        // Da su conformidad con su contraseña.
        $this->actingAs($trabajador, 'sanctum')
            ->postJson("/api/my-documents/{$boleta->id}/sign", ['password' => 'ClaveSegura#2026'])
            ->assertOk();

        // Ahora sí.
        $this->actingAs($trabajador, 'sanctum')->get("/api/my-payslips/{$mes}/{$anio}?ver=1")->assertOk();
        $this->actingAs($trabajador, 'sanctum')->get("/api/documents/{$boleta->id}/view")->assertOk();
        $this->actingAs($trabajador, 'sanctum')->get("/api/documents/{$boleta->id}/download")->assertOk();

        // Es UN solo PDF, el firmado por el colegio: el mismo para él y para RR.HH.
        $paginas = function ($respuesta) {
            $base = $respuesta->baseResponse;
            $pdf = $base instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $respuesta->streamedContent() : $base->getContent();
            return preg_match_all('#/Type\s*/Page[^s]#', (string) $pdf);
        };
        $this->assertSame(1, $paginas($this->actingAs($trabajador, 'sanctum')->get("/api/documents/{$boleta->id}/download")));
        $this->assertSame(1, $paginas($this->actingAs($trabajador, 'sanctum')->get("/api/documents/{$boleta->id}/view")));
        $this->assertSame(1, $paginas($this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->get("/api/documents/{$boleta->id}/download")));
        $this->assertSame($firmada, Storage::disk('local')->get($boleta->fresh()->archivo));
    }
}
