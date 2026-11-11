<?php

namespace Tests\Feature;

use App\Mail\CambioDeCuenta;
use App\Models\CtsInasistencia;
use App\Models\CtsLiquidacion;
use App\Models\Notificacion;
use App\Models\Planilla;
use App\Models\Solicitud;
use App\Models\Vacacion;
use App\Support\Cts\MotorCts;
use App\Support\Solicitudes\DiasDeLicencia;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * «Mis solicitudes»: el trabajador pide, RR.HH. aprueba o rechaza, y lo
 * aprobado llega solo a la planilla y a la CTS — sin tocar lo ya pagado.
 */
class SolicitudesTest extends TestCase
{
    use RefreshDatabase;

    private $empleado;
    private $usuario;
    private $rrhh;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 10:00:00');
        Storage::fake('local');
        Mail::fake();
        $this->empleado = $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01']);
        $this->usuario = $this->crearUsuario('empleado', ['password' => bcrypt('Su-Clave-2026')]);
        $this->usuario->forceFill(['empleado_id' => (string) $this->empleado->id])->save();
        $this->rrhh = $this->crearUsuario('rrhh');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pedir(array $datos)
    {
        return $this->actingAs($this->usuario, 'sanctum')->post('/api/my-requests', $datos, ['Accept' => 'application/json']);
    }

    private function comoRrhh()
    {
        return $this->actingAs($this->rrhh, 'sanctum');
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('sustento.pdf', 40, 'application/pdf');
    }

    public function test_licencia_sin_goce_descuenta_la_planilla_y_la_cts_y_al_anularla_vuelve(): void
    {
        // Planilla de noviembre ya generada (sin cerrar) y CTS de noviembre calculada.
        $planilla = Planilla::create(['empleado_id' => $this->empleado->id, 'mes' => 11, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 3000]);
        $this->comoRrhh()->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'noviembre'])->assertOk();
        $cts = CtsLiquidacion::where('empleado_id', $this->empleado->id)->firstOrFail();
        $this->assertSame([6, 0], [$cts->meses, $cts->dias]);

        // Del lunes 26 de octubre al viernes 20 de noviembre: toca dos meses y dos semestres.
        $id = $this->pedir(['tipo' => 'licencia', 'con_goce' => '0', 'fecha_inicio' => '2026-10-26', 'fecha_fin' => '2026-11-20', 'motivo' => 'Asuntos familiares'])
            ->assertCreated()->assertJsonPath('data.tipo_texto', 'Licencia sin goce')->json('data.id');
        $this->assertTrue(Notificacion::where('user_id', $this->rrhh->id)->where('tipo', 'solicitud_nueva')->exists());

        // Pendiente no cuenta para nada.
        $this->assertSame(0, DiasDeLicencia::calendarioSinGoce($this->empleado->id, Carbon::parse('2026-10-01'), Carbon::parse('2026-11-30')));

        $previa = $this->comoRrhh()->getJson("/api/requests/{$id}/preview")->assertOk()->json('data.avisos');
        $this->assertTrue(collect($previa)->contains(fn ($a) => str_contains($a, 'se recalcula sola')));

        $this->comoRrhh()->postJson("/api/requests/{$id}/approve")->assertOk()->assertJsonPath('data.estado', 'aprobada');

        // Noviembre 2026 tiene 21 días hábiles; la licencia toma 15 (del 2 al 20).
        $this->assertEqualsWithDelta(round(3000 * 6 / 21, 2), (float) $planilla->refresh()->sueldo_base, 0.01);
        // La CTS de noviembre pierde del 26 al 31 de octubre: 6 días.
        $cts->refresh();
        $this->assertSame(6, (int) $cts->dias_licencia);
        $this->assertSame([5, 24], [$cts->meses, $cts->dias]);
        $this->assertTrue(Notificacion::where('user_id', $this->usuario->id)->where('tipo', 'solicitud_resuelta')->exists());

        // RR.HH. la anula: todo vuelve.
        $this->comoRrhh()->postJson("/api/requests/{$id}/annul", ['respuesta' => 'Se registró por error'])->assertOk();
        $this->assertEqualsWithDelta(3000, (float) $planilla->refresh()->sueldo_base, 0.01);
        $this->assertSame([6, 0, 0], [$cts->refresh()->meses, $cts->dias, (int) $cts->dias_licencia]);
    }

    public function test_lo_ya_pagado_no_se_toca_y_se_avisa(): void
    {
        $this->comoRrhh()->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'noviembre'])->assertOk();
        $cts = CtsLiquidacion::where('empleado_id', $this->empleado->id)->firstOrFail();
        $this->empleado->forceFill(['cts_entidad_financiera' => 'Caja Arequipa', 'cts_numero_cuenta' => '123456789'])->save();
        $this->comoRrhh()->postJson('/api/cts/deposit', ['ids' => [$cts->id], 'fecha_deposito' => '2026-11-09'])->assertOk();
        $monto = (float) $cts->refresh()->monto;

        $id = $this->pedir(['tipo' => 'licencia', 'con_goce' => '0', 'fecha_inicio' => '2026-10-26', 'fecha_fin' => '2026-10-30', 'motivo' => 'Viaje familiar'])->json('data.id');
        $avisos = $this->comoRrhh()->getJson("/api/requests/{$id}/preview")->json('data.avisos');
        $this->assertTrue(collect($avisos)->contains(fn ($a) => str_contains($a, 'ya está depositada')));

        $this->comoRrhh()->postJson("/api/requests/{$id}/approve")->assertOk();
        $this->assertEqualsWithDelta($monto, (float) $cts->refresh()->monto, 0.001);
        $this->assertSame('depositada', Solicitud::find($id)->efectos['cts'][0]['estado']);
        // Pero la próxima vez que se calcule algo de ese semestre, la cuenta ya la ve.
        $this->assertSame(5, (new MotorCts())->calcular($this->empleado, 2026, 'noviembre')['dias_licencia']);
    }

    public function test_descanso_medico_cuenta_hasta_60_dias_en_el_anio(): void
    {
        foreach ([['2026-01-05', '2026-02-28'], ['2026-09-01', '2026-09-30']] as [$desde, $hasta]) {
            Solicitud::forceCreate(['empleado_id' => $this->empleado->id, 'tipo' => 'descanso_medico', 'fecha_inicio' => $desde,
                'fecha_fin' => $hasta, 'motivo' => 'Certificado', 'estado' => 'aprobada']);
        }
        // 55 días en enero-febrero; en setiembre los 5 primeros completan 60 y los otros 25 restan.
        $this->assertSame(25, DiasDeLicencia::excesoDescansoMedico($this->empleado->id, Carbon::parse('2026-05-01'), Carbon::parse('2026-10-31')));
        $calculo = (new MotorCts())->calcular($this->empleado, 2026, 'noviembre');
        $this->assertSame([25, 5, 5], [$calculo['dias_licencia'], $calculo['meses'], $calculo['dias']]);
        // El descanso médico no le quita sueldo.
        $this->assertSame(0, DiasDeLicencia::habilesSinGoce($this->empleado->id, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')));
    }

    public function test_justificar_una_falta_resta_de_la_cts_solo_si_rrhh_lo_marca(): void
    {
        CtsInasistencia::create(['empleado_id' => $this->empleado->id, 'anio' => 2026, 'periodo' => 'noviembre', 'dias' => 3]);
        $this->comoRrhh()->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'noviembre'])->assertOk();

        // No se justifica sin sustento ni una falta que todavía no pasa.
        $this->pedir(['tipo' => 'justificar_falta', 'fecha_inicio' => '2026-10-27', 'fecha_fin' => '2026-10-28', 'motivo' => 'Estuve enfermo'])
            ->assertStatus(422)->assertJsonValidationErrors('sustento');
        $this->pedir(['tipo' => 'justificar_falta', 'fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-20', 'motivo' => 'Cita médica', 'sustento' => $this->pdf()])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_fin');

        $id = $this->pedir(['tipo' => 'justificar_falta', 'fecha_inicio' => '2026-10-27', 'fecha_fin' => '2026-10-28', 'motivo' => 'Estuve enfermo', 'sustento' => $this->pdf()])
            ->assertCreated()->json('data.id');

        $faltas = $this->comoRrhh()->getJson("/api/requests/{$id}/preview")->json('data.faltas_cts');
        $this->assertSame([['anio' => 2026, 'periodo' => 'noviembre', 'anotadas' => 3, 'a_restar' => 2, 'se_puede' => true, 'motivo' => null]], $faltas);

        $this->comoRrhh()->postJson("/api/requests/{$id}/approve", ['restar_faltas' => true])->assertOk();
        $this->assertSame(1, (int) CtsInasistencia::where('empleado_id', $this->empleado->id)->value('dias'));
        $this->assertSame(1, (int) CtsLiquidacion::where('empleado_id', $this->empleado->id)->value('faltas'));

        // Anularla devuelve las dos faltas.
        $this->comoRrhh()->postJson("/api/requests/{$id}/annul", ['respuesta' => 'El certificado no corresponde'])->assertOk();
        $this->assertSame(3, (int) CtsInasistencia::where('empleado_id', $this->empleado->id)->value('dias'));
        $this->assertSame(3, (int) CtsLiquidacion::where('empleado_id', $this->empleado->id)->value('faltas'));
    }

    public function test_cambio_de_cuenta_pide_contrasena_sustento_revisado_y_avisa_por_correo(): void
    {
        $base = ['tipo' => 'cambio_cuenta', 'destino' => 'cts', 'entidad' => 'Caja Arequipa', 'numero' => '00123456789', 'motivo' => 'Cambié de entidad'];

        $this->pedir($base + ['sustento' => $this->pdf(), 'password' => 'otra-cosa'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->pedir($base + ['password' => 'Su-Clave-2026'])->assertStatus(422)->assertJsonValidationErrors('sustento');
        $this->pedir(['destino' => 'sueldo', 'cci' => '123'] + $base + ['sustento' => $this->pdf(), 'password' => 'Su-Clave-2026'])
            ->assertStatus(422)->assertJsonValidationErrors('cci');

        $r = $this->pedir($base + ['sustento' => $this->pdf(), 'password' => 'Su-Clave-2026'])->assertCreated();
        $id = $r->json('data.id');
        // En la lista la cuenta va enmascarada; la contraseña no se guarda en ningún lado.
        $this->assertSame('•••••••6789', $r->json('data.datos.numero'));
        $this->assertArrayNotHasKey('password', Solicitud::find($id)->datos);
        Mail::assertQueued(CambioDeCuenta::class, fn ($m) => $m->hasTo($this->usuario->email) && $m->momento === 'pedida');

        // La misma cuenta dos veces mientras espera, no.
        $this->pedir($base + ['sustento' => $this->pdf(), 'password' => 'Su-Clave-2026'])->assertStatus(422)->assertJsonValidationErrors('destino');

        // Sin abrir el sustento no se aprueba.
        $this->comoRrhh()->postJson("/api/requests/{$id}/approve")->assertStatus(422);
        $this->comoRrhh()->get("/api/requests/{$id}/support")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('00123456789', $this->comoRrhh()->getJson("/api/requests/{$id}/preview")->json('data.datos.numero'));
        $this->comoRrhh()->postJson("/api/requests/{$id}/approve")->assertOk();

        $this->empleado->refresh();
        $this->assertSame(['Caja Arequipa', '00123456789'], [$this->empleado->cts_entidad_financiera, $this->empleado->cts_numero_cuenta]);
        Mail::assertQueued(CambioDeCuenta::class, fn ($m) => $m->momento === 'aprobada');
    }

    public function test_fechas_que_se_cruzan_y_quien_puede_que(): void
    {
        Vacacion::create(['empleado_id' => $this->empleado->id, 'fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-12-10',
            'dias_solicitados' => 10, 'estado' => 'aprobado', 'estado_registro' => 'activo']);

        $this->pedir(['tipo' => 'licencia', 'con_goce' => '0', 'fecha_inicio' => '2026-12-08', 'fecha_fin' => '2026-12-12', 'motivo' => 'Viaje familiar'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');
        $this->pedir(['tipo' => 'permiso', 'con_goce' => '1', 'fecha_inicio' => '2026-12-03', 'hora_inicio' => '09:00', 'hora_fin' => '11:00', 'motivo' => 'Trámite'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');
        // La licencia con goce va con sustento.
        $this->pedir(['tipo' => 'licencia', 'con_goce' => '1', 'fecha_inicio' => '2026-12-14', 'fecha_fin' => '2026-12-15', 'motivo' => 'Paternidad'])
            ->assertStatus(422)->assertJsonValidationErrors('sustento');

        $id = $this->pedir(['tipo' => 'permiso', 'con_goce' => '1', 'fecha_inicio' => '2026-11-12', 'hora_inicio' => '09:00', 'hora_fin' => '11:00', 'motivo' => 'Cita médica'])
            ->assertCreated()->json('data.id');
        // Dos permisos que se pisan el mismo día, no.
        $this->pedir(['tipo' => 'permiso', 'con_goce' => '1', 'fecha_inicio' => '2026-11-12', 'hora_inicio' => '10:00', 'hora_fin' => '12:00', 'motivo' => 'Otro trámite'])
            ->assertStatus(422)->assertJsonValidationErrors('hora_inicio');
        // Y unas vacaciones encima de una licencia pendiente, tampoco.
        $this->pedir(['tipo' => 'licencia', 'con_goce' => '0', 'fecha_inicio' => '2026-12-21', 'fecha_fin' => '2026-12-23', 'motivo' => 'Viaje familiar'])->assertCreated();
        $this->actingAs($this->usuario, 'sanctum')->postJson('/api/vacations', ['fecha_inicio' => '2026-12-22', 'fecha_fin' => '2026-12-24'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');

        // El trabajador no entra a la bandeja ni resuelve nada.
        $this->actingAs($this->usuario, 'sanctum')->getJson('/api/requests')->assertForbidden();
        $this->actingAs($this->usuario, 'sanctum')->postJson("/api/requests/{$id}/approve")->assertForbidden();
        // Nadie resuelve lo suyo.
        $this->rrhh->forceFill(['empleado_id' => (string) $this->empleado->id])->save();
        $this->comoRrhh()->postJson("/api/requests/{$id}/approve")->assertForbidden();
        $this->rrhh->forceFill(['empleado_id' => null])->save();

        // Rechazar pide el motivo; ya rechazada, no se cancela ni se vuelve a resolver.
        $this->comoRrhh()->postJson("/api/requests/{$id}/reject", [])->assertStatus(422)->assertJsonValidationErrors('respuesta');
        $this->comoRrhh()->postJson("/api/requests/{$id}/reject", ['respuesta' => 'Ese día hay evaluación'])->assertOk();
        $this->actingAs($this->usuario, 'sanctum')->deleteJson("/api/my-requests/{$id}")->assertStatus(422);
        $this->comoRrhh()->postJson("/api/requests/{$id}/approve")->assertStatus(422);

        // Los de otro trabajador no se ven ni se bajan.
        $otro = $this->crearUsuario('empleado');
        $otro->forceFill(['empleado_id' => (string) $this->crearEmpleado()->id])->save();
        $this->actingAs($otro, 'sanctum')->deleteJson("/api/my-requests/{$id}")->assertNotFound();
        $this->assertCount(0, $this->actingAs($otro, 'sanctum')->getJson('/api/my-requests')->json('data'));
    }

    public function test_cancelar_una_pendiente_y_un_cesado_no_pide(): void
    {
        $id = $this->pedir(['tipo' => 'licencia', 'con_goce' => '0', 'fecha_inicio' => '2026-11-23', 'fecha_fin' => '2026-11-24', 'motivo' => 'Mudanza'])->json('data.id');
        $this->actingAs($this->usuario, 'sanctum')->deleteJson("/api/my-requests/{$id}")->assertOk();
        $this->assertSame('cancelada', Solicitud::find($id)->estado);
        // Cancelada, ya no ocupa esas fechas.
        $this->pedir(['tipo' => 'licencia', 'con_goce' => '0', 'fecha_inicio' => '2026-11-23', 'fecha_fin' => '2026-11-24', 'motivo' => 'Mudanza'])->assertCreated();

        $this->empleado->forceFill(['estado' => 'inactivo', 'fecha_cese' => '2026-11-05'])->save();
        $this->pedir(['tipo' => 'permiso', 'con_goce' => '1', 'fecha_inicio' => '2026-11-12', 'hora_inicio' => '09:00', 'hora_fin' => '11:00', 'motivo' => 'Trámite'])
            ->assertForbidden();
    }
}
