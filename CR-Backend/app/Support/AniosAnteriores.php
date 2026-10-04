<?php

namespace App\Support;

use App\Models\Configuracion;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Planillas y boletas de un año que ya pasó.
 *
 * Antes del sistema las boletas se firmaban a mano. Para tenerlas de
 * registro, RR.HH. puede armar la planilla de, por ejemplo, todo 2025. Un
 * mes así no es como el del año en curso:
 *
 *   - Le toca a quien trabajaba ESE mes, aunque hoy ya no esté.
 *   - Su boleta ya se firmó en papel: no se le avisa al trabajador ni se le
 *     pide firmarla otra vez.
 *
 * Lo mide el AÑO, no el mes: la boleta de septiembre que se emite en
 * octubre es la de siempre, con su aviso y su firma.
 */
final class AniosAnteriores
{
    public const AJUSTE = 'permitir_anios_anteriores';

    public static function esAnterior(int $anio): bool
    {
        return $anio < (int) now()->year;
    }

    public static function permitidos(): bool
    {
        return Configuracion::activo(self::AJUSTE, true);
    }

    /** Corta la petición si el año ya pasó y el Administrador lo tiene apagado. */
    public static function exigir(int $anio): void
    {
        if (self::esAnterior($anio) && ! self::permitidos()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => "No se pueden armar planillas ni boletas de {$anio}: los años anteriores están desactivados. "
                    . 'Un Administrador lo puede activar en Configuración → Ajustes del sistema.',
            ], 422));
        }
    }

    /**
     * Si esa persona trabajaba ese mes, sin mirar su estado de hoy.
     *
     * Quien sigue activo trabajaba desde que entró (eso lo mide el prorrateo
     * por fecha de ingreso). Quien ya cesó, hasta su fecha de cese: sin fecha
     * no se puede saber, y se le deja fuera antes que inventarle meses.
     */
    public static function trabajabaEnElMes($empleado, int $mes, int $anio): bool
    {
        if ($empleado->estado === 'activo') {
            return true;
        }

        if (! $empleado->fecha_cese) {
            return false;
        }

        return Carbon::parse($empleado->fecha_cese)->startOfDay()
            ->gte(Carbon::create($anio, $mes, 1)->startOfDay());
    }
}
