<?php

namespace App\Support;

use App\Models\CtsLiquidacion;
use App\Models\Planilla;
use App\Models\RentaQuintaPrevia;

/**
 * Los años que ofrece el filtro de cada módulo: los que tienen datos guardados,
 * más el año en curso. Se arma con los datos y no con el reloj para que un año
 * con planillas siga disponible siempre, y uno vacío no aparezca.
 *
 * El año elegido entra siempre: es el que la pantalla está mostrando.
 */
final class AniosConDatos
{
    /** @return list<int> */
    public static function gratificaciones(int $elegido): array
    {
        return self::ordenar([...self::conPlanilla(), self::actual(), $elegido]);
    }

    /** @return list<int> */
    public static function renta5ta(int $elegido): array
    {
        return self::ordenar([
            ...self::conPlanilla(),
            ...RentaQuintaPrevia::query()->distinct()->pluck('anio')->all(),
            self::actual(),
            $elegido,
        ]);
    }

    /**
     * El depósito de mayo cubre desde noviembre del año anterior: una planilla
     * de noviembre o diciembre ya alimenta el depósito del año siguiente, y en
     * esos meses hay que poder prepararlo.
     *
     * @return list<int>
     */
    public static function cts(int $elegido): array
    {
        $hoy = now();

        return self::ordenar([
            ...self::conPlanilla(),
            ...Planilla::query()->where('mes', '>=', 11)->distinct()->pluck('anio')->map(fn ($anio) => (int) $anio + 1)->all(),
            ...CtsLiquidacion::query()->distinct()->pluck('anio')->all(),
            self::actual(),
            ...($hoy->month >= 11 ? [self::actual() + 1] : []),
            $elegido,
        ]);
    }

    /** @return list<int> */
    private static function conPlanilla(): array
    {
        return Planilla::query()->distinct()->pluck('anio')->all();
    }

    private static function actual(): int
    {
        return (int) now()->year;
    }

    /**
     * @param  array<int|string>  $anios
     * @return list<int>
     */
    private static function ordenar(array $anios): array
    {
        $unicos = array_unique(array_map('intval', $anios));
        rsort($unicos);

        return $unicos;
    }
}
