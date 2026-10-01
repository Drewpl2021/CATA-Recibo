<?php

namespace App\Console\Commands;

use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Notificacion;
use App\Models\Planilla;
use App\Models\User;
use App\Support\Meses;
use Illuminate\Console\Command;

/**
 * El penúltimo día del mes, si todavía no se generó la planilla o no se
 * emitió ninguna boleta, le deja un aviso en la campana a RR.HH. y
 * Administración — el mismo sitio donde el trabajador ve "tu boleta ya
 * está", solo que este aviso es para quien tiene que generarla.
 *
 * Por qué el PENÚLTIMO día y no el último: el último ya no deja margen
 * para reaccionar —generar, revisar y emitir toma su rato—, y el
 * penúltimo todavía regala un día entero antes de que se acabe el mes.
 *
 * Un aviso por mes, no dos: si falta la planilla, avisa de la planilla
 * (emitir boletas sin ella no se puede); si la planilla ya está pero
 * faltan las boletas, avisa de eso. Nunca los dos juntos, porque el
 * segundo problema no existe mientras exista el primero.
 */
class AvisarPlanillaPendiente extends Command
{
    protected $signature = 'planillas:avisar-pendientes
        {--forzar : Avisa aunque hoy no sea el penúltimo día del mes}';

    protected $description = 'Avisa a RR.HH. y Administración si falta generar la planilla o emitir boletas del mes';

    public function handle(): int
    {
        $hoy = now();

        if (! $this->option('forzar') && $hoy->day !== $hoy->daysInMonth - 1) {
            $this->info('Hoy no es el penúltimo día del mes: no hay nada que avisar.');

            return self::SUCCESS;
        }

        $mes  = $hoy->month;
        $anio = $hoy->year;

        $empleadosActivos = Empleado::where('estado', 'activo')->count();

        if ($empleadosActivos === 0) {
            $this->info('No hay empleados activos: no hay nada que avisar.');

            return self::SUCCESS;
        }

        $conPlanilla = Planilla::where('mes', $mes)->where('anio', $anio)
            ->distinct('empleado_id')->count('empleado_id');

        $periodo = Meses::nombre($mes) . ' ' . $anio;

        if ($conPlanilla < $empleadosActivos) {
            $faltan = $empleadosActivos - $conPlanilla;
            $this->avisar(
                "Falta generar la planilla de {$periodo}",
                $faltan === $empleadosActivos
                    ? "Todavía no se generó ninguna planilla de {$periodo}. Quedan pocos días del mes."
                    : "Faltan {$faltan} de {$empleadosActivos} trabajadores por tener su planilla de {$periodo}. Quedan pocos días del mes.",
            );

            $this->info("Aviso enviado: falta la planilla de {$periodo} ({$faltan} de {$empleadosActivos} trabajadores).");

            return self::SUCCESS;
        }

        $hayBoletas = Documento::where('tipo', 'boleta')
            ->where('periodo_mes', $mes)->where('periodo_anio', $anio)
            ->exists();

        if (! $hayBoletas) {
            $this->avisar(
                "Faltan emitir las boletas de {$periodo}",
                "La planilla de {$periodo} ya está lista, pero todavía no se emitió ninguna boleta. Quedan pocos días del mes.",
            );

            $this->info("Aviso enviado: faltan emitir las boletas de {$periodo}.");

            return self::SUCCESS;
        }

        $this->info("Ya está todo: planilla generada y boletas emitidas de {$periodo}. No hay nada que avisar.");

        return self::SUCCESS;
    }

    /**
     * A RR.HH. y Administración, que son quienes pueden hacer algo con el
     * aviso — un docente no tiene cómo generar una planilla.
     *
     * No se duplica si el comando corriera dos veces el mismo día (el
     * scheduler reiniciándose, por ejemplo): antes de crear el aviso se
     * revisa si ese usuario ya tiene uno igual de hoy.
     */
    private function avisar(string $titulo, string $mensaje): void
    {
        $usuarios = User::whereHas('rol', fn ($q) => $q->whereIn('nombre', ['admin', 'rrhh']))
            ->where('estado_registro', 'activo')
            ->get();

        foreach ($usuarios as $user) {
            $yaAvisado = Notificacion::where('user_id', $user->id)
                ->where('tipo', 'planilla_pendiente')
                ->whereDate('created_at', now())
                ->exists();

            if ($yaAvisado) {
                continue;
            }

            Notificacion::create([
                'user_id' => $user->id,
                'tipo'    => 'planilla_pendiente',
                'titulo'  => $titulo,
                'mensaje' => $mensaje,
            ]);
        }
    }
}
