<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\PaymentConcept;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use App\Models\PlanillaCorrida;
use App\Support\ConceptosDePago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cambiar los conceptos de una planilla cuya boleta ya salió.
 *
 *   - Boleta firmada o planilla cerrada: no se puede, por ningún camino.
 *   - Boleta emitida sin firmar: se puede, y su PDF se rehace solo, para
 *     que la boleta nunca diga otra cosa que la planilla.
 */
class BoletaEmitidaYSusConceptosTest extends TestCase
{
    use RefreshDatabase;

    private $rrhh;
    private $empleado;
    private Planilla $planilla;
    private PaymentConcept $bono;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

        $this->rrhh = $this->crearUsuario('rrhh');
        $this->empleado = $this->crearEmpleado(['sueldo_base' => 2000]);
        $this->planilla = Planilla::create([
            'empleado_id' => $this->empleado->id, 'mes' => (int) now()->month, 'anio' => (int) now()->year,
            'sueldo_base' => 2000, 'total' => 2000,
        ]);
        $this->bono = PaymentConcept::create(['nombre' => 'Bono de prueba', 'tipo' => 'bonificacion']);
    }

    /** RR.HH. emite la boleta del mes; devuelve su documento. */
    private function emitir(): Documento
    {
        $this->actingAs($this->rrhh, 'sanctum')
            ->get("/api/payslips/{$this->empleado->id}/{$this->planilla->mes}/{$this->planilla->anio}")
            ->assertOk();

        return Documento::where('planilla_id', $this->planilla->id)->where('tipo', 'boleta')->firstOrFail();
    }

    private function pdf(Documento $boleta): string
    {
        return md5(Storage::disk('local')->get($boleta->archivo));
    }

    public function test_con_la_boleta_firmada_no_se_tocan_los_conceptos_por_ningun_camino(): void
    {
        $linea = PayrollDetalle::create(['planilla_id' => $this->planilla->id, 'payment_concept_id' => $this->bono->id, 'monto_calculado' => 100]);
        $boleta = $this->emitir();
        $boleta->update(['estado_firma' => 'firmado']);
        $antes = $this->pdf($boleta);

        $api = $this->actingAs($this->rrhh, 'sanctum');
        $otro = PaymentConcept::create(['nombre' => 'Otro bono', 'tipo' => 'bonificacion']);

        $api->postJson('/api/payroll-details', ['planilla_id' => $this->planilla->id, 'payment_concept_id' => $otro->id, 'monto_calculado' => 50])
            ->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'firmada'));
        $api->putJson("/api/payroll-details/{$linea->id}", ['monto_calculado' => 999])->assertStatus(409);
        $api->deleteJson("/api/payroll-details/{$linea->id}")->assertStatus(409);
        $api->postJson("/api/payrolls/{$this->planilla->id}/concepts", ['conceptos' => [['nombre' => 'Otro bono', 'monto' => 50]]])->assertStatus(409);
        $api->putJson("/api/payrolls/{$this->planilla->id}/recalcular")->assertStatus(422);

        // Aplicar a varios: a él se lo salta, diciendo por qué.
        $aplicar = $api->postJson("/api/payment-concepts/{$otro->id}/apply-to-group", [
            'mes' => $this->planilla->mes, 'anio' => $this->planilla->anio, 'empleado_ids' => [$this->empleado->id], 'calculo' => 'fijo', 'valor' => 50,
        ])->assertOk();
        $this->assertSame(0, $aplicar->json('data.resumen.aplicadas'));
        $this->assertStringContainsString('firmada', $aplicar->json('data.detalle.0.motivo'));

        // Nada cambió: ni las líneas, ni el PDF firmado.
        $this->assertSame(1, PayrollDetalle::where('planilla_id', $this->planilla->id)->where('payment_concept_id', '!=', null)
            ->whereIn('payment_concept_id', [$this->bono->id, $otro->id])->count());
        $this->assertEquals(100, $linea->fresh()->monto_calculado);
        $this->assertSame($antes, $this->pdf($boleta));
    }

    public function test_abrir_la_boleta_firmada_no_le_reescribe_la_renta_de_5ta(): void
    {
        $quinta = PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::RENTA_5TA], ['tipo' => 'descuento']);
        $boleta = $this->emitir();
        // Lo que se le retuvo y él firmó (aunque hoy el cálculo diría otra cosa).
        PayrollDetalle::updateOrCreate(['planilla_id' => $this->planilla->id, 'payment_concept_id' => $quinta->id], ['monto_calculado' => 123.45]);
        $boleta->update(['estado_firma' => 'firmado']);

        $this->emitir();

        $this->assertEquals(123.45, PayrollDetalle::where('planilla_id', $this->planilla->id)->where('payment_concept_id', $quinta->id)->value('monto_calculado'));
    }

    public function test_con_la_boleta_emitida_sin_firmar_se_puede_y_su_pdf_se_rehace(): void
    {
        $boleta = $this->emitir();
        $antes = $this->pdf($boleta);

        $nueva = $this->actingAs($this->rrhh, 'sanctum')
            ->postJson('/api/payroll-details', ['planilla_id' => $this->planilla->id, 'payment_concept_id' => $this->bono->id, 'monto_calculado' => 150])
            ->assertCreated()
            ->assertJsonPath('boleta_rehecha', true);

        // El mismo documento, sin firmar, con el PDF nuevo.
        $this->assertSame(1, Documento::where('planilla_id', $this->planilla->id)->where('tipo', 'boleta')->count());
        $this->assertSame('pendiente', $boleta->fresh()->estado_firma);
        $despuesDeAgregar = $this->pdf($boleta);
        $this->assertNotSame($antes, $despuesDeAgregar);

        // Cambiar el monto y quitarlo también lo rehacen.
        $id = $nueva->json('data.id');
        $this->actingAs($this->rrhh, 'sanctum')->putJson("/api/payroll-details/{$id}", ['monto_calculado' => 175])
            ->assertOk()->assertJsonPath('boleta_rehecha', true);
        $this->assertNotSame($despuesDeAgregar, $this->pdf($boleta));
        $this->actingAs($this->rrhh, 'sanctum')->deleteJson("/api/payroll-details/{$id}")
            ->assertOk()->assertJsonPath('boleta_rehecha', true);
    }

    public function test_sin_boleta_emitida_todo_sigue_como_siempre(): void
    {
        $this->actingAs($this->rrhh, 'sanctum')
            ->postJson('/api/payroll-details', ['planilla_id' => $this->planilla->id, 'payment_concept_id' => $this->bono->id, 'monto_calculado' => 150])
            ->assertCreated()
            ->assertJsonPath('boleta_rehecha', false);

        $this->assertSame(0, Documento::where('planilla_id', $this->planilla->id)->count());
    }

    public function test_con_la_planilla_cerrada_no_se_tocan_los_conceptos(): void
    {
        $corrida = PlanillaCorrida::create(['nombre' => 'Docentes', 'mes' => $this->planilla->mes, 'anio' => $this->planilla->anio, 'estado' => 'cerrada']);
        $this->planilla->update(['corrida_id' => $corrida->id]);

        $this->actingAs($this->rrhh, 'sanctum')
            ->postJson('/api/payroll-details', ['planilla_id' => $this->planilla->id, 'payment_concept_id' => $this->bono->id, 'monto_calculado' => 150])
            ->assertStatus(409)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'cerrada'));

        // Aplicar a varios, eligiendo personas (sin elegir la planilla): también se la salta.
        $aplicar = $this->actingAs($this->rrhh, 'sanctum')->postJson("/api/payment-concepts/{$this->bono->id}/apply-to-group", [
            'mes' => $this->planilla->mes, 'anio' => $this->planilla->anio, 'empleado_ids' => [$this->empleado->id], 'calculo' => 'fijo', 'valor' => 50,
        ])->assertOk();
        $this->assertSame(0, $aplicar->json('data.resumen.aplicadas'));
    }
}
