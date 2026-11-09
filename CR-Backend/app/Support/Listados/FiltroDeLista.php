<?php

namespace App\Support\Listados;

use Closure;
use Illuminate\Support\Collection;

/**
 * Los filtros de las listas de CTS y de Gratificaciones, del lado del
 * servidor: los mismos que aplica la pantalla (situación, sede, área, cargo
 * y la búsqueda por DNI, nombre o cargo), para que el Excel baje exactamente
 * lo que se ve. Como Renta5taController::filtrar.
 */
final class FiltroDeLista
{
    /**
     * @param  Collection<int, array>          $filas
     * @param  array<string, ?string>          $f            situacion, sede_id, area_id, cargo_id, search
     * @param  Closure(array, string): bool    $esSituacion  si la fila está en esa situación
     */
    public static function aplicar(Collection $filas, array $f, Closure $esSituacion): Collection
    {
        $busqueda = mb_strtolower(trim((string) ($f['search'] ?? '')));

        return $filas->filter(fn (array $x) => (empty($f['situacion']) || $esSituacion($x, (string) $f['situacion']))
            && (empty($f['sede_id']) || (string) $x['sede_id'] === (string) $f['sede_id'])
            && (empty($f['area_id']) || (string) $x['area_id'] === (string) $f['area_id'])
            && (empty($f['cargo_id']) || (string) $x['cargo_id'] === (string) $f['cargo_id'])
            && ($busqueda === '' || collect([$x['dni'], $x['nombre'], $x['cargo']])
                ->contains(fn ($v) => str_contains(mb_strtolower((string) $v), $busqueda))))
            ->values();
    }

    /**
     * «Filtros: Situación: Depositada, Sede: CATA Central» — la segunda fila
     * del Excel, para que diga qué es.
     *
     * @param  array<string, string>  $situaciones  valor => cómo se lee
     */
    public static function describir(array $f, Collection $todas, array $situaciones): string
    {
        $nombreDe = fn (string $campo, string $id) => optional($todas->first(fn ($x) => (string) $x["{$campo}_id"] === $id))[$campo] ?? $id;
        $partes = array_filter([
            ! empty($f['situacion']) ? 'Situación: ' . ($situaciones[$f['situacion']] ?? $f['situacion']) : null,
            ! empty($f['sede_id']) ? 'Sede: ' . $nombreDe('sede', (string) $f['sede_id']) : null,
            ! empty($f['area_id']) ? 'Área: ' . $nombreDe('area', (string) $f['area_id']) : null,
            ! empty($f['cargo_id']) ? 'Cargo: ' . $nombreDe('cargo', (string) $f['cargo_id']) : null,
            ! empty($f['search']) ? "Búsqueda: «{$f['search']}»" : null,
        ]);

        return $partes ? 'Filtros: ' . implode(', ', $partes) : 'Todo el personal';
    }
}
