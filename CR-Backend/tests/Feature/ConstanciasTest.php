<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\Planilla;
use App\Support\Documentos\Constancias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FirmadorPdfDePrueba;
use Tests\TestCase;

/**
 * El trabajador pide su constancia de trabajo o de haberes desde Mis
 * Documentos: se arma al momento, RR.HH. la firma con «Firmar aquí» y le
 * llega a él, sin pedirle conformidad.
 */
class ConstanciasTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-del-Certificado-2026';

    private $empleado;
    private $usuario;
    private $rrhh;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->empleado = $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01']);
        $this->usuario = $this->crearUsuario('empleado');
        $this->usuario->forceFill(['empleado_id' => (string) $this->empleado->id])->save();
        $this->rrhh = $this->crearUsuario('rrhh');
    }

    private function pedir(array $datos)
    {
        return $this->actingAs($this->usuario, 'sanctum')->postJson('/api/my-documents/letters', $datos);
    }

    public function test_pide_su_constancia_de_trabajo_y_rrhh_la_firma(): void
    {
        $r = $this->pedir(['tipo' => 'constancia_trabajo', 'dirigido_a' => 'Cooperativa San Pedro'])->assertCreated()->assertJsonPath('data.nueva', true);
        $doc = Documento::findOrFail($r->json('data.id'));
        $this->assertSame('constancia_trabajo', $doc->tipo);
        $this->assertSame('pendiente', $doc->firma_colegio);
        $this->assertTrue(Storage::disk('local')->exists($doc->archivo));
        // A RR.HH. le llega el aviso en su campanita.
        $this->assertTrue(Notificacion::where('user_id', $this->rrhh->id)->where('tipo', 'constancia_pedida')->exists());

        // Pedirla otra vez mientras espera no arma otra.
        $this->pedir(['tipo' => 'constancia_trabajo'])->assertOk()->assertJsonPath('data.nueva', false)->assertJsonPath('data.id', (string) $doc->id);
        $this->assertSame(1, Documento::where('tipo', 'constancia_trabajo')->count());

        // Sin la firma del colegio, él todavía no la ve.
        $tipos = fn () => collect($this->actingAs($this->usuario, 'sanctum')->getJson('/api/my-documents?page=0&size=50')->json('data.content'))->pluck('tipo')->all();
        $this->assertNotContains('constancia_trabajo', $tipos());

        $lista = $this->actingAs($this->rrhh, 'sanctum')->getJson('/api/letters/pending')->assertOk();
        $this->assertSame(1, $lista->json('data.firma.por_firmar'));
        $this->assertSame('Constancia de trabajo', $lista->json('data.filas.0.tipo_texto'));

        $pfx = FirmadorPdfDePrueba::pfx('Isidoro', 'Rodriguez Mamani', '01234567', self::CLAVE);
        $this->actingAs($this->rrhh, 'sanctum')->post('/api/my-signing-certificate', [
            'archivo' => UploadedFile::fake()->createWithContent('i.pfx', $pfx), 'clave' => self::CLAVE, 'confirmar' => '1',
        ], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($this->rrhh, 'sanctum')->postJson('/api/letters/sign-here', ['clave' => self::CLAVE])
            ->assertOk()->assertJsonPath('data.entregadas', 1);

        $this->assertSame('completa', $doc->refresh()->firma_colegio);
        $this->assertContains('constancia_trabajo', $tipos());
        // Se la descarga sin dar conformidad: la constancia no la pide.
        $this->actingAs($this->usuario, 'sanctum')->get("/api/documents/{$doc->id}/download")->assertOk();
        $this->assertTrue(Notificacion::where('user_id', $this->usuario->id)->where('tipo', 'constancia_lista')->exists());
        Mail::assertQueued(\App\Mail\HojaLista::class, fn ($m) => $m->hasTo($this->usuario->email) && ! $m->conConformidad
            && str_ends_with($m->enlace, '/inicio/mis-documentos'));
    }

    public function test_la_constancia_de_haberes_toma_sus_ultimos_meses_pagados(): void
    {
        $esteMes = now();
        foreach ([3, 2, 1, 0] as $atras) {
            $f = $esteMes->copy()->subMonthsNoOverflow($atras);
            Planilla::create(['empleado_id' => $this->empleado->id, 'mes' => $f->month, 'anio' => $f->year, 'sueldo_base' => 3000, 'total' => 2600]);
        }

        $meses = Constancias::haberes($this->empleado, 3);

        // Los tres anteriores al mes en curso, del más antiguo al más reciente.
        $this->assertCount(3, $meses);
        $esperado = $esteMes->copy()->subMonthsNoOverflow(3);
        $this->assertSame([$esperado->month, $esperado->year], [$meses[0]['mes'], $meses[0]['anio']]);
        $this->assertEqualsWithDelta(2600, $meses[2]['neto'], 0.001);

        $this->pedir(['tipo' => 'constancia_haberes', 'meses' => 3])->assertCreated();
        $this->assertSame(1, Documento::where('tipo', 'constancia_haberes')->count());
    }

    public function test_sin_meses_pagados_no_hay_constancia_de_haberes_y_se_validan_los_datos(): void
    {
        $this->pedir(['tipo' => 'constancia_haberes', 'meses' => 3])->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'meses pagados'));
        $this->pedir(['tipo' => 'otra_cosa'])->assertStatus(422)->assertJsonValidationErrors('tipo');
        $this->pedir(['tipo' => 'constancia_haberes', 'meses' => 7])->assertStatus(422)->assertJsonValidationErrors('meses');
        $this->pedir(['tipo' => 'constancia_trabajo', 'dirigido_a' => '<script>x</script>'])->assertStatus(422)->assertJsonValidationErrors('dirigido_a');
    }

    public function test_rrhh_anula_una_antes_de_firmarla_y_un_trabajador_no_entra_a_la_bandeja(): void
    {
        $id = $this->pedir(['tipo' => 'constancia_trabajo'])->json('data.id');

        $this->actingAs($this->usuario, 'sanctum')->getJson('/api/letters/pending')->assertForbidden();
        $this->actingAs($this->usuario, 'sanctum')->deleteJson("/api/letters/{$id}")->assertForbidden();

        $this->actingAs($this->rrhh, 'sanctum')->deleteJson("/api/letters/{$id}")->assertOk();
        $this->assertNull(Documento::find($id));
    }
}
