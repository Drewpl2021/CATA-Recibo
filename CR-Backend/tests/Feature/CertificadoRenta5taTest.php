<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\RentaQuintaPrevia;
use App\Support\Renta5ta\MotorRenta5ta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FirmadorPdfDePrueba;
use Tests\TestCase;

/**
 * El Certificado de Rentas y Retenciones de 5ta: con lo que de verdad se
 * pagó y se retuvo en el año, el impuesto por tramos y el saldo; se emite
 * cuando el año terminó o al cese, se firma como las boletas y le llega al
 * trabajador.
 */
class CertificadoRenta5taTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-del-Certificado-2026';

    /** Un año entero cobrado: 12 meses de 6,000 con 300 retenidos cada uno. */
    private function conAnioCobrado(int $anio = 2025, array $datos = [])
    {
        $e = $this->crearEmpleado(['sueldo_base' => 6000, 'fecha_ingreso' => '2020-03-01', ...$datos]);
        foreach (range(1, 12) as $mes) {
            RentaQuintaPrevia::create(['empleado_id' => $e->id, 'anio' => $anio, 'mes' => $mes, 'remuneracion' => 6000, 'retencion' => 300]);
        }

        return $e;
    }

    public function test_suma_lo_pagado_y_retenido_y_calcula_el_impuesto_por_tramos(): void
    {
        $e = $this->conAnioCobrado();
        $motor = new MotorRenta5ta();

        $this->assertNull($motor->porQueNoHayCertificado($e, 2025));
        $c = $motor->certificado($e, 2025);

        $uit = $c['uit'];
        $neta = 72000 - 7 * $uit;
        $this->assertCount(12, $c['meses']);
        $this->assertEqualsWithDelta(72000, $c['renta_bruta'], 0.001);
        $this->assertEqualsWithDelta(7 * $uit, $c['deduccion'], 0.001);
        $this->assertEqualsWithDelta($neta, $c['renta_neta'], 0.001);
        $esperado = min($neta, 5 * $uit) * 0.08 + max(0, min($neta - 5 * $uit, 15 * $uit)) * 0.14;
        $this->assertEqualsWithDelta($esperado, $c['impuesto'], 0.01);
        $this->assertEqualsWithDelta($c['impuesto'], array_sum(array_column($c['tramos'], 'impuesto')), 0.02);
        $this->assertEqualsWithDelta(3600, $c['retenido'], 0.001);
        $this->assertEqualsWithDelta($c['impuesto'] - 3600, $c['saldo'], 0.001);
        $this->assertSame('2025-01-01', $c['desde']);
        $this->assertSame('2025-12-31', $c['hasta']);
    }

    public function test_se_emite_cuando_el_anio_termino_o_al_cese(): void
    {
        $motor = new MotorRenta5ta();
        $anio = (int) now()->year;

        $activo = $this->conAnioCobrado($anio);
        $this->assertStringContainsString('todavía no termina', $motor->porQueNoHayCertificado($activo, $anio));

        $cesado = $this->conAnioCobrado($anio, ['estado' => 'inactivo', 'fecha_cese' => "{$anio}-04-13"]);
        $this->assertNull($motor->porQueNoHayCertificado($cesado, $anio));
        $this->assertTrue($motor->certificado($cesado, $anio)['al_cese']);
        $this->assertSame("{$anio}-04-13", $motor->certificado($cesado, $anio)['hasta']);

        $sinNada = $this->crearEmpleado(['fecha_ingreso' => '2020-03-01']);
        $this->assertStringContainsString('No tiene remuneraciones', $motor->porQueNoHayCertificado($sinNada, 2025));
    }

    public function test_se_entrega_se_firma_y_le_llega_al_trabajador(): void
    {
        Storage::fake('local');
        Mail::fake();
        $e = $this->conAnioCobrado();
        $usuario = $this->crearUsuario('empleado');
        $usuario->forceFill(['empleado_id' => (string) $e->id])->save();
        $rrhh = $this->crearUsuario('rrhh');

        $lista = $this->actingAs($rrhh, 'sanctum')->getJson('/api/income-tax/certificates?anio=2025')->assertOk();
        $fila = collect($lista->json('data.filas'))->firstWhere('id', $e->id);
        $this->assertTrue($fila['puede']);
        $this->assertEqualsWithDelta(3600, $fila['retenido'], 0.001);

        // Al vuelo, antes de entregarlo.
        $this->actingAs($rrhh, 'sanctum')->get("/api/income-tax/{$e->id}/certificate?anio=2025")->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/income-tax/certificates/deliver', ['anio' => 2025, 'ids' => [$e->id]])
            ->assertOk()->assertJsonPath('data.entregados', 1);
        $doc = Documento::where('empleado_id', $e->id)->where('tipo', 'certificado_5ta')->firstOrFail();
        $this->assertSame('pendiente', $doc->firma_colegio);
        $this->assertSame(2025, $doc->periodo_anio);

        $pfx = FirmadorPdfDePrueba::pfx('Isidoro', 'Rodriguez Mamani', '01234567', self::CLAVE);
        $this->actingAs($rrhh, 'sanctum')->post('/api/my-signing-certificate', [
            'archivo' => UploadedFile::fake()->createWithContent('i.pfx', $pfx), 'clave' => self::CLAVE, 'confirmar' => '1',
        ], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/income-tax/certificates/sign-here', ['anio' => 2025, 'clave' => self::CLAVE])
            ->assertOk()->assertJsonPath('data.entregadas', 1);

        $this->assertSame('completa', $doc->refresh()->firma_colegio);
        Mail::assertQueued(\App\Mail\HojaLista::class, fn ($m) => $m->hasTo($usuario->email) && ! $m->femenino && $m->periodo === '2025');
        $tipos = collect($this->actingAs($usuario, 'sanctum')
            ->getJson('/api/my-documents?tipo=cts,gratificacion,certificado_5ta&page=0&size=20')->json('data.content'))->pluck('tipo');
        $this->assertContains('certificado_5ta', $tipos->all());

        // Entregarlo otra vez no rehace el firmado.
        $firmado = Storage::disk('local')->get($doc->archivo);
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/income-tax/certificates/deliver', ['anio' => 2025])->assertOk()->assertJsonPath('data.entregados', 0);
        $this->assertSame($firmado, Storage::disk('local')->get($doc->refresh()->archivo));
        // El que se baja es el firmado.
        $this->assertSame($firmado, $this->actingAs($rrhh, 'sanctum')->get("/api/income-tax/{$e->id}/certificate?anio=2025")->streamedContent());
    }

    public function test_no_se_entrega_si_todavia_no_corresponde_y_un_trabajador_no_entra(): void
    {
        $anio = (int) now()->year;
        $activo = $this->conAnioCobrado($anio);
        $rrhh = $this->crearUsuario('rrhh');

        $r = $this->actingAs($rrhh, 'sanctum')->postJson('/api/income-tax/certificates/deliver', ['anio' => $anio, 'ids' => [$activo->id]])->assertOk();
        $this->assertSame(0, $r->json('data.entregados'));
        $this->assertStringContainsString('todavía no termina', $r->json('data.saltados.0'));
        $this->actingAs($rrhh, 'sanctum')->getJson("/api/income-tax/{$activo->id}/certificate?anio={$anio}")->assertStatus(422);

        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')->getJson('/api/income-tax/certificates?anio=2025')->assertForbidden();
    }
}
