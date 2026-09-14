<?php

namespace App\Traits;

/**
 * Armar un CSV que Excel abra bien.
 *
 * Vive acá y no copiado en cada controlador porque ya son dos los que
 * exportan —la planilla y el personal— y las reglas de un CSV no son
 * opinables: el día que haya que tocar el escape, se toca una vez.
 */
trait ExportaCsv
{
    /**
     * Una fila de CSV.
     *
     * Se arma a mano y no con fputcsv porque esa función mete su propio
     * escape con barras invertidas y en PHP 8.4 cambió de comportamiento.
     * Acá solo hay una regla, la del estándar: las comillas se duplican.
     */
    protected function filaCsv(array $campos): string
    {
        $celdas = array_map(function ($valor) {
            $texto = (string) $valor;

            return str_contains($texto, '"') || str_contains($texto, ',') || str_contains($texto, "\n")
                ? '"' . str_replace('"', '""', $texto) . '"'
                : $texto;
        }, $campos);

        return implode(',', $celdas) . "\r\n";
    }

    /**
     * El BOM de UTF-8.
     *
     * Sin él, Excel abre el archivo como Latin-1 y los apellidos con tilde y
     * con ñ salen rotos ("Mu-oz"). En un colegio de Juliaca eso es la mitad
     * de la lista.
     */
    protected function bomUtf8(): string
    {
        return "\xEF\xBB\xBF";
    }

    /** Un nombre de archivo sin caracteres que Windows rechace. */
    protected function nombreCsvSeguro(string $texto): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N} \-]+/u', '', $texto)) . '.csv';
    }
}
