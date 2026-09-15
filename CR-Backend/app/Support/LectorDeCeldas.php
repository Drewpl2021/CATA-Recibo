<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Leer las celdas de un Excel tal como llegan del navegador.
 *
 * Las dos importaciones (conceptos de pago y empleados) leen DNIs, montos,
 * fechas y textos de celdas que RR.HH. llenó a mano. Cada una tenía su copia
 * de estas reglas, y dos copias de "cómo se lee un monto" terminan leyendo
 * distinto el mismo "S/ 1,200.50".
 *
 * Todo devuelve null cuando la celda está vacía o no se entiende; quien llama
 * distingue los dos casos con vacia().
 */
final class LectorDeCeldas
{
    /** Vacía, o con el guion que se pone en Excel para decir "nada". */
    public static function vacia(mixed $crudo): bool
    {
        return $crudo === null
            || (is_string($crudo) && in_array(trim($crudo), ['', '-', '—', '–'], true));
    }

    public static function filaVacia(array $celdas): bool
    {
        foreach ($celdas as $celda) {
            if (! self::vacia($celda)) {
                return false;
            }
        }

        return true;
    }

    /** Texto limpio: sin espacios de más. Un número entero sale sin ".0". */
    public static function texto(mixed $crudo): ?string
    {
        if (self::vacia($crudo)) {
            return null;
        }
        if (is_float($crudo) && floor($crudo) == $crudo) {
            $crudo = number_format($crudo, 0, '', '');
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $crudo));
    }

    /** Solo las cifras: "951 234-567" → "951234567". */
    public static function digitos(mixed $crudo): ?string
    {
        if (self::vacia($crudo)) {
            return null;
        }
        if (is_int($crudo) || (is_float($crudo) && floor($crudo) == $crudo)) {
            return number_format((float) $crudo, 0, '', '');
        }

        return preg_replace('/[\s.\-]/', '', (string) $crudo);
    }

    /** "41.234.567", " 41234567 " y "41234567" son el mismo DNI. */
    public static function claveDni(?string $dni): string
    {
        return strtoupper(preg_replace('/[\s.\-]/', '', (string) $dni));
    }

    /**
     * El DNI de una celda, y las formas en que puede venir escrito.
     *
     * Excel guarda un DNI tecleado como número y se come los ceros de la
     * izquierda: 01234567 llega como 1234567. Por eso, a un número de 6 o 7
     * cifras se le prueba también con los ceros devueltos.
     *
     * @return array{vacio: bool, claves: string[], texto: string}
     */
    public static function dni(mixed $crudo): array
    {
        if (is_int($crudo) || is_float($crudo)) {
            $crudo = floor($crudo) == $crudo ? number_format((float) $crudo, 0, '', '') : (string) $crudo;
        }

        $texto = trim((string) $crudo);
        if (self::vacia($texto)) {
            return ['vacio' => true, 'claves' => [], 'texto' => ''];
        }

        $clave  = self::claveDni($texto);
        $claves = [$clave];
        if (ctype_digit($clave) && strlen($clave) >= 6 && strlen($clave) < 8) {
            $claves[] = str_pad($clave, 8, '0', STR_PAD_LEFT);
        }

        return ['vacio' => false, 'claves' => $claves, 'texto' => $texto];
    }

    /**
     * Un monto como lo escriben en Perú, en un Excel o a mano.
     *
     * 120 · "120" · "S/ 1,200.50" · "1.200,50" · "35,50" → número.
     * El separador que va al final con 1 o 2 cifras detrás es el decimal.
     */
    public static function monto(mixed $crudo): ?float
    {
        if (is_int($crudo) || is_float($crudo)) {
            return round((float) $crudo, 2);
        }
        if (! is_string($crudo)) {
            return null;
        }

        $t = preg_replace('/^(s\/\.?|pen)\s*/i', '', trim($crudo));
        $t = str_replace([' ', "\u{00A0}"], '', $t);

        if (str_contains($t, ',') && str_contains($t, '.')) {
            $t = strrpos($t, ',') > strrpos($t, '.')
                ? str_replace(',', '.', str_replace('.', '', $t))
                : str_replace(',', '', $t);
        } elseif (str_contains($t, ',')) {
            $t = preg_match('/,\d{1,2}$/', $t) ? str_replace(',', '.', $t) : str_replace(',', '', $t);
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $t) ? round((float) $t, 2) : null;
    }

    /**
     * Una fecha como llega: "2026-01-15" (así la manda el navegador cuando la
     * celda es de tipo fecha), "15/01/2026", "15-01-26", o el número de serie
     * con que Excel guarda las fechas por dentro (46037).
     *
     * Siempre día/mes/año: es como se escribe en Perú. Devuelve 'Y-m-d'.
     */
    public static function fecha(mixed $crudo): ?string
    {
        if (self::vacia($crudo)) {
            return null;
        }

        if (is_int($crudo) || is_float($crudo)) {
            $serie = (int) $crudo;
            if ($serie < 1 || $serie > 100000) {
                return null;
            }

            return Carbon::create(1899, 12, 30)->addDays($serie)->format('Y-m-d');
        }

        $t = trim((string) $crudo);

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $t, $m)) {
            [$anio, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2}|\d{4})$/', $t, $m)) {
            [$dia, $mes, $anio] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if (strlen($m[3]) === 2) {
                // "85" es 1985 y "20" es 2020: se toma el siglo que no deja la
                // fecha en el futuro.
                $anio += $anio > (int) now()->format('y') ? 1900 : 2000;
            }
        } else {
            return null;
        }

        return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }

    /** "Sí", "si", "x", "1" → true; "No", "0" → false; otra cosa → null. */
    public static function siNo(mixed $crudo): ?bool
    {
        if (is_bool($crudo)) {
            return $crudo;
        }
        if (is_int($crudo) || is_float($crudo)) {
            return in_array((int) $crudo, [0, 1], true) ? (bool) $crudo : null;
        }

        $t = ReconocedorDeColumnas::normalizar((string) $crudo);

        return match (true) {
            in_array($t, ['si', 's', 'x', 'yes', 'true', 'verdadero', '1'], true) => true,
            in_array($t, ['no', 'n', 'false', 'falso', '0'], true)                 => false,
            default                                                                 => null,
        };
    }
}
