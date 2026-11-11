<?php

namespace App\Support\Solicitudes;

use App\Models\CtsInasistencia;
use App\Models\CtsLiquidacion;
use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\Solicitud;
use App\Support\Cts\MotorCts;
use App\Support\Documentos\BoletaAlDia;
use App\Support\Meses;
use App\Traits\CalculaConceptosPlanilla;
use Carbon\Carbon;

/**
 * Lo que pasa en la planilla y en la CTS cuando RR.HH. aprueba (o revierte)
 * una solicitud. Todo se recalcula desde lo aprobado (DiasDeLicencia), así
 * que aprobar y revertir son la misma operación: «recalcular lo tocado».
 *
 * Lo ya pagado no se toca nunca:
 *   · una planilla cerrada o con la boleta firmada se queda como está, y se
 *     le dice a RR.HH. que lo arregle en la siguiente;
 *   · una CTS ya depositada, igual.
 * Lo que todavía no se calculó (la planilla del mes que viene, la CTS del
 * semestre en curso) no necesita nada: al calcularse ya lee lo aprobado.
 *
 * `previa()` dice todo eso ANTES de aprobar, para que RR.HH. decida
 * sabiendo; `aplicar()` lo hace y devuelve lo que hizo, que se guarda en
 * `solicitudes.efectos`.
 */
final class EfectosDeSolicitud
{
    use CalculaConceptosPlanilla;

    /**
     * Qué pasaría al aprobar, con las faltas de CTS que se podrían restar
     * (solo justificar falta).
     *
     * @return array{avisos: string[], faltas_cts: array<int, array<string, mixed>>}
     */
    public function previa(Solicitud $s, ?bool $conGoce = null): array
    {
        $conGoce ??= (bool) $s->con_goce;
        $avisos = [];
        $faltas = [];

        switch ($s->tipo) {
            case Solicitud::PERMISO:
                $avisos[] = $conGoce
                    ? 'Es un permiso con goce: no cambia la planilla ni la CTS.'
                    : 'Es un permiso sin goce por horas: la planilla no lo descuenta sola. Si corresponde, descuéntalo en la planilla de '
                        . $this->mesTexto($s->fecha_inicio) . ' con «Descuento Autorizado - Tardanzas y Faltas». La CTS no cambia (son horas, no días).';
                break;

            case Solicitud::LICENCIA:
                if ($conGoce) {
                    $avisos[] = "Licencia con goce de {$s->dias()} día(s): se paga y cuenta como trabajada para la CTS. No cambia nada.";
                    break;
                }
                $avisos[] = "Licencia sin goce de {$s->dias()} día(s): no se pagan y no cuentan para la CTS.";
                foreach ($this->meses($s) as [$mes, $anio]) {
                    $avisos[] = $this->avisoPlanilla($s->empleado, $mes, $anio);
                }
                foreach ($this->semestres($s->fecha_inicio, $s->fecha_fin) as [$anio, $periodo]) {
                    $avisos[] = $this->avisoCts($s->empleado, $anio, $periodo);
                }
                break;

            case Solicitud::DESCANSO_MEDICO:
                $anio  = (int) $s->fecha_inicio->year;
                $antes = DiasDeLicencia::descansoMedicoDelAnio($s->empleado_id, $anio, $s->id);
                $total = $antes + $s->dias();
                $avisos[] = "Descanso médico de {$s->dias()} día(s): se paga como siempre. En {$anio} lleva {$antes} día(s) aprobados; con este, {$total}.";
                if ($total > 20) {
                    $avisos[] = 'Pasa de 20 días en el año: desde el día 21 lo paga EsSalud como subsidio. Regístralo en la planilla y pide el reembolso.';
                }
                if ($total > DiasDeLicencia::TOPE_DESCANSO_MEDICO) {
                    $avisos[] = 'Pasa de 60 días en el año: lo que excede no cuenta para la CTS.';
                    foreach ($this->semestres($s->fecha_inicio, $s->fecha_fin) as [$a, $periodo]) {
                        $avisos[] = $this->avisoCts($s->empleado, $a, $periodo);
                    }
                } else {
                    $avisos[] = 'Cuenta como trabajado para la CTS (hasta 60 días en el año).';
                }
                break;

            case Solicitud::JUSTIFICAR_FALTA:
                $avisos[] = 'Una falta justificada no le resta días a la CTS. Si ya se le descontó en una boleta, devuélvelo en la planilla siguiente.';
                $faltas = $this->faltasQueSePuedenRestar($s);
                break;

            case Solicitud::CAMBIO_CUENTA:
                $cts = ($s->datos['destino'] ?? '') === 'cts';
                $avisos[] = $cts
                    ? 'Se cambia la cuenta CTS de su ficha. El próximo depósito de CTS va a la cuenta nueva; lo ya depositado no cambia.'
                    : 'Se cambia la cuenta de sueldo de su ficha. Los pagos que ya se hicieron no cambian.';
                if (! $s->sustento_visto_por) {
                    $avisos[] = 'Abre el sustento antes de aprobar: un cambio de cuenta no se aprueba sin revisarlo.';
                }
                break;
        }

        return ['avisos' => array_values(array_filter($avisos)), 'faltas_cts' => $faltas];
    }

    /**
     * Aplica lo que toca al aprobar. Va dentro de la transacción de quien
     * llama, con la solicitud ya marcada como aprobada.
     *
     * @return array<string, mixed>  lo que se hizo, para `solicitudes.efectos`
     */
    public function aplicar(Solicitud $s, bool $restarFaltas = false): array
    {
        $efectos = ['planillas' => [], 'cts' => [], 'faltas_restadas' => [], 'cuenta_anterior' => null];

        if ($s->tipo === Solicitud::LICENCIA && ! $s->con_goce) {
            $efectos['planillas'] = $this->recalcularPlanillas($s);
            $efectos['cts'] = $this->recalcularCts($s->empleado, $this->semestres($s->fecha_inicio, $s->fecha_fin));
        }

        if ($s->tipo === Solicitud::DESCANSO_MEDICO) {
            // El tope es del año: un descanso nuevo puede mover el exceso de
            // cualquier semestre que toque ese año.
            $efectos['cts'] = $this->recalcularCts($s->empleado, $this->semestres(
                Carbon::create($s->fecha_inicio->year, 1, 1), Carbon::create($s->fecha_fin->year, 12, 31)));
        }

        if ($s->tipo === Solicitud::JUSTIFICAR_FALTA && $restarFaltas) {
            foreach ($this->faltasQueSePuedenRestar($s) as $f) {
                if (! $f['se_puede']) {
                    continue;
                }
                $clave = ['empleado_id' => $s->empleado_id, 'anio' => $f['anio'], 'periodo' => $f['periodo']];
                $registro = CtsInasistencia::where($clave)->lockForUpdate()->first();
                if (! $registro) {
                    continue;
                }
                $quedan = max(0, (int) $registro->dias - $f['a_restar']);
                $restadas = (int) $registro->dias - $quedan;
                $quedan === 0 ? $registro->delete() : $registro->update(['dias' => $quedan]);
                $efectos['faltas_restadas'][] = ['anio' => $f['anio'], 'periodo' => $f['periodo'], 'dias' => $restadas];
            }
            $efectos['cts'] = $this->recalcularCts($s->empleado, array_map(
                fn ($f) => [$f['anio'], $f['periodo']], $efectos['faltas_restadas']));
        }

        if ($s->tipo === Solicitud::CAMBIO_CUENTA) {
            $efectos['cuenta_anterior'] = $this->cambiarCuenta($s);
        }

        return $efectos;
    }

    /**
     * Deshace una aprobada: recalcula lo que tocó (ya sin ella, porque quien
     * llama la marcó anulada antes) y devuelve las faltas que se restaron.
     * El cambio de cuenta no se revierte aquí: se corrige en la ficha.
     *
     * @return string[]  avisos de lo que no se pudo tocar
     */
    public function revertir(Solicitud $s): array
    {
        $avisos = [];
        $efectos = $s->efectos ?? [];

        if ($s->tipo === Solicitud::LICENCIA && ! $s->con_goce) {
            foreach ($this->recalcularPlanillas($s) as $p) {
                if ($p['estado'] === 'bloqueada') {
                    $avisos[] = "Planilla de {$p['mes_texto']}: {$p['motivo']} Devuélvele los días en la planilla siguiente.";
                }
            }
            $avisos = [...$avisos, ...$this->avisosCtsDepositada(
                $this->recalcularCts($s->empleado, $this->semestres($s->fecha_inicio, $s->fecha_fin)))];
        }

        if ($s->tipo === Solicitud::DESCANSO_MEDICO) {
            $avisos = [...$avisos, ...$this->avisosCtsDepositada($this->recalcularCts($s->empleado, $this->semestres(
                Carbon::create($s->fecha_inicio->year, 1, 1), Carbon::create($s->fecha_fin->year, 12, 31))))];
        }

        if ($s->tipo === Solicitud::JUSTIFICAR_FALTA) {
            $semestres = [];
            foreach ($efectos['faltas_restadas'] ?? [] as $f) {
                $clave = ['empleado_id' => $s->empleado_id, 'anio' => $f['anio'], 'periodo' => $f['periodo']];
                $liq = CtsLiquidacion::where($clave)->first();
                if ($liq?->depositada()) {
                    $avisos[] = "La CTS de {$f['periodo']} {$f['anio']} ya está depositada: sus {$f['dias']} día(s) no se vuelven a anotar.";
                    continue;
                }
                $registro = CtsInasistencia::where($clave)->lockForUpdate()->first();
                $registro
                    ? $registro->update(['dias' => (int) $registro->dias + (int) $f['dias']])
                    : CtsInasistencia::create($clave + ['dias' => (int) $f['dias'], 'observacion' => 'Vuelven al anular una justificación de falta.']);
                $semestres[] = [$f['anio'], $f['periodo']];
            }
            $this->recalcularCts($s->empleado, $semestres);
        }

        if ($s->tipo === Solicitud::CAMBIO_CUENTA) {
            $avisos[] = 'La cuenta de la ficha no se cambia sola: si hay que volver a la anterior, corrígela en la ficha del trabajador.';
        }

        return $avisos;
    }

    // ───────────────────────── planilla ─────────────────────────

    /** @return array<int, array{mes: int, anio: int, mes_texto: string, estado: string, motivo?: string}> */
    private function recalcularPlanillas(Solicitud $s): array
    {
        $hechas = [];
        foreach ($this->meses($s) as [$mes, $anio]) {
            $hechas[] = ['mes' => $mes, 'anio' => $anio, 'mes_texto' => $this->mesTexto(Carbon::create($anio, $mes, 1))]
                + $this->recalcularPlanilla($s->empleado, $mes, $anio);
        }

        return $hechas;
    }

    /** Como «Recalcular» de la planilla: el sueldo por los días pagados, sus conceptos y su boleta sin firmar. */
    private function recalcularPlanilla(Empleado $empleado, int $mes, int $anio): array
    {
        $planilla = $this->planillaDe($empleado, $mes, $anio);
        if (! $planilla) {
            return ['estado' => 'sin_planilla'];
        }
        if ($motivo = $planilla->motivoParaNoTocar()) {
            return ['estado' => 'bloqueada', 'motivo' => $motivo];
        }

        $antes = (float) $planilla->sueldo_base;
        $nuevo = $this->sueldoDelMes($empleado, $mes, $anio) ?? 0.0;
        $planilla->update(['sueldo_base' => $nuevo]);
        $this->generarConceptosAutomaticos($planilla, $empleado);
        $planilla->recalcularTotal();
        BoletaAlDia::rehacer($planilla);

        return ['estado' => 'recalculada', 'sueldo_antes' => round($antes, 2), 'sueldo_ahora' => round($nuevo, 2)];
    }

    private function planillaDe(Empleado $empleado, int $mes, int $anio): ?Planilla
    {
        return Planilla::with('corrida', 'documentoBoleta')->where('empleado_id', $empleado->id)
            ->where('mes', $mes)->where('anio', $anio)->where('estado_registro', 'activo')->first();
    }

    private function avisoPlanilla(Empleado $empleado, int $mes, int $anio): string
    {
        $texto = $this->mesTexto(Carbon::create($anio, $mes, 1));
        $planilla = $this->planillaDe($empleado, $mes, $anio);
        if (! $planilla) {
            return "Planilla de {$texto}: todavía no está; al generarla ya sale con esos días descontados.";
        }
        if ($motivo = $planilla->motivoParaNoTocar()) {
            return "Planilla de {$texto}: {$motivo} Los días no se descuentan solos: descuéntalos en la planilla siguiente.";
        }

        return "Planilla de {$texto}: se recalcula sola con los días descontados" . ($planilla->documentoBoleta ? ' y se rehace su boleta (aún sin firmar).' : '.');
    }

    // ───────────────────────── CTS ─────────────────────────

    /**
     * Recalcula la CTS guardada de cada semestre, si está calculada y sin
     * depositar. Lo que no está calculado no se toca: al calcularse lee lo
     * aprobado.
     *
     * @param  array<int, array{0: int, 1: string}>  $semestres
     */
    private function recalcularCts(Empleado $empleado, array $semestres): array
    {
        $hechas = [];
        $motor = new MotorCts();
        foreach ($this->unicos($semestres) as [$anio, $periodo]) {
            $clave = ['empleado_id' => $empleado->id, 'anio' => $anio, 'periodo' => $periodo];
            $liq = CtsLiquidacion::where($clave)->lockForUpdate()->first();
            if (! $liq) {
                continue;
            }
            if ($liq->depositada()) {
                $hechas[] = ['anio' => $anio, 'periodo' => $periodo, 'estado' => 'depositada'];
                continue;
            }
            $antes = (float) $liq->monto;
            $calculo = $motor->calcular($empleado, $anio, $periodo);
            $aplica = $calculo['aplica'];
            unset($calculo['aplica'], $calculo['motivo']);
            $aplica ? $liq->fill($calculo)->save() : $liq->delete();
            $hechas[] = ['anio' => $anio, 'periodo' => $periodo, 'estado' => 'recalculada',
                'monto_antes' => round($antes, 2), 'monto_ahora' => $aplica ? round((float) $calculo['monto'], 2) : 0.0];
        }

        return $hechas;
    }

    private function avisoCts(Empleado $empleado, int $anio, string $periodo): string
    {
        $liq = CtsLiquidacion::where(['empleado_id' => $empleado->id, 'anio' => $anio, 'periodo' => $periodo])->first();

        return match (true) {
            ! $liq              => "CTS de {$periodo} {$anio}: todavía no está calculada; al calcularla ya lo toma en cuenta.",
            $liq->depositada()  => "CTS de {$periodo} {$anio}: ya está depositada, no cambia. Si corresponde, ajústalo en el próximo depósito.",
            default             => "CTS de {$periodo} {$anio}: está calculada y sin depositar; se recalcula sola.",
        };
    }

    /** @return string[] */
    private function avisosCtsDepositada(array $cts): array
    {
        return array_values(array_map(
            fn ($c) => "La CTS de {$c['periodo']} {$c['anio']} ya está depositada: no cambia.",
            array_filter($cts, fn ($c) => $c['estado'] === 'depositada'),
        ));
    }

    /**
     * Las faltas de CTS que RR.HH. anotó en los semestres de las fechas
     * justificadas, y cuántas se podrían restar (una por día justificado,
     * sin pasar de lo anotado). No se puede si ya está depositada.
     *
     * @return array<int, array{anio: int, periodo: string, anotadas: int, a_restar: int, se_puede: bool, motivo: ?string}>
     */
    private function faltasQueSePuedenRestar(Solicitud $s): array
    {
        $porSemestre = [];
        for ($d = $s->fecha_inicio->copy(); $d->lte($s->fecha_fin); $d->addDay()) {
            [$anio, $periodo] = self::semestreDe($d);
            $porSemestre["{$anio}|{$periodo}"] = ($porSemestre["{$anio}|{$periodo}"] ?? 0) + 1;
        }

        $filas = [];
        foreach ($porSemestre as $k => $dias) {
            [$anio, $periodo] = explode('|', $k);
            $anio = (int) $anio;
            $clave = ['empleado_id' => $s->empleado_id, 'anio' => $anio, 'periodo' => $periodo];
            $anotadas = (int) CtsInasistencia::where($clave)->value('dias');
            if ($anotadas === 0) {
                continue;
            }
            $depositada = (bool) CtsLiquidacion::where($clave)->first()?->depositada();
            $filas[] = [
                'anio' => $anio, 'periodo' => $periodo, 'anotadas' => $anotadas,
                'a_restar' => min($dias, $anotadas),
                'se_puede' => ! $depositada,
                'motivo'   => $depositada ? 'Su CTS de ese semestre ya está depositada.' : null,
            ];
        }

        return $filas;
    }

    // ───────────────────────── cuenta ─────────────────────────

    /** Pone la cuenta nueva en la ficha y devuelve la que había (enmascarada, para el registro). */
    private function cambiarCuenta(Solicitud $s): array
    {
        $d = $s->datos ?? [];
        $e = Empleado::whereKey($s->empleado_id)->lockForUpdate()->firstOrFail();

        if (($d['destino'] ?? '') === 'cts') {
            $antes = ['entidad' => $e->cts_entidad_financiera, 'numero' => self::enmascarar($e->cts_numero_cuenta)];
            $e->forceFill(['cts_entidad_financiera' => $d['entidad'], 'cts_numero_cuenta' => $d['numero']])->save();
        } else {
            $antes = ['entidad' => $e->entidad_financiera, 'numero' => self::enmascarar($e->numero_cuenta), 'cci' => self::enmascarar($e->cci)];
            $e->forceFill(['entidad_financiera' => $d['entidad'], 'numero_cuenta' => $d['numero'], 'cci' => $d['cci'] ?? null])->save();
        }

        return $antes;
    }

    /** «••••••4521»: solo los últimos cuatro. */
    public static function enmascarar(?string $numero): ?string
    {
        $numero = trim((string) $numero);
        if ($numero === '') {
            return null;
        }

        return str_repeat('•', max(0, mb_strlen($numero) - 4)) . mb_substr($numero, -4);
    }

    // ───────────────────────── fechas ─────────────────────────

    /** El depósito de CTS al que pertenece un día: [año, 'mayo'|'noviembre']. */
    public static function semestreDe(Carbon $d): array
    {
        $m = (int) $d->month;
        if ($m >= 5 && $m <= 10) {
            return [(int) $d->year, 'noviembre'];
        }

        return [$m >= 11 ? (int) $d->year + 1 : (int) $d->year, 'mayo'];
    }

    /** @return array<int, array{0: int, 1: string}> */
    private function semestres(Carbon $desde, Carbon $hasta): array
    {
        $lista = [];
        for ($d = $desde->copy()->startOfMonth(); $d->lte($hasta); $d->addMonth()) {
            $lista[] = self::semestreDe($d);
        }

        return $this->unicos($lista);
    }

    /** @return array<int, array{0: int, 1: int}> [mes, año] */
    private function meses(Solicitud $s): array
    {
        $lista = [];
        for ($d = $s->fecha_inicio->copy()->startOfMonth(); $d->lte($s->fecha_fin); $d->addMonth()) {
            $lista[] = [(int) $d->month, (int) $d->year];
        }

        return $lista;
    }

    private function unicos(array $pares): array
    {
        $vistos = [];
        foreach ($pares as $p) {
            $vistos[implode('|', $p)] = $p;
        }

        return array_values($vistos);
    }

    private function mesTexto(Carbon $d): string
    {
        return Meses::nombre((int) $d->month) . ' ' . $d->year;
    }
}
