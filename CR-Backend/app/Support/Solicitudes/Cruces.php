<?php

namespace App\Support\Solicitudes;

use App\Models\Solicitud;
use App\Models\Vacacion;
use Carbon\Carbon;

/**
 * Que dos ausencias no se pisen: vacaciones, licencias, descansos médicos y
 * faltas justificadas ocupan días enteros, y un mismo día no puede ser dos
 * cosas a la vez. Si lo fuera, la planilla lo restaría dos veces o la CTS lo
 * contaría como trabajado y como no trabajado.
 *
 * Se revisa al pedir y otra vez al aprobar (entre una cosa y otra pueden
 * haberse aprobado otras). Lo usan Solicitudes y Vacaciones.
 */
final class Cruces
{
    /** La primera ausencia vigente que se cruza con esas fechas, en palabras; null si no hay. */
    public static function con(string $empleadoId, Carbon $inicio, Carbon $fin, ?string $exceptoSolicitud = null, ?string $exceptoVacacion = null): ?string
    {
        $vacacion = Vacacion::where('empleado_id', $empleadoId)
            ->where('estado_registro', 'activo')->whereIn('estado', ['pendiente', 'aprobado'])
            ->when($exceptoVacacion, fn ($q) => $q->where('id', '!=', $exceptoVacacion))
            ->where('fecha_inicio', '<=', $fin->toDateString())->where('fecha_fin', '>=', $inicio->toDateString())
            ->first();
        if ($vacacion) {
            return 'Esas fechas se cruzan con sus vacaciones del ' . self::rango($vacacion->fecha_inicio, $vacacion->fecha_fin) . '.';
        }

        $solicitud = Solicitud::where('empleado_id', $empleadoId)
            ->whereIn('tipo', Solicitud::CON_DIAS)->whereIn('estado', Solicitud::VIGENTES)
            ->when($exceptoSolicitud, fn ($q) => $q->where('id', '!=', $exceptoSolicitud))
            ->where('fecha_inicio', '<=', $fin->toDateString())->where('fecha_fin', '>=', $inicio->toDateString())
            ->first();
        if ($solicitud) {
            $estado = $solicitud->estado === 'pendiente' ? ' (pendiente)' : '';
            return 'Esas fechas se cruzan con su ' . mb_strtolower($solicitud->nombre()) . $estado . ' del ' . self::rango($solicitud->fecha_inicio, $solicitud->fecha_fin) . '.';
        }

        return null;
    }

    private static function rango($desde, $hasta): string
    {
        $d = Carbon::parse($desde)->format('d/m/Y');
        $h = Carbon::parse($hasta)->format('d/m/Y');

        return $d === $h ? $d : "{$d} al {$h}";
    }
}
