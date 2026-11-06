<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Planilla;
use App\Support\FirmadorPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FirmadorPdfDePrueba;
use Tests\TestCase;

/**
 * El trabajador baja varias de sus boletas y hojas juntas: en un .zip (cada
 * una tal cual, firmada) o en un solo PDF para leer o imprimir. Solo lo
 * suyo y lo que ya tiene su conformidad.
 */
class JuntarMisDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private $empleado;
    private $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->empleado = $this->crearEmpleado(['dni' => '40123456']);
        $this->usuario = $this->crearUsuario('empleado');
        $this->usuario->forceFill(['empleado_id' => (string) $this->empleado->id])->save();
    }

    /** Un PDF de una página, firmado digitalmente como lo firma el sistema. */
    private function pdfFirmado(string $texto): string
    {
        [$certificado, $llave] = FirmadorPdfDePrueba::certificado('Isidoro', 'Rodriguez Mamani', '01234567');

        return FirmadorPdf::firmar(Pdf::loadHTML("<p>{$texto}</p>")->output(), $certificado, $llave, [], ['nombre' => 'ISIDORO']);
    }

    private function hoja(string $tipo, int $mes, string $estadoFirma = 'firmado', $empleado = null): Documento
    {
        $empleado ??= $this->empleado;
        $ruta = "documentos/{$empleado->id}/{$tipo}/{$tipo}_{$mes}.pdf";
        Storage::disk('local')->put($ruta, $this->pdfFirmado("{$tipo} {$mes}"));

        return Documento::create(['empleado_id' => $empleado->id, 'tipo' => $tipo, 'archivo' => $ruta, 'estado_registro' => 'activo',
            'estado_firma' => $estadoFirma, 'periodo_mes' => $mes, 'periodo_anio' => 2026, 'firma_colegio' => 'completa']);
    }

    /** Una boleta firmada por el colegio, ya con su conformidad. */
    private function boleta(int $mes): Documento
    {
        $planilla = Planilla::create(['empleado_id' => $this->empleado->id, 'mes' => $mes, 'anio' => 2026, 'sueldo_base' => 2000, 'total' => 1800]);
        $ruta = "documentos/{$this->empleado->id}/boletas/boleta_{$mes}_firmada.pdf";
        Storage::disk('local')->put($ruta, $this->pdfFirmado("boleta {$mes}"));

        return Documento::create(['empleado_id' => $this->empleado->id, 'planilla_id' => $planilla->id, 'tipo' => 'boleta', 'archivo' => $ruta,
            'estado_registro' => 'activo', 'estado_firma' => 'firmado', 'firma_colegio' => 'completa']);
    }

    private function juntar(array $ids, string $formato)
    {
        return $this->actingAs($this->usuario, 'sanctum')->post('/api/my-documents/bundle', ['ids' => array_map('strval', $ids), 'formato' => $formato], ['Accept' => 'application/json']);
    }

    public function test_el_zip_lleva_cada_una_firmada_y_dice_cuales_no_entraron(): void
    {
        $boleta = $this->boleta(10);
        $cts = $this->hoja('cts', 11);
        $sinConformidad = $this->hoja('gratificacion', 12, 'pendiente');
        $ajena = $this->hoja('cts', 11, 'firmado', $this->crearEmpleado());

        $respuesta = $this->juntar([$boleta->id, $cts->id, $sinConformidad->id, $ajena->id], 'zip')->assertOk();

        $this->assertSame('2', $respuesta->headers->get('X-No-Incluidas'));
        $zip = new \ZipArchive();
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $nombres = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->all();
        $this->assertContains('2026-11 Hoja de CTS.pdf', $nombres);
        $this->assertCount(1, array_filter($nombres, fn ($n) => str_starts_with($n, '2026-10 Boleta')));
        $this->assertContains('NO_INCLUIDAS.txt', $nombres);
        // La firma digital viaja intacta en el .zip.
        $this->assertSame(Storage::disk('local')->get($cts->archivo), $zip->getFromName('2026-11 Hoja de CTS.pdf'));
        $aviso = $zip->getFromName('NO_INCLUIDAS.txt');
        $this->assertStringContainsString('Hoja de gratificación', $aviso);
        $this->assertStringContainsString('1 documento(s) que no se encontraron', $aviso);
        $this->assertStringNotContainsString((string) $ajena->id, $aviso);
        $zip->close();

        // Queda anotado que se las llevó.
        $this->assertNotNull($cts->refresh()->fecha_descarga);
        $this->assertNotNull($boleta->refresh()->fecha_descarga);
    }

    public function test_un_solo_pdf_junta_las_paginas_aunque_vengan_firmadas(): void
    {
        $ids = [$this->boleta(9)->id, $this->boleta(10)->id, $this->hoja('cts', 11)->id];

        $respuesta = $this->juntar($ids, 'pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $pdf = $respuesta->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(3, preg_match_all('~/Type\s*/Page[^s]~', $pdf));
        $this->assertSame('0', $respuesta->headers->get('X-No-Incluidas'));
    }

    public function test_sin_conformidad_ni_ajenas_no_baja_nada(): void
    {
        $pendiente = $this->hoja('cts', 11, 'pendiente');
        $ajena = $this->hoja('cts', 5, 'firmado', $this->crearEmpleado());

        $this->juntar([$pendiente->id, $ajena->id], 'zip')->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Ninguno se puede bajar'));
        $this->juntar([$pendiente->id], 'patata')->assertStatus(422)->assertJsonValidationErrors('formato');
        $this->assertNull($ajena->refresh()->fecha_descarga);
    }

    public function test_sin_sesion_no_hay_nada(): void
    {
        $this->postJson('/api/my-documents/bundle', ['ids' => [(string) $this->hoja('cts', 11)->id], 'formato' => 'zip'])->assertUnauthorized();
    }
}
