<?php

namespace App\Support\Portal;

use App\Models\Portal\Imagen;
use Carbon\CarbonInterface;

/**
 * Los tipos del contrato del portal, armados campo por campo.
 *
 * Cada objeto se arma con sus claves en el orden del contrato y SOLO con
 * ellas, por dos motivos:
 *
 *   - El esquema del portal es estricto (additionalProperties: false): una
 *     clave de más que se colara desde el panel invalidaría la sección.
 *   - MySQL reordena las claves de las columnas JSON (las devuelve por largo
 *     y alfabeto). El JSON es el mismo, pero así la respuesta sale siempre
 *     igual de legible, venga de SQLite o de MySQL.
 */
class Formato
{
    /** Un texto obligatorio, sin espacios en los bordes. */
    public static function texto(?string $texto): string
    {
        return trim((string) $texto);
    }

    /** Un texto opcional: vacío vale null. */
    public static function opcional(?string $texto): ?string
    {
        return Ficha::limpio($texto);
    }

    /** Una lista de textos (párrafos, ítems), sin los vacíos. */
    public static function textos(?array $textos): array
    {
        return array_values(array_filter(
            array_map(fn ($t) => is_scalar($t) ? trim((string) $t) : '', $textos ?? []),
            fn ($t) => $t !== '',
        ));
    }

    /** Fecha sin hora (§1.6). */
    public static function fecha(?CarbonInterface $fecha): ?string
    {
        return $fecha?->format('Y-m-d');
    }

    /**
     * Un monto en soles como número, no como el texto "440.00" que da una
     * columna decimal: entero si no tiene céntimos (440), si no, con ellos.
     */
    public static function numero(int|float|string|null $valor): int|float|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $numero = (float) $valor;

        return floor($numero) === $numero ? (int) $numero : $numero;
    }

    /** El tipo Imagen (§1.7). */
    public static function imagen(?Imagen $imagen): ?array
    {
        return $imagen?->aContrato();
    }

    /** Un objeto Imagen que ya viene armado (de un JSON con las imágenes resueltas). */
    public static function imagenDeArreglo(?array $imagen): ?array
    {
        if ($imagen === null) {
            return null;
        }

        return [
            'url'   => (string) $imagen['url'],
            'alt'   => trim((string) ($imagen['alt'] ?? '')),
            'ancho' => isset($imagen['ancho']) ? (int) $imagen['ancho'] : null,
            'alto'  => isset($imagen['alto']) ? (int) $imagen['alto'] : null,
        ];
    }

    /** Telefono (§1.8). */
    public static function telefono(?array $telefono): ?array
    {
        if (! $telefono) {
            return null;
        }

        return [
            'numero'  => self::texto($telefono['numero'] ?? null),
            'visible' => self::texto($telefono['visible'] ?? null),
        ];
    }

    /** Enlace (§1.8). */
    public static function enlace(?array $enlace): ?array
    {
        if (! $enlace) {
            return null;
        }

        return [
            'etiqueta' => self::texto($enlace['etiqueta'] ?? null),
            'url'      => self::texto($enlace['url'] ?? null),
            'externo'  => (bool) ($enlace['externo'] ?? false),
        ];
    }

    /** AccionHero: { etiqueta, destino } (§3.3). */
    public static function accion(?array $accion): ?array
    {
        if (! $accion) {
            return null;
        }

        return [
            'etiqueta' => self::texto($accion['etiqueta'] ?? null),
            'destino'  => self::destino($accion['destino'] ?? []),
        ];
    }

    /** Destino (§1.8): uno de tres objetos, según `tipo`. */
    public static function destino(array $destino): array
    {
        return match ($destino['tipo'] ?? null) {
            'seccion' => ['tipo' => 'seccion', 'seccion' => (string) $destino['seccion']],
            'pagina'  => ['tipo' => 'pagina', 'ruta' => (string) $destino['ruta']],
            'url'     => ['tipo' => 'url', 'url' => (string) $destino['url'], 'externo' => (bool) ($destino['externo'] ?? true)],
            // Un destino que no es de los tres no se adivina: el panel no
            // deja guardarlo, y si aun así llega, la sección lo dice.
            default   => throw SeccionSinConfigurar::falta('destino', 'tipo'),
        };
    }

    /** Cifra (§3.9). */
    public static function cifra(\App\Models\Portal\Cifra $cifra): array
    {
        return [
            'id'       => $cifra->clave,
            'valor'    => $cifra->valor,
            'sufijo'   => self::opcional($cifra->sufijo),
            'etiqueta' => self::texto($cifra->etiqueta),
        ];
    }

    /** Las cifras publicadas de un ámbito (portada, logros, propuesta). */
    public static function cifras(string $ambito): array
    {
        return \App\Models\Portal\Cifra::de($ambito)->publicados()->get()
            ->map(fn ($cifra) => self::cifra($cifra))
            ->all();
    }
}
