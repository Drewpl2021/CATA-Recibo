<?php

namespace App\Support\Pagos;

use App\Support\Listados\LibroExcel;

/**
 * La relación de pagos: a quién, a qué cuenta y cuánto, agrupado por banco o
 * caja, con lo que NO se puede abonar aparte y lo que hay que revisar antes
 * de pagar. Es lo que Tesorería necesita para hacer las transferencias (o
 * para cargar el archivo del banco) y lo que se firma como respaldo.
 *
 * Sirve para los sueldos de un mes (el neto de cada planilla) y para el
 * depósito de CTS de un semestre (a la cuenta CTS, nunca a la del sueldo).
 * Quien llama le pasa las filas; aquí solo se ordenan, se revisan y se suman.
 *
 * Los montos se suman ya redondeados a céntimos: el banco abona a cada uno
 * su monto redondeado, así que el total tiene que ser la suma de esos.
 */
final class RelacionDePagos
{
    /**
     * @param  list<array{dni: ?string, nombre: string, forma_pago?: ?string, entidad: ?string, cuenta: ?string, cci?: ?string, monto: float, cesado?: bool}>  $filas
     * @param  bool  $esCts  la CTS de un cesado no se deposita: se le paga con su liquidación
     * @return array{grupos: array<string, array{filas: list<array>, total: float}>, sin_abono: list<array>, alertas: list<string>, total_abonar: float, total_otro_medio: float, personas: int}
     */
    public static function armar(array $filas, bool $esCts = false): array
    {
        $grupos = [];
        $sinAbono = [];
        $alertas = [];
        $porCuenta = [];

        usort($filas, fn ($a, $b) => strnatcasecmp($a['nombre'], $b['nombre']));

        foreach ($filas as $f) {
            $monto = round((float) $f['monto'], 2);
            $cuenta = trim((string) ($f['cuenta'] ?? ''));
            $entidad = mb_strtoupper(trim((string) ($f['entidad'] ?? '')));
            $fila = ['dni' => (string) $f['dni'], 'nombre' => $f['nombre'], 'entidad' => $entidad, 'cuenta' => $cuenta,
                'cci' => trim((string) ($f['cci'] ?? '')), 'monto' => $monto];

            $motivo = match (true) {
                $monto < 0                                  => 'Neto negativo: los descuentos pasan lo que gana. Revisa su planilla.',
                $monto == 0.0                               => 'Monto en cero: no hay nada que pagarle.',
                $esCts && ! empty($f['cesado'])             => 'Cesado: su CTS no se deposita, se le paga directamente con su liquidación.',
                ! $esCts && ($f['forma_pago'] ?? null) === 'efectivo' => 'Cobra en efectivo, según su ficha.',
                $cuenta === ''                              => $esCts ? 'No tiene cuenta CTS en su ficha.' : 'No tiene número de cuenta en su ficha.',
                default                                     => null,
            };
            if ($motivo !== null) {
                $sinAbono[] = $fila + ['motivo' => $motivo];
                continue;
            }

            $clave = $entidad !== '' ? $entidad : '(SIN ENTIDAD)';
            if ($entidad === '') {
                $alertas[] = "{$f['nombre']}: tiene número de cuenta pero no el banco o la caja.";
            }
            $grupos[$clave]['filas'][] = $fila;
            $grupos[$clave]['total'] = round(($grupos[$clave]['total'] ?? 0) + $monto, 2);
            $porCuenta[$clave . '|' . preg_replace('/\D/', '', $cuenta)][] = $f['nombre'];
        }

        // La misma cuenta para dos personas es, casi siempre, un error de digitación.
        foreach ($porCuenta as $clave => $nombres) {
            if (count($nombres) > 1) {
                $alertas[] = 'La misma cuenta (' . explode('|', $clave)[0] . ') figura para: ' . implode(', ', $nombres) . '.';
            }
        }

        ksort($grupos);
        // La columna CCI solo si alguien la tiene: vacía para todos, sobra.
        $conCci = (bool) array_filter(array_merge(...array_map(fn ($g) => array_column($g['filas'], 'cci'), array_values($grupos) ?: [['filas' => []]])));

        return [
            'grupos'           => $grupos,
            'sin_abono'        => $sinAbono,
            'alertas'          => $alertas,
            'total_abonar'     => round(array_sum(array_column($grupos, 'total')), 2),
            'total_otro_medio' => round(array_sum(array_map(fn ($f) => max(0, $f['monto']), $sinAbono)), 2),
            'personas'         => count($filas),
            'con_cci'          => $conCci,
        ];
    }

    /** Las hojas del Excel: «Abonos» (por entidad, con subtotales), «Sin abono» y «Revisar». */
    public static function hojas(LibroExcel $libro, array $r, bool $conCci): void
    {
        $titulos = array_values(array_filter(['N°', 'DNI', 'Apellidos y nombres', 'Banco o caja', 'N° de cuenta', $conCci ? 'CCI' : null, 'Monto S/'], fn ($t) => $t !== null));
        $filas = [];
        $estiloFilas = [];
        $n = 0;
        $vacias = fn (int $k) => array_fill(0, $k, '');

        foreach ($r['grupos'] as $entidad => $g) {
            foreach ($g['filas'] as $f) {
                $filas[] = array_merge([++$n, $f['dni'], $f['nombre'], $entidad, $f['cuenta']], $conCci ? [$f['cci']] : [], [$f['monto']]);
            }
            $subtotal = array_merge($vacias(2), ['Subtotal ' . $entidad . ' (' . count($g['filas']) . ')'], $vacias($conCci ? 3 : 2), [$g['total']]);
            $filas[] = $subtotal;
            $estiloFilas[count($filas)] = array_map(fn ($v) => is_float($v) ? LibroExcel::TOTAL_MONTO : LibroExcel::TOTAL_TEXTO, $subtotal);
        }
        $total = array_merge($vacias(2), ['TOTAL A ABONAR'], $vacias($conCci ? 3 : 2), [$r['total_abonar']]);
        $filas[] = $total;
        $estiloFilas[count($filas)] = array_map(fn ($v) => is_float($v) ? LibroExcel::TOTAL_MONTO : LibroExcel::TOTAL_TEXTO, $total);

        $columnaMonto = count($titulos) - 1;
        $estilos = [1 => LibroExcel::TEXTO, 4 => LibroExcel::TEXTO, $columnaMonto => LibroExcel::MONTO];
        if ($conCci) {
            $estilos[5] = LibroExcel::TEXTO;
        }
        self::hoja($libro, 'Abonos', $titulos, $filas, $estilos, $estiloFilas);

        $sin = [];
        foreach ($r['sin_abono'] as $i => $f) {
            $sin[] = [$i + 1, $f['dni'], $f['nombre'], $f['monto'], $f['motivo']];
        }
        if (! $sin) {
            $sin[] = ['', '', 'Todos se pagan por abono.', null, ''];
        }
        self::hoja($libro, 'Sin abono', ['N°', 'DNI', 'Apellidos y nombres', 'Monto S/', 'Por qué'], $sin,
            [1 => LibroExcel::TEXTO, 3 => LibroExcel::MONTO]);

        $revisar = array_map(fn ($a, $i) => [$i + 1, $a], $r['alertas'], array_keys($r['alertas']));
        if (! $revisar) {
            $revisar[] = ['', 'Nada que revisar: cada uno tiene su cuenta y ninguna se repite.'];
        }
        self::hoja($libro, 'Revisar', ['N°', 'Antes de pagar'], $revisar);
    }

    private static function hoja(LibroExcel $libro, string $nombre, array $titulos, array $filas, array $estiloColumnas = [], array $estiloFilas = []): void
    {
        $anchos = [];
        foreach ($titulos as $i => $t) {
            $largo = mb_strlen($t);
            foreach (array_slice($filas, 0, 300) as $f) {
                $largo = max($largo, mb_strlen((string) ($f[$i] ?? '')));
            }
            $anchos[$i] = min(70, max(8, $largo + 2));
        }
        $libro->hoja($nombre, array_merge([$titulos], $filas), [
            'anchos' => $anchos, 'estiloColumnas' => $estiloColumnas,
            'estiloFilas' => [0 => LibroExcel::TITULO] + $estiloFilas,
            'altoFilas' => [0 => 26], 'congelarPrimeraFila' => true,
        ]);
    }
}
