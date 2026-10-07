<?php

namespace App\Support\Renta5ta;

use App\Models\Configuracion;

/**
 * Con qué método se calcula la Renta de 5ta.
 *
 * Por defecto, el de SUNAT (MotorRenta5ta). El ajuste deja volver al de la
 * hoja de RR.HH. ("Calculo 5ta.xlsx": se proyecta una vez y se reparte
 * igual de marzo a diciembre) solo para comparar con planillas viejas.
 */
final class MetodoRenta5ta
{
    public const AJUSTE = 'renta5ta_como_hoja_rrhh';

    public static function comoHojaDeRrhh(): bool
    {
        return Configuracion::activo(self::AJUSTE, false);
    }
}
