<?php

namespace App\Support;

/**
 * Los meses del año, en UN solo sitio.
 *
 * Estaban copiados en la boleta de RR.HH. y en la del propio trabajador, y
 * duplicar una lista es duplicar sus erratas: bastaba corregir una para que
 * la misma boleta se llamara distinto según por dónde se descargara.
 */
class Meses
{
    public const NOMBRES = [
        1  => 'Enero',      2  => 'Febrero',   3  => 'Marzo',
        4  => 'Abril',      5  => 'Mayo',      6  => 'Junio',
        7  => 'Julio',      8  => 'Agosto',    9  => 'Septiembre',
        10 => 'Octubre',    11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public static function nombre(int|string|null $mes): string
    {
        return self::NOMBRES[(int) $mes] ?? '';
    }
}
