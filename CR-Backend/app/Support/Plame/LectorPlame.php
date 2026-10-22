<?php

namespace App\Support\Plame;

use App\Support\LectorDeCeldas;
use App\Support\ReconocedorDeColumnas;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Exceptions\AvisoParaElUsuario;

/**
 * Lee la hoja PLANILLA del PLAME de RR.HH. ("PLANILLA MARZO PLAME 2026 v.3.xls").
 *
 * No es una tabla con una fila de títulos: arriba trae el cuadro de tasas
 * (RMV, AFP), después la cabecera ocupa TRES filas con celdas combinadas
 * («RETENCIONES A CARGO DEL TRABAJADOR» encima de «SNP / ONP», que a su vez
 * cubre dos columnas: la de SI/NO y la del monto), y al final viene la fila
 * de TOTALES. Por eso:
 *
 *   - la cabecera empieza en la fila que dice «APELLIDOS Y NOMBRES»;
 *   - el título de cada columna es el texto MÁS DE ABAJO que la cubre (con
 *     las combinadas repartidas a todas sus columnas), y lo de arriba queda
 *     como su grupo;
 *   - los datos empiezan en la primera fila con un DNI y terminan en TOTALES.
 *
 * Las columnas se buscan por su título y no por su letra: si un mes trae una
 * columna de más (corbatas, un curso), las demás no se corren.
 *
 * El archivo se lee en memoria y no se guarda: lleva el sueldo de todo el
 * colegio.
 */
final class LectorPlame
{
    /** Hasta qué fila se busca la cabecera. */
    private const FILAS_DE_BUSQUEDA = 40;

    /** Una planilla de colegio no pasa de esto; más es que se leyó otra cosa. */
    public const MAX_FILAS = 1500;

    /**
     * @return array{
     *   hoja: string,
     *   columnas: list<array{indice:int, letra:string, titulo:string, grupo:string}>,
     *   filas: list<array{numero:int, celdas:list<mixed>}>
     * }
     */
    public static function leer(string $ruta): array
    {
        try {
            $lector = IOFactory::createReaderForFile($ruta);
        } catch (\Throwable) {
            throw new AvisoParaElUsuario('El archivo no es un Excel (.xls o .xlsx).');
        }
        // Sin setReadDataOnly: en un .xls eso deja fuera las celdas
        // combinadas, y sin ellas la columna del monto de la ONP no tiene título.
        $libro = $lector->load($ruta);

        $hoja = self::hojaDeLaPlanilla($libro->getAllSheets());
        if (! $hoja) {
            throw new AvisoParaElUsuario('No se encontró la hoja de la planilla: ninguna hoja tiene las columnas «APELLIDOS Y NOMBRES» y «DNI».');
        }

        [$hojaPlanilla, $filaCabecera] = $hoja;
        $ultimaColumna = Coordinate::columnIndexFromString($hojaPlanilla->getHighestDataColumn());
        $ultimaFila    = $hojaPlanilla->getHighestDataRow();

        // Cada celda combinada, repartida a todas las celdas que cubre.
        $combinadas = [];
        foreach ($hojaPlanilla->getMergeCells() as $rango) {
            [$desde, $hasta] = Coordinate::rangeBoundaries($rango);
            $valor = self::valor($hojaPlanilla->getCell([$desde[0], $desde[1]]));
            for ($f = $desde[1]; $f <= $hasta[1]; $f++) {
                for ($c = $desde[0]; $c <= $hasta[0]; $c++) {
                    $combinadas["{$c}:{$f}"] = $valor;
                }
            }
        }
        $texto = function (int $columna, int $fila) use ($hojaPlanilla, $combinadas): string {
            $valor = $combinadas["{$columna}:{$fila}"] ?? self::valor($hojaPlanilla->getCell([$columna, $fila]));

            return is_string($valor) ? trim(preg_replace('/\s+/u', ' ', $valor)) : '';
        };

        // La columna del DNI, para saber dónde empiezan los datos.
        $columnaDni = null;
        for ($f = $filaCabecera; $f <= $filaCabecera + 4 && ! $columnaDni; $f++) {
            for ($c = 1; $c <= $ultimaColumna; $c++) {
                if (ReconocedorDeColumnas::normalizar($texto($c, $f)) === 'dni') {
                    $columnaDni = $c;
                    break;
                }
            }
        }

        $primeraDeDatos = null;
        for ($f = $filaCabecera + 1; $f <= min($ultimaFila, $filaCabecera + 10); $f++) {
            if ($columnaDni && self::pareceDni(self::valor($hojaPlanilla->getCell([$columnaDni, $f])))) {
                $primeraDeDatos = $f;
                break;
            }
        }
        if (! $primeraDeDatos) {
            throw new AvisoParaElUsuario('La hoja «' . $hojaPlanilla->getTitle() . '» no tiene filas de trabajadores debajo de los títulos.');
        }

        // ── Las columnas ─────────────────────────────────────────
        $columnas = [];
        for ($c = 1; $c <= $ultimaColumna; $c++) {
            $textos = [];
            for ($f = $filaCabecera; $f < $primeraDeDatos; $f++) {
                $t = $texto($c, $f);
                if ($t !== '' && end($textos) !== $t) {
                    $textos[] = $t;
                }
            }
            $titulo = $textos ? array_pop($textos) : '';
            $columnas[] = [
                'indice' => $c - 1,
                'letra'  => Coordinate::stringFromColumnIndex($c),
                'titulo' => $titulo,
                'grupo'  => implode(' · ', $textos),
            ];
        }

        // ── Las filas, hasta TOTALES ─────────────────────────────
        $filas = [];
        $vaciasSeguidas = 0;
        for ($f = $primeraDeDatos; $f <= $ultimaFila; $f++) {
            $celdas = [];
            for ($c = 1; $c <= $ultimaColumna; $c++) {
                $celdas[] = self::valor($hojaPlanilla->getCell([$c, $f]));
            }

            $esTotal = collect(array_slice($celdas, 0, 6))
                ->contains(fn ($v) => is_string($v) && str_starts_with(ReconocedorDeColumnas::normalizar($v), 'total'));
            if ($esTotal) {
                break;
            }

            if (LectorDeCeldas::filaVacia($celdas)) {
                if (++$vaciasSeguidas >= 3) {
                    break;
                }
                continue;
            }
            $vaciasSeguidas = 0;

            $filas[] = ['numero' => $f, 'celdas' => $celdas];
            if (count($filas) > self::MAX_FILAS) {
                throw new AvisoParaElUsuario('La hoja tiene más de ' . self::MAX_FILAS . ' filas: no parece un PLAME.');
            }
        }

        return ['hoja' => $hojaPlanilla->getTitle(), 'columnas' => $columnas, 'filas' => $filas];
    }

    /**
     * La hoja que tiene la planilla y la fila donde empieza su cabecera.
     * Primero la que se llama PLANILLA; si no, la primera que tenga los títulos.
     *
     * @param  Worksheet[] $hojas
     * @return array{0: Worksheet, 1: int}|null
     */
    private static function hojaDeLaPlanilla(array $hojas): ?array
    {
        usort($hojas, fn ($a, $b) => (ReconocedorDeColumnas::normalizar($b->getTitle()) === 'planilla')
            <=> (ReconocedorDeColumnas::normalizar($a->getTitle()) === 'planilla'));

        foreach ($hojas as $hoja) {
            $ultimaColumna = min(Coordinate::columnIndexFromString($hoja->getHighestDataColumn()), 120);
            $ultimaFila    = min($hoja->getHighestDataRow(), self::FILAS_DE_BUSQUEDA);

            for ($f = 1; $f <= $ultimaFila; $f++) {
                $normales = [];
                for ($c = 1; $c <= $ultimaColumna; $c++) {
                    $v = $hoja->getCell([$c, $f])->getValue();
                    if (is_string($v)) {
                        $normales[] = ReconocedorDeColumnas::normalizar($v);
                    }
                }
                if (in_array('apellidos y nombres', $normales, true)) {
                    return [$hoja, $f];
                }
            }
        }

        return null;
    }

    /** Lo que muestra la celda. Una fórmula, con el resultado que Excel dejó guardado. */
    private static function valor(Cell $celda): mixed
    {
        $valor = $celda->getValue();

        if ($celda->getDataType() === DataType::TYPE_FORMULA) {
            $valor = $celda->getOldCalculatedValue();
            if ($valor === null) {
                try {
                    $valor = $celda->getCalculatedValue();
                } catch (\Throwable) {
                    $valor = null;
                }
            }
        }
        if ($valor instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $valor = $valor->getPlainText();
        }
        if (is_string($valor) && trim($valor) === '') {
            return null;
        }

        return $valor;
    }

    private static function pareceDni(mixed $valor): bool
    {
        $dni = LectorDeCeldas::dni($valor);

        return ! $dni['vacio'] && (bool) preg_match('/^[0-9]{6,9}$/', $dni['claves'][0]);
    }
}
