<?php

namespace App\Support;

use RuntimeException;

/**
 * Lo justo de un PDF para poder agregarle una firma al final (ver
 * FirmadorPdf): dónde está cada objeto según sus tablas de referencias, el
 * trailer más reciente, y leer o rearmar diccionarios como texto.
 *
 * No interpreta páginas ni contenido: solo la estructura. Entiende la tabla
 * clásica («xref» + «trailer», la de dompdf) y la comprimida (xref stream,
 * con objetos dentro de object streams), siguiendo la cadena /Prev de las
 * actualizaciones anteriores — que es como queda un PDF ya firmado.
 */
final class LectorPdf
{
    /** @var array<int, array{0: int, 1: int, 2: int}> número => [tipo, dato1, dato2] (1: posición y generación; 2: object stream e índice) */
    private array $tabla = [];

    /** @var array<string, string> */
    private array $trailer = [];

    private int $inicioTabla;

    /** @var array<int, array{datos: string, objetos: array<int, int>}> object streams ya abiertos */
    private array $flujos = [];

    public function __construct(private readonly string $pdf)
    {
        if (! str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('No es un PDF.');
        }
        if (! preg_match_all('/startxref\s+(\d+)/', $pdf, $m)) {
            throw new RuntimeException('El PDF no tiene tabla de referencias (startxref).');
        }
        $this->inicioTabla = (int) end($m[1]);
        $this->cargarTablas($this->inicioTabla);
    }

    /** @return array<string, string> el trailer más reciente (o el diccionario de su xref stream). */
    public function trailer(): array
    {
        return $this->trailer;
    }

    /** Dónde empieza la última tabla de referencias: el /Prev de la que se agregue. */
    public function inicioDeLaTabla(): int
    {
        return $this->inicioTabla;
    }

    public function generacion(int $num): int
    {
        return ($this->tabla[$num][0] ?? 0) === 1 ? $this->tabla[$num][2] : 0;
    }

    /** @return array{0: int, 1: int}|null número y generación, si el valor es «12 0 R». */
    public function referencia(?string $valor): ?array
    {
        return $valor !== null && preg_match('/^\s*(\d+)\s+(\d+)\s+R\s*$/', $valor, $m) ? [(int) $m[1], (int) $m[2]] : null;
    }

    /** El valor tal cual, o el contenido del objeto si es una referencia. */
    public function valor(string $valor): string
    {
        $ref = $this->referencia($valor);

        return $ref ? trim($this->contenido($ref[0])) : trim($valor);
    }

    /** @return array<string, string> */
    public function diccionario(int $num): array
    {
        $contenido = trim($this->contenido($num));
        if (! str_starts_with($contenido, '<<')) {
            throw new RuntimeException("El objeto {$num} del PDF no es un diccionario.");
        }

        return self::partirDiccionario($contenido);
    }

    /** El objeto como texto: lo que va entre «N G obj» y «endobj» (sin su stream). */
    public function contenido(int $num): string
    {
        $entrada = $this->tabla[$num] ?? throw new RuntimeException("El PDF no tiene el objeto {$num}.");

        if ($entrada[0] === 2) {
            return $this->delFlujo($entrada[1], $entrada[2], $num);
        }
        if ($entrada[0] !== 1) {
            throw new RuntimeException("El objeto {$num} del PDF está borrado.");
        }

        $pos = $entrada[1];
        if (! preg_match('/\G\s*(\d+)\s+(\d+)\s+obj/', $this->pdf, $m, 0, $pos) || (int) $m[1] !== $num) {
            throw new RuntimeException("El objeto {$num} no está donde el PDF dice.");
        }
        $pos += strlen($m[0]);

        return self::leerValor($this->pdf, $pos);
    }

    // ── Tablas de referencias ─────────────────────────────────────────

    private function cargarTablas(int $pos): void
    {
        $vistas = [];
        $pendientes = [$pos];
        while ($pendientes) {
            $pos = array_shift($pendientes);
            if (isset($vistas[$pos]) || count($vistas) > 500) {
                continue;
            }
            $vistas[$pos] = true;

            $dic = preg_match('/\G\s*xref\b/', $this->pdf, $m, 0, $pos)
                ? $this->tablaClasica($pos + strlen($m[0]))
                : $this->tablaComprimida($pos);

            if (! $this->trailer) {
                $this->trailer = $dic;
            }
            // Un PDF «híbrido» trae las dos: primero la comprimida, después la anterior.
            foreach (['XRefStm', 'Prev'] as $clave) {
                if (isset($dic[$clave]) && ctype_digit(trim($dic[$clave]))) {
                    $pendientes[] = (int) trim($dic[$clave]);
                }
            }
        }
    }

    /** @return array<string, string> su trailer */
    private function tablaClasica(int $pos): array
    {
        while (preg_match('/\G\s*(\d+)\s+(\d+)[ \t]*\r?\n/', $this->pdf, $m, 0, $pos)) {
            $pos += strlen($m[0]);
            [$primero, $cuantos] = [(int) $m[1], (int) $m[2]];
            for ($i = 0; $i < $cuantos; $i++) {
                if (! preg_match('/\G\s*(\d{1,10})\s+(\d{1,5})\s+([nf])[ \t]*\r?\n?/', $this->pdf, $e, 0, $pos)) {
                    throw new RuntimeException('La tabla de referencias del PDF está dañada.');
                }
                $pos += strlen($e[0]);
                // La más nueva manda: las anteriores solo llenan lo que falta.
                $this->tabla[$primero + $i] ??= $e[3] === 'n' ? [1, (int) $e[1], (int) $e[2]] : [0, 0, 0];
            }
        }
        if (! preg_match('/\G\s*trailer\s*/', $this->pdf, $m, 0, $pos)) {
            throw new RuntimeException('El PDF no tiene trailer.');
        }
        $pos += strlen($m[0]);

        return self::partirDiccionario(self::leerValor($this->pdf, $pos));
    }

    /** @return array<string, string> el diccionario de la xref stream (hace de trailer) */
    private function tablaComprimida(int $pos): array
    {
        if (! preg_match('/\G\s*\d+\s+\d+\s+obj/', $this->pdf, $m, 0, $pos)) {
            throw new RuntimeException('No se encontró la tabla de referencias del PDF.');
        }
        $pos += strlen($m[0]);
        $dic = self::partirDiccionario(self::leerValor($this->pdf, $pos));
        if (trim($dic['Type'] ?? '') !== '/XRef') {
            throw new RuntimeException('No se encontró la tabla de referencias del PDF.');
        }
        $datos = $this->decodificar($dic, $this->datosDelFlujo($dic, $pos));

        $anchos = array_map('intval', preg_split('/\s+/', trim($dic['W'] ?? '', '[] ')));
        if (count($anchos) !== 3) {
            throw new RuntimeException('La tabla de referencias comprimida del PDF está dañada.');
        }
        $indice = isset($dic['Index'])
            ? array_map('intval', preg_split('/\s+/', trim($dic['Index'], '[] ')))
            : [0, (int) trim($dic['Size'] ?? '0')];

        $paso = array_sum($anchos);
        $p = 0;
        for ($k = 0; $k + 1 < count($indice); $k += 2) {
            for ($i = 0; $i < $indice[$k + 1]; $i++, $p += $paso) {
                $campo = function (int $n) use ($datos, $anchos, $p): ?int {
                    if ($anchos[$n] === 0) {
                        return null;
                    }
                    $inicio = $p + array_sum(array_slice($anchos, 0, $n));
                    $v = 0;
                    for ($b = 0; $b < $anchos[$n]; $b++) {
                        $v = ($v << 8) | ord($datos[$inicio + $b] ?? "\0");
                    }

                    return $v;
                };
                $tipo = $campo(0) ?? 1;
                $this->tabla[$indice[$k] + $i] ??= match ($tipo) {
                    1       => [1, (int) $campo(1), (int) ($campo(2) ?? 0)],
                    2       => [2, (int) $campo(1), (int) $campo(2)],
                    default => [0, 0, 0],
                };
            }
        }

        return $dic;
    }

    /** Un objeto guardado dentro de un object stream. */
    private function delFlujo(int $flujo, int $indice, int $num): string
    {
        if (! isset($this->flujos[$flujo])) {
            $entrada = $this->tabla[$flujo] ?? null;
            if (($entrada[0] ?? null) !== 1 || ! preg_match('/\G\s*\d+\s+\d+\s+obj/', $this->pdf, $m, 0, $entrada[1])) {
                throw new RuntimeException("No se encontró el grupo de objetos {$flujo} del PDF.");
            }
            $pos = $entrada[1] + strlen($m[0]);
            $dic = self::partirDiccionario(self::leerValor($this->pdf, $pos));
            $datos = $this->decodificar($dic, $this->datosDelFlujo($dic, $pos));
            $primero = (int) trim($dic['First'] ?? '0');
            $pares = preg_split('/\s+/', trim(substr($datos, 0, $primero)));
            $objetos = [];
            for ($i = 0; $i + 1 < count($pares); $i += 2) {
                $objetos[(int) $pares[$i]] = $primero + (int) $pares[$i + 1];
            }
            $this->flujos[$flujo] = ['datos' => $datos, 'objetos' => $objetos];
        }

        $pos = $this->flujos[$flujo]['objetos'][$num] ?? throw new RuntimeException("El objeto {$num} no está en su grupo.");

        return self::leerValor($this->flujos[$flujo]['datos'], $pos);
    }

    /** Los bytes crudos de un stream: lo que hay entre «stream» y «endstream». */
    private function datosDelFlujo(array $dic, int $pos): string
    {
        if (! preg_match('/\G\s*stream\r?\n/', $this->pdf, $m, 0, $pos)) {
            throw new RuntimeException('Falta el contenido de un stream del PDF.');
        }
        $inicio = $pos + strlen($m[0]);
        $largo = trim($dic['Length'] ?? '');
        if (ctype_digit($largo)) {
            return substr($this->pdf, $inicio, (int) $largo);
        }
        $fin = strpos($this->pdf, 'endstream', $inicio);

        return rtrim(substr($this->pdf, $inicio, $fin === false ? null : $fin - $inicio), "\r\n");
    }

    private function decodificar(array $dic, string $datos): string
    {
        $filtro = trim($dic['Filter'] ?? '', '[] ');
        if ($filtro === '') {
            return $datos;
        }
        if ($filtro !== '/FlateDecode') {
            throw new RuntimeException("El PDF usa una compresión que no se entiende ({$filtro}).");
        }
        $plano = @gzuncompress($datos);
        if ($plano === false) {
            throw new RuntimeException('No se pudo descomprimir parte del PDF.');
        }

        $parametros = isset($dic['DecodeParms']) ? self::partirDiccionario(trim($dic['DecodeParms'], '[] ')) : [];
        $predictor = (int) trim($parametros['Predictor'] ?? '1');

        return $predictor >= 10 ? self::quitarPredictorPng($plano, (int) trim($parametros['Columns'] ?? '1')) : $plano;
    }

    /** El «predictor PNG» de las tablas comprimidas: cada fila guarda la diferencia con la anterior. */
    private static function quitarPredictorPng(string $datos, int $columnas): string
    {
        $salida = '';
        $anterior = array_fill(0, $columnas, 0);
        for ($p = 0; $p < strlen($datos); $p += $columnas + 1) {
            $tipo = ord($datos[$p]);
            $fila = [];
            for ($i = 0; $i < $columnas; $i++) {
                $x = ord($datos[$p + 1 + $i] ?? "\0");
                $a = $i > 0 ? $fila[$i - 1] : 0;
                $b = $anterior[$i];
                $c = $i > 0 ? $anterior[$i - 1] : 0;
                $fila[$i] = match ($tipo) {
                    1 => ($x + $a) & 0xFF,
                    2 => ($x + $b) & 0xFF,
                    3 => ($x + intdiv($a + $b, 2)) & 0xFF,
                    4 => ($x + self::paeth($a, $b, $c)) & 0xFF,
                    default => $x,
                };
            }
            $salida .= implode('', array_map('chr', $fila));
            $anterior = $fila;
        }

        return $salida;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        [$pa, $pb, $pc] = [abs($p - $a), abs($p - $b), abs($p - $c)];

        return $pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c);
    }

    // ── Texto de los objetos ───────────────────────────────────────────

    /**
     * Un objeto PDF desde $pos, como texto tal cual (un diccionario, un
     * arreglo, un nombre, un número, una referencia…). Avanza $pos.
     */
    public static function leerValor(string $s, int &$pos): string
    {
        $largo = strlen($s);
        self::saltarEspacios($s, $pos);
        $inicio = $pos;
        $c = $s[$pos] ?? '';

        if ($c === '<' && ($s[$pos + 1] ?? '') === '<' || $c === '[') {
            $pila = 0;
            while ($pos < $largo) {
                $c = $s[$pos];
                if ($c === '(') {
                    self::saltarTexto($s, $pos);
                    continue;
                }
                if ($c === '%') {
                    while ($pos < $largo && $s[$pos] !== "\n" && $s[$pos] !== "\r") {
                        $pos++;
                    }
                    continue;
                }
                if ($c === '<' && ($s[$pos + 1] ?? '') === '<') {
                    $pila++;
                    $pos += 2;
                } elseif ($c === '>' && ($s[$pos + 1] ?? '') === '>') {
                    $pila--;
                    $pos += 2;
                } elseif ($c === '[') {
                    $pila++;
                    $pos++;
                } elseif ($c === ']') {
                    $pila--;
                    $pos++;
                } elseif ($c === '<') {
                    $fin = strpos($s, '>', $pos);
                    $pos = $fin === false ? $largo : $fin + 1;
                } else {
                    $pos++;
                }
                if ($pila === 0) {
                    return substr($s, $inicio, $pos - $inicio);
                }
            }
            throw new RuntimeException('El PDF tiene un objeto sin cerrar.');
        }
        if ($c === '(') {
            self::saltarTexto($s, $pos);

            return substr($s, $inicio, $pos - $inicio);
        }
        if ($c === '<') {
            $fin = strpos($s, '>', $pos);
            $pos = $fin === false ? $largo : $fin + 1;

            return substr($s, $inicio, $pos - $inicio);
        }
        if ($c === '/') {
            $pos++;
            while ($pos < $largo && ! str_contains(" \t\r\n\f\0()<>[]{}/%", $s[$pos])) {
                $pos++;
            }

            return substr($s, $inicio, $pos - $inicio);
        }
        if (preg_match('/\G\d+\s+\d+\s+R(?![A-Za-z])/', $s, $m, 0, $pos)) {
            $pos += strlen($m[0]);

            return $m[0];
        }
        if (preg_match('/\G[^\s\/\[\]<>()%{}]+/', $s, $m, 0, $pos)) {
            $pos += strlen($m[0]);

            return $m[0];
        }

        throw new RuntimeException('El PDF tiene algo que no se entiende.');
    }

    /** @return array<string, string> clave (sin la barra) => valor tal cual, en su orden. */
    public static function partirDiccionario(string $dic): array
    {
        $dic = trim($dic);
        if (! str_starts_with($dic, '<<')) {
            return [];
        }
        $pos = 2;
        $claves = [];
        while (true) {
            self::saltarEspacios($dic, $pos);
            if (substr($dic, $pos, 2) === '>>' || $pos >= strlen($dic)) {
                return $claves;
            }
            $clave = self::leerValor($dic, $pos);
            if (! str_starts_with($clave, '/')) {
                throw new RuntimeException('El PDF tiene un diccionario que no se entiende.');
            }
            $claves[substr($clave, 1)] = self::leerValor($dic, $pos);
        }
    }

    /** @param array<string, string> $claves */
    public static function armarDiccionario(array $claves): string
    {
        $partes = [];
        foreach ($claves as $clave => $valor) {
            $partes[] = "/{$clave} {$valor}";
        }

        return '<< ' . implode(' ', $partes) . ' >>';
    }

    /** Un texto del PDF que acepta tildes y eñes: UTF-16 con su marca, en hexadecimal. */
    public static function texto(string $texto): string
    {
        return '<FEFF' . strtoupper(bin2hex(mb_convert_encoding($texto, 'UTF-16BE', 'UTF-8'))) . '>';
    }

    private static function saltarEspacios(string $s, int &$pos): void
    {
        $largo = strlen($s);
        while ($pos < $largo) {
            if (str_contains(" \t\r\n\f\0", $s[$pos])) {
                $pos++;
            } elseif ($s[$pos] === '%') {
                while ($pos < $largo && $s[$pos] !== "\n" && $s[$pos] !== "\r") {
                    $pos++;
                }
            } else {
                return;
            }
        }
    }

    /** Un texto entre paréntesis, con sus escapes y paréntesis anidados. */
    private static function saltarTexto(string $s, int &$pos): void
    {
        $largo = strlen($s);
        $pila = 0;
        while ($pos < $largo) {
            $c = $s[$pos];
            if ($c === '\\') {
                $pos += 2;
                continue;
            }
            $pos++;
            if ($c === '(') {
                $pila++;
            } elseif ($c === ')' && --$pila === 0) {
                return;
            }
        }
    }
}
