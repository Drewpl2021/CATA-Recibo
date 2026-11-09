<?php

namespace Tests\Feature;

use App\Models\CtsLiquidacion;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\PaymentConcept;
use App\Models\Planilla;
use App\Models\PayrollDetalle;
use App\Support\ConceptosDePago;
use App\Support\Firma\FirmaDigitalPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FirmadorPdfDePrueba;
use Tests\TestCase;

/**
 * La hoja de CTS y la de gratificación se firman digitalmente por el
 * colegio, como la boleta: salen «por firmar», el trabajador no las ve
 * hasta que están firmadas, y se firman con «Firmar aquí».
 */
class FirmaDeHojasTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-del-Certificado-2026';

    private $rrhh;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->rrhh = $this->crearUsuario('rrhh');
        PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::GRATIFICACION], ['tipo' => 'bonificacion']);
        PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::BONIF_EXTRAORDINARIA], ['tipo' => 'bonificacion']);
    }

    /** Un trabajador con su cuenta para entrar al sistema. */
    private function trabajadorConCuenta(array $datos = [])
    {
        $empleado = $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0,
            'cts_entidad_financiera' => 'Caja Arequipa', 'cts_numero_cuenta' => '105-0012345-6', ...$datos]);
        $usuario = $this->crearUsuario('empleado');
        $usuario->forceFill(['empleado_id' => (string) $empleado->id])->save();

        return [$empleado, $usuario];
    }

    private function ponerCertificado(): void
    {
        $pfx = FirmadorPdfDePrueba::pfx('Isidoro', 'Rodriguez Mamani', '01234567', self::CLAVE);
        $this->actingAs($this->rrhh, 'sanctum')->post('/api/my-signing-certificate', [
            'archivo'   => UploadedFile::fake()->createWithContent('isidoro.pfx', $pfx),
            'clave'     => self::CLAVE,
            'confirmar' => '1',
        ], ['Accept' => 'application/json'])->assertOk();
    }

    private function susDocumentos($usuario): array
    {
        return collect($this->actingAs($usuario, 'sanctum')->getJson('/api/my-documents?page=0&size=50')->assertOk()->json('data.content') ?? [])
            ->pluck('tipo')->all();
    }

    private function depositarCts($empleado): CtsLiquidacion
    {
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'mayo'])->assertOk();
        $l = CtsLiquidacion::where('empleado_id', $empleado->id)->firstOrFail();
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/cts/deposit', ['ids' => [$l->id], 'fecha_deposito' => '2026-05-12'])->assertOk();

        return $l->refresh();
    }

    public function test_la_hoja_de_cts_espera_la_firma_del_colegio_y_llega_al_firmarla(): void
    {
        [$empleado, $usuario] = $this->trabajadorConCuenta();
        $l = $this->depositarCts($empleado);

        $hoja = Documento::findOrFail($l->documento_id);
        $this->assertSame('pendiente', $hoja->firma_colegio);
        $this->assertNotContains('cts', $this->susDocumentos($usuario), 'Sin la firma del colegio todavía no es suya.');

        $lista = $this->actingAs($this->rrhh, 'sanctum')->getJson('/api/cts?anio=2026&periodo=mayo')->assertOk();
        $this->assertSame(1, $lista->json('data.firma.por_firmar'));
        $this->assertNull($lista->json('data.firma.mi_certificado'));
        $this->assertSame('pendiente', collect($lista->json('data.filas'))->firstWhere('id', $empleado->id)['firma_colegio']);

        $this->ponerCertificado();
        $this->assertSame(1, $this->actingAs($this->rrhh, 'sanctum')->getJson('/api/cts?anio=2026&periodo=mayo')->json('data.firma.mi_certificado.por_firmar'));

        // El emitido, antes de firmar: el firmado lo tiene que llevar adentro intacto.
        $rutaEmitido = $hoja->refresh()->archivo;
        $emitido = Storage::disk('local')->get($rutaEmitido);

        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/cts/sign-here', ['anio' => 2026, 'periodo' => 'mayo', 'clave' => 'otra-clave'])
            ->assertStatus(422);
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/cts/sign-here', ['anio' => 2026, 'periodo' => 'mayo', 'clave' => self::CLAVE])
            ->assertOk()->assertJsonPath('data.firmadas', 1)->assertJsonPath('data.entregadas', 1)->assertJsonPath('data.siguiente', null);

        $hoja->refresh();
        $this->assertSame('completa', $hoja->firma_colegio);
        $this->assertSame('ISIDORO RODRIGUEZ MAMANI', $hoja->firmas_colegio[0]['nombre']);
        $firmas = FirmaDigitalPdf::firmas(Storage::disk('local')->get($hoja->archivo));
        $this->assertCount(1, $firmas);
        $this->assertTrue($firmas[0]['valida']);
        // Con todas las firmas, la copia sin firmar sobra y se borra: el firmado
        // es el emitido con la firma al final.
        $this->assertNull($hoja->archivo_sin_firma);
        $this->assertFalse(Storage::disk('local')->exists($rutaEmitido));
        $this->assertStringStartsWith($emitido, Storage::disk('local')->get($hoja->archivo));

        $this->assertContains('cts', $this->susDocumentos($usuario));
        $this->assertTrue(Notificacion::where('user_id', $usuario->id)->where('documento_id', $hoja->id)->exists());
        // Y por correo, como la boleta.
        Mail::assertQueued(\App\Mail\HojaLista::class, fn ($m) => $m->hasTo($usuario->email)
            && $m->queEs === 'hoja de CTS' && $m->periodo === 'mayo 2026');
        $this->assertSame($usuario->email, $hoja->aviso_correo);
        $this->assertNotNull($hoja->fecha_aviso);

        // Ya no queda ninguna: otra pasada lo dice.
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/cts/sign-here', ['anio' => 2026, 'periodo' => 'mayo', 'clave' => self::CLAVE])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'esperando tu firma'));
    }

    public function test_la_hoja_llega_a_mis_boletas_y_da_su_conformidad_solo_despues_de_la_firma_del_colegio(): void
    {
        [$empleado, $usuario] = $this->trabajadorConCuenta();
        $l = $this->depositarCts($empleado);
        $hojas = fn () => collect($this->actingAs($usuario, 'sanctum')
            ->getJson('/api/my-documents?tipo=cts,gratificacion&anio=2026&page=0&size=20')->assertOk()->json('data.content'));

        // Sin la firma del colegio: ni la ve ni puede dar su conformidad.
        $this->assertCount(0, $hojas());
        $this->actingAs($usuario, 'sanctum')->postJson("/api/my-documents/{$l->documento_id}/sign", ['password' => 'ClaveSegura#2026'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'hoja de CTS todavía está en firma del colegio'));

        $this->ponerCertificado();
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/cts/sign-here', ['anio' => 2026, 'periodo' => 'mayo', 'clave' => self::CLAVE])->assertOk();

        $this->assertSame(['cts'], $hojas()->pluck('tipo')->all());
        $this->actingAs($usuario, 'sanctum')->postJson("/api/my-documents/{$l->documento_id}/sign", ['password' => 'ClaveSegura#2026'])->assertOk();
        $hoja = Documento::findOrFail($l->documento_id);
        $this->assertSame('firmado', $hoja->estado_firma);
        // Su conformidad no toca el PDF firmado por el colegio.
        $this->assertCount(1, FirmaDigitalPdf::firmas(Storage::disk('local')->get($hoja->archivo)));
    }

    public function test_el_comando_borra_las_copias_sin_firmar_que_quedaron_de_antes(): void
    {
        $empleado = $this->crearEmpleado();
        Storage::disk('local')->put('documentos/a/cts.pdf', '%PDF emitido');
        Storage::disk('local')->put('documentos/a/cts_firmada.pdf', '%PDF emitido + firma');
        Storage::disk('local')->put('documentos/b/cts.pdf', '%PDF emitido');
        Storage::disk('local')->put('documentos/b/cts_firmada.pdf', '%PDF emitido + una firma');
        $completa = Documento::create(['empleado_id' => $empleado->id, 'tipo' => 'cts', 'archivo' => 'documentos/a/cts_firmada.pdf',
            'archivo_sin_firma' => 'documentos/a/cts.pdf', 'firma_colegio' => 'completa', 'estado_registro' => 'activo', 'estado_firma' => 'pendiente']);
        $aMedias = Documento::create(['empleado_id' => $empleado->id, 'tipo' => 'cts', 'archivo' => 'documentos/b/cts_firmada.pdf',
            'archivo_sin_firma' => 'documentos/b/cts.pdf', 'firma_colegio' => 'parcial', 'estado_registro' => 'activo', 'estado_firma' => 'pendiente']);

        $this->artisan('documentos:quitar-copias-sin-firma')->expectsOutputToContain('1 copia(s)')->assertSuccessful();

        $this->assertFalse(Storage::disk('local')->exists('documentos/a/cts.pdf'));
        $this->assertNull($completa->refresh()->archivo_sin_firma);
        // La que espera otra firma la necesita para comparar la próxima subida.
        $this->assertTrue(Storage::disk('local')->exists('documentos/b/cts.pdf'));
        $this->assertSame('documentos/b/cts.pdf', $aMedias->refresh()->archivo_sin_firma);
        $this->artisan('documentos:quitar-copias-sin-firma')->expectsOutputToContain('No había')->assertSuccessful();
    }

    public function test_no_se_anula_el_deposito_si_el_trabajador_ya_abrio_su_hoja(): void
    {
        [$empleado] = $this->trabajadorConCuenta();
        $l = $this->depositarCts($empleado);
        Documento::whereKey($l->documento_id)->update(['estado_firma' => 'visto']);

        $this->actingAs($this->rrhh, 'sanctum')->deleteJson("/api/cts/{$l->id}/deposit")
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'ya abrió'));
        $this->assertTrue($l->refresh()->depositada());
    }

    public function test_la_hoja_de_gratificacion_se_firma_y_no_se_vuelve_a_generar(): void
    {
        [$empleado, $usuario] = $this->trabajadorConCuenta();
        $planilla = Planilla::create(['empleado_id' => $empleado->id, 'mes' => 7, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 0]);
        foreach ([ConceptosDePago::GRATIFICACION => 3000, ConceptosDePago::BONIF_EXTRAORDINARIA => 270] as $nombre => $monto) {
            PayrollDetalle::create(['planilla_id' => $planilla->id, 'payment_concept_id' => PaymentConcept::where('nombre', $nombre)->value('id'), 'monto_calculado' => $monto]);
        }
        $entregar = fn () => $this->actingAs($this->rrhh, 'sanctum')
            ->postJson('/api/bonuses/deliver', ['anio' => 2026, 'periodo' => 'julio', 'ids' => [$empleado->id]])->assertOk();

        $entregar()->assertJsonPath('data.entregadas', 1);
        $hoja = Documento::where('empleado_id', $empleado->id)->where('tipo', 'gratificacion')->firstOrFail();
        $this->assertSame('pendiente', $hoja->firma_colegio);
        $this->assertNotContains('gratificacion', $this->susDocumentos($usuario));

        $this->ponerCertificado();
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/bonuses/sign-here', ['anio' => 2026, 'periodo' => 'julio', 'clave' => self::CLAVE])
            ->assertOk()->assertJsonPath('data.entregadas', 1);
        $hoja->refresh();
        $this->assertSame('completa', $hoja->firma_colegio);
        $this->assertContains('gratificacion', $this->susDocumentos($usuario));

        // Entregarla otra vez no rehace la firmada.
        $firmado = Storage::disk('local')->get($hoja->archivo);
        $entregar()->assertJsonPath('data.entregadas', 0);
        $this->assertSame('completa', $hoja->refresh()->firma_colegio);
        $this->assertSame($firmado, Storage::disk('local')->get($hoja->archivo));

        // La hoja que se baja es la firmada del expediente.
        $bajada = $this->actingAs($this->rrhh, 'sanctum')->get("/api/bonuses/{$empleado->id}/sheet?anio=2026&periodo=julio")->assertOk();
        $this->assertSame($firmado, $bajada->streamedContent());

        $lista = $this->actingAs($this->rrhh, 'sanctum')->getJson('/api/bonuses?anio=2026&periodo=julio')->assertOk();
        $this->assertSame(1, $lista->json('data.firma.entregadas'));
        $this->assertSame('completa', collect($lista->json('data.filas'))->firstWhere('id', $empleado->id)['firma_colegio']);
    }

    public function test_un_trabajador_no_puede_firmar_hojas(): void
    {
        [, $usuario] = $this->trabajadorConCuenta();

        $this->actingAs($usuario, 'sanctum')->postJson('/api/cts/sign-here', ['anio' => 2026, 'periodo' => 'mayo', 'clave' => 'x'])->assertForbidden();
        $this->actingAs($usuario, 'sanctum')->postJson('/api/bonuses/sign-here', ['anio' => 2026, 'periodo' => 'julio', 'clave' => 'x'])->assertForbidden();
    }
}
