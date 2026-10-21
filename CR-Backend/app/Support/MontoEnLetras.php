<?php

namespace App\Support;

/**
 * Un monto en soles escrito en letras, como va en una liquidación o un
 * cheque: 2302.13 → «DOS MIL TRESCIENTOS DOS CON 13/100 SOLES».
 *
 * Hecho a mano y no con NumberFormatter: el contenedor de producción no trae
 * la extensión intl. Llega hasta los cientos de millones, que sobra.
 */
class MontoEnLetras
{
    private const UNIDADES = [
        '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve',
    ];

    private const DECENAS = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];

    private const CENTENAS = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    public static function soles(float $monto): string
    {
        $centimos = (int) round($monto * 100);
        $entero = intdiv($centimos, 100);

        return mb_strtoupper(self::entero($entero) . ' con ' . sprintf('%02d', $centimos % 100) . '/100 soles');
    }

    public static function entero(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }

        $millones = intdiv($n, 1_000_000);
        $miles = intdiv($n % 1_000_000, 1000);
        $resto = $n % 1000;
        $partes = [];

        if ($millones) {
            $partes[] = $millones === 1 ? 'un millón' : self::centenas($millones, true) . ' millones';
        }
        if ($miles) {
            $partes[] = $miles === 1 ? 'mil' : self::centenas($miles, true) . ' mil';
        }
        if ($resto) {
            $partes[] = self::centenas($resto, false);
        }

        return implode(' ', $partes);
    }

    /** De 1 a 999. Con `$apocope`, «un» y «veintiún» (antes de «mil» o «millones»). */
    private static function centenas(int $n, bool $apocope): string
    {
        if ($n === 100) {
            return 'cien';
        }

        $texto = trim(self::CENTENAS[intdiv($n, 100)] . ' ' . self::decenas($n % 100, $apocope));

        return $texto;
    }

    private static function decenas(int $n, bool $apocope): string
    {
        if ($n < 30) {
            if ($apocope && $n === 1) {
                return 'un';
            }
            if ($apocope && $n === 21) {
                return 'veintiún';
            }

            return self::UNIDADES[$n];
        }

        $unidad = $n % 10;
        $texto = self::DECENAS[intdiv($n, 10)];
        if ($unidad) {
            $texto .= ' y ' . ($apocope && $unidad === 1 ? 'un' : self::UNIDADES[$unidad]);
        }

        return $texto;
    }
}
