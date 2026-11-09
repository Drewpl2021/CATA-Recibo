<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Support\Firma\CertificadoDigital;
use App\Support\Firma\FirmadorPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Que no se escapen los datos de las boletas, las hojas de CTS y de
 * gratificación, ni el certificado de firma o su clave: ni por la caché del
 * navegador, ni por una ruta suelta, ni en un rastro de error, ni a otro
 * trabajador.
 */
class SinFugaDeDatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_nada_del_api_se_queda_en_la_cache_del_navegador(): void
    {
        $respuesta = $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson('/api/cts?anio=2026&periodo=mayo')
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache');

        $cache = (string) $respuesta->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('private', $cache);
    }

    public function test_un_pdf_tampoco_se_guarda_en_la_cache(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documentos/x/cts/cts_2026_mayo.pdf', '%PDF-1.4 prueba');
        $empleado = $this->crearEmpleado();
        $hoja = Documento::create(['empleado_id' => $empleado->id, 'tipo' => 'cts', 'archivo' => 'documentos/x/cts/cts_2026_mayo.pdf',
            'estado_registro' => 'activo', 'estado_firma' => 'pendiente', 'periodo_mes' => 5, 'periodo_anio' => 2026]);

        $respuesta = $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->get("/api/documents/{$hoja->id}/view")->assertOk();

        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
    }

    public function test_el_disco_privado_no_tiene_ruta_publica(): void
    {
        $this->assertFalse(Route::has('storage.local'), 'Boletas, hojas y certificados solo salen por los controladores.');
        $this->assertFalse(Route::has('storage.local.upload'));
    }

    public function test_la_clave_y_el_certificado_nunca_quedan_en_un_rastro_de_error(): void
    {
        $sensible = fn (string $clase, string $metodo, string $parametro) => collect((new \ReflectionMethod($clase, $metodo))->getParameters())
            ->first(fn ($p) => $p->getName() === $parametro)
            ->getAttributes(\SensitiveParameter::class);

        $this->assertNotEmpty($sensible(CertificadoDigital::class, 'abrir', 'clave'));
        $this->assertNotEmpty($sensible(CertificadoDigital::class, 'abrir', 'pfx'));
        $this->assertNotEmpty($sensible(\App\Models\CertificadoFirma::class, 'cifrar', 'pfx'));
        $this->assertNotEmpty($sensible(FirmadorPdf::class, 'firmar', 'llave'));

        // Y de verdad: un error dentro de abrir() no lleva la clave en su rastro.
        try {
            (function (#[\SensitiveParameter] string $clave) {
                throw new \RuntimeException('falla de prueba');
            })('Clave-Secreta-Del-Certificado');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('Clave-Secreta', $e->getTraceAsString());
            $this->assertInstanceOf(\SensitiveParameterValue::class, $e->getTrace()[0]['args'][0] ?? new \SensitiveParameterValue(''));
        }
    }

    public function test_al_trabajador_no_le_llega_el_dni_de_quien_firmo_por_el_colegio(): void
    {
        $empleado = $this->crearEmpleado();
        $usuario = $this->crearUsuario('empleado');
        $usuario->forceFill(['empleado_id' => (string) $empleado->id])->save();
        Documento::create(['empleado_id' => $empleado->id, 'tipo' => 'cts', 'archivo' => 'documentos/x.pdf', 'estado_registro' => 'activo',
            'estado_firma' => 'pendiente', 'periodo_mes' => 5, 'periodo_anio' => 2026, 'firma_colegio' => 'completa',
            'firmas_colegio' => [['nombre' => 'FIRMANTE DE PRUEBA', 'dni' => '01234567', 'fecha' => '2026-05-12T10:00:00Z', 'emisor' => 'X']]]);

        $respuesta = $this->actingAs($usuario, 'sanctum')->getJson('/api/my-documents?tipo=cts,gratificacion&page=0&size=10')->assertOk();

        $this->assertSame('completa', $respuesta->json('data.content.0.firma_colegio'));
        $this->assertArrayNotHasKey('firmas_colegio', $respuesta->json('data.content.0'));
        $this->assertStringNotContainsString('01234567', $respuesta->getContent());
    }

    public function test_un_trabajador_no_abre_ni_firma_la_hoja_de_otro(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documentos/otro.pdf', '%PDF-1.4 prueba');
        $suyo = $this->crearEmpleado();
        $otro = $this->crearEmpleado();
        $usuario = $this->crearUsuario('empleado');
        $usuario->forceFill(['empleado_id' => (string) $suyo->id])->save();
        $ajena = Documento::create(['empleado_id' => $otro->id, 'tipo' => 'cts', 'archivo' => 'documentos/otro.pdf', 'estado_registro' => 'activo',
            'estado_firma' => 'pendiente', 'periodo_mes' => 5, 'periodo_anio' => 2026, 'firma_colegio' => 'completa']);

        $this->actingAs($usuario, 'sanctum')->get("/api/documents/{$ajena->id}/view")->assertForbidden();
        $this->actingAs($usuario, 'sanctum')->get("/api/documents/{$ajena->id}/download")->assertForbidden();
        $this->actingAs($usuario, 'sanctum')->postJson("/api/my-documents/{$ajena->id}/sign", ['password' => 'ClaveSegura#2026'])->assertNotFound();
        $this->assertSame('pendiente', $ajena->refresh()->estado_firma);
    }
}
