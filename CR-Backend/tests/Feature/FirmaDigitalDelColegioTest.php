<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\PaymentConcept;
use App\Models\Planilla;
use App\Support\FirmaDigitalDeBoletas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FirmadorPdfDePrueba;
use Tests\TestCase;

/**
 * Boletas con firma digital del colegio (ReFirma): emitir, bajarlas para
 * firmar, subirlas firmadas, y recién entonces entregarlas al trabajador.
 */
class FirmaDigitalDelColegioTest extends TestCase
{
    use RefreshDatabase;

    private $rrhh;
    private $empleado;
    private $trabajador;
    private Planilla $planilla;
    private int $mes;
    private int $anio;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        Configuracion::poner(FirmaDigitalDeBoletas::AJUSTE, true);

        $this->mes = (int) now()->month;
        $this->anio = (int) now()->year;
        $this->rrhh = $this->crearUsuario('rrhh');
        $this->empleado = $this->crearEmpleado(['sueldo_base' => 2000]);
        $this->trabajador = $this->crearUsuario('empleado');
        $this->trabajador->forceFill(['empleado_id' => (string) $this->empleado->id])->save();
        $this->planilla = Planilla::create([
            'empleado_id' => $this->empleado->id, 'mes' => $this->mes, 'anio' => $this->anio, 'sueldo_base' => 2000, 'total' => 2000,
        ]);
    }

    private function emitir(): Documento
    {
        $this->actingAs($this->rrhh, 'sanctum')->get("/api/payslips/{$this->empleado->id}/{$this->mes}/{$this->anio}")->assertOk();

        return Documento::where('planilla_id', $this->planilla->id)->where('tipo', 'boleta')->firstOrFail();
    }

    private function firmado(Documento $boleta, array ...$firmantes): string
    {
        $pdf = Storage::disk('local')->get($boleta->archivo_sin_firma ?? $boleta->archivo);
        foreach ($firmantes as [$nombre, $apellido, $dni]) {
            [$cert, $llave] = FirmadorPdfDePrueba::certificado($nombre, $apellido, $dni);
            $pdf = FirmadorPdfDePrueba::firmar($pdf, $cert, $llave);
        }

        return $pdf;
    }

    private function subir(string $contenido, string $ruta = 'payslips/signed', ?string $nombre = null)
    {
        $nombre ??= "{$this->planilla->numeroDeBoleta()}_{$this->empleado->dni}_FIRMADO[R].pdf";

        return $this->actingAs($this->rrhh, 'sanctum')->post("/api/{$ruta}", [
            'archivo' => UploadedFile::fake()->createWithContent($nombre, $contenido),
            'mes' => $this->mes, 'anio' => $this->anio,
        ], ['Accept' => 'application/json']);
    }

    private function paginas(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    public function test_el_camino_completo_con_una_firma(): void
    {
        $boleta = $this->emitir();

        // Emitida: espera la firma del colegio, una sola copia, y al
        // trabajador no le llega nada todavía.
        $this->assertSame('pendiente', $boleta->firma_colegio);
        $this->assertSame(1, $this->paginas(Storage::disk('local')->get($boleta->archivo)));
        $this->assertSame(0, Notificacion::count());
        $this->actingAs($this->trabajador, 'sanctum')->getJson('/api/my-documents?page=0&size=10')->assertOk()->assertJsonCount(0, 'data.content');
        $this->actingAs($this->trabajador, 'sanctum')->getJson("/api/my-payslips/{$this->mes}/{$this->anio}?ver=1")->assertForbidden();

        // El .zip «para firmar» la trae.
        $zip = $this->actingAs($this->rrhh, 'sanctum')->get("/api/employees/payslips-zip?mes={$this->mes}&anio={$this->anio}&para_firmar=1")->assertOk();
        $this->assertStringContainsString('para_firmar', $zip->headers->get('content-disposition'));

        // Sin firmar: se rechaza.
        $this->subir(Storage::disk('local')->get($boleta->archivo), 'payslips/signed/check')
            ->assertOk()->assertJsonPath('data.estado', 'sin_firma');

        // Firmada por el señor Isidoro: la revisión dice que está lista, y
        // todavía no guarda nada.
        $firmada = $this->firmado($boleta, ['ISIDORO', 'RODRIGUEZ', '40112233']);
        $this->subir($firmada, 'payslips/signed/check')->assertOk()
            ->assertJsonPath('data.estado', 'ok')
            ->assertJsonPath('data.resultado', 'completa')
            ->assertJsonPath('data.firmas.0.nombre', 'ISIDORO RODRIGUEZ')
            ->assertJsonPath('data.firmas.0.dni', '40112233');
        $this->assertSame('pendiente', $boleta->fresh()->firma_colegio);

        // Guardada: completa, el archivo es el firmado y se le avisa.
        $this->subir($firmada)->assertOk()->assertJsonPath('data.resultado', 'completa');
        $boleta->refresh();
        $this->assertSame('completa', $boleta->firma_colegio);
        $this->assertSame($firmada, Storage::disk('local')->get($boleta->archivo));
        $this->assertSame('ISIDORO RODRIGUEZ', $boleta->firmas_colegio[0]['nombre']);
        $this->assertSame(1, Notificacion::count());

        // Ya no se le cambian los conceptos.
        $bono = PaymentConcept::create(['nombre' => 'Bono', 'tipo' => 'bonificacion']);
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/payroll-details', [
            'planilla_id' => $this->planilla->id, 'payment_concept_id' => $bono->id, 'monto_calculado' => 10,
        ])->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'firma digital del colegio'));

        // El trabajador la ve, da su conformidad, y el PDF firmado no se toca.
        $this->actingAs($this->trabajador, 'sanctum')->getJson('/api/my-documents?page=0&size=10')->assertJsonCount(1, 'data.content');
        $this->actingAs($this->trabajador, 'sanctum')
            ->postJson("/api/my-documents/{$boleta->id}/sign", ['password' => 'ClaveSegura#2026'])->assertOk();
        $this->assertSame('firmado', $boleta->fresh()->estado_firma);
        $this->assertSame($firmada, Storage::disk('local')->get($boleta->fresh()->archivo));

        // Lo que se baja, él o RR.HH., es exactamente el firmado.
        $suya = $this->actingAs($this->trabajador, 'sanctum')->get("/api/my-payslips/{$this->mes}/{$this->anio}")->assertOk();
        $this->assertSame($firmada, $suya->streamedContent());
        $this->actingAs($this->rrhh, 'sanctum')->get("/api/payslips/{$this->empleado->id}/{$this->mes}/{$this->anio}")->assertOk();
        $this->assertSame($firmada, Storage::disk('local')->get($boleta->fresh()->archivo));

        // La constancia de entrega lo dice.
        $excel = $this->actingAs($this->rrhh, 'sanctum')->get("/api/payslips/delivery-record?mes={$this->mes}&anio={$this->anio}")->assertOk();
        $hoja = \PhpOffice\PhpSpreadsheet\IOFactory::load($excel->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('ISIDORO RODRIGUEZ', $hoja->getCell('E5')->getValue());
        $this->assertSame('Recibida, con conformidad', $hoja->getCell('L5')->getValue());
    }

    public function test_con_dos_firmas_requeridas_la_primera_deja_la_boleta_a_medias(): void
    {
        Configuracion::ponerNumero(FirmaDigitalDeBoletas::AJUSTE_FIRMAS, 2);
        $boleta = $this->emitir();

        $una = $this->firmado($boleta, ['ISIDORO', 'RODRIGUEZ', '40112233']);
        $this->subir($una)->assertOk()->assertJsonPath('data.resultado', 'parcial');
        $this->assertSame('parcial', $boleta->fresh()->firma_colegio);
        $this->assertSame(0, Notificacion::count());

        // La misma persona dos veces no son dos firmas.
        [$cert, $llave] = FirmadorPdfDePrueba::certificado('ISIDORO', 'RODRIGUEZ', '40112233');
        $repetida = FirmadorPdfDePrueba::firmar($una, $cert, $llave);
        $this->subir($repetida, 'payslips/signed/check')->assertJsonPath('data.estado', 'sin_cambios');

        // La segunda persona firma ENCIMA de la primera: completa.
        [$cert2, $llave2] = FirmadorPdfDePrueba::certificado('ROSA', 'MAMANI', '41223344');
        $dos = FirmadorPdfDePrueba::firmar($una, $cert2, $llave2);
        $this->subir($dos)->assertOk()->assertJsonPath('data.resultado', 'completa')->assertJsonPath('data.firmas_validas', 2);
        $this->assertSame(1, Notificacion::count());
    }

    public function test_se_rechaza_lo_que_no_es_la_boleta_emitida(): void
    {
        $boleta = $this->emitir();
        $firmada = $this->firmado($boleta, ['ISIDORO', 'RODRIGUEZ', '40112233']);

        // Cambiada después de firmar.
        $this->subir(str_replace('/Type /Sig', '/Type /Sih', $firmada), 'payslips/signed/check')
            ->assertJsonPath('data.estado', fn ($e) => in_array($e, ['firma_invalida', 'sin_firma'], true));
        // Con algo agregado después de la firma.
        $this->subir($firmada . "\n% algo mas\n", 'payslips/signed/check')->assertJsonPath('data.estado', 'modificada');
        // Otro PDF que no es esta boleta.
        [$c, $k] = FirmadorPdfDePrueba::certificado('ISIDORO', 'RODRIGUEZ', '40112233');
        $otro = FirmadorPdfDePrueba::firmar("%PDF-1.4\n%%EOF\n", $c, $k);
        $this->subir($otro, 'payslips/signed/check')->assertJsonPath('data.estado', 'cambiada');
        // Sin DNI en el nombre, o de otro mes.
        $this->subir($firmada, 'payslips/signed/check', 'boleta-firmada.pdf')->assertJsonPath('data.estado', 'no_reconocido');
        $this->subir($firmada, 'payslips/signed/check', "BOL-{$this->anio}-0099_{$this->empleado->dni}.pdf")->assertJsonPath('data.estado', 'otro_mes');

        // Si después de bajarla se le cambió un concepto, la firmada ya no sirve.
        $bono = PaymentConcept::create(['nombre' => 'Bono', 'tipo' => 'bonificacion']);
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/payroll-details', [
            'planilla_id' => $this->planilla->id, 'payment_concept_id' => $bono->id, 'monto_calculado' => 10,
        ])->assertCreated()->assertJsonPath('boleta_rehecha', true);
        $this->subir($firmada, 'payslips/signed/check')->assertJsonPath('data.estado', 'cambiada');

        // Nada de esto se guardó.
        $this->subir($firmada)->assertStatus(422);
        $this->assertSame('pendiente', $boleta->fresh()->firma_colegio);
    }

    public function test_anular_para_corregir_solo_si_el_trabajador_no_la_abrio(): void
    {
        $boleta = $this->emitir();
        $this->subir($this->firmado($boleta, ['ISIDORO', 'RODRIGUEZ', '40112233']))->assertOk();

        $this->actingAs($this->rrhh, 'sanctum')->postJson("/api/payslips/{$boleta->id}/void")->assertOk();
        $this->assertNull(Documento::find($boleta->id));
        // Ya se puede corregir y volver a emitir.
        $this->assertNull($this->planilla->fresh()->motivoParaNoTocar());
        $nueva = $this->emitir();
        $this->assertSame('pendiente', $nueva->firma_colegio);

        // Abierta por el trabajador: ya no.
        $this->subir($this->firmado($nueva, ['ISIDORO', 'RODRIGUEZ', '40112233']))->assertOk();
        $this->actingAs($this->trabajador, 'sanctum')->get("/api/my-payslips/{$this->mes}/{$this->anio}?ver=1");
        $nueva->forceFill(['estado_firma' => 'visto'])->save();
        $this->actingAs($this->rrhh, 'sanctum')->postJson("/api/payslips/{$nueva->id}/void")->assertStatus(422);
    }

    public function test_con_el_ajuste_apagado_todo_sigue_como_antes(): void
    {
        Configuracion::poner(FirmaDigitalDeBoletas::AJUSTE, false);
        $boleta = $this->emitir();

        $this->assertNull($boleta->firma_colegio);
        $this->assertSame(2, $this->paginas(Storage::disk('local')->get($boleta->archivo)));
        $this->assertSame(1, Notificacion::count());
        $this->subir(Storage::disk('local')->get($boleta->archivo), 'payslips/signed/check')->assertJsonPath('data.estado', 'no_aplica');
    }
}
