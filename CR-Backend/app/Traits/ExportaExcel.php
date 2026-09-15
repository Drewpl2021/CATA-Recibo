<?php

namespace App\Traits;

use App\Support\LibroExcel;

/**
 * Lo común de los dos reportes que se descargan: el personal y la planilla.
 *
 * Antes salían en CSV. Excel los abría, sí, pero con dos estorbos: los montos
 * llegaban como texto (para sumarlos había que convertirlos a mano) y, sobre
 * todo, la lista del personal no se podía volver a subir sin abrirla y
 * guardarla otra vez como .xlsx, que es justo lo que pide la importación.
 * Ahora el .xlsx se arma directamente, con el mismo escritor de los modelos.
 */
trait ExportaExcel
{
    /** Un nombre de archivo sin caracteres que Windows rechace. */
    protected function nombreExcelSeguro(string $texto): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N} \-]+/u', '', $texto)) . '.xlsx';
    }

    /**
     * La hoja de un reporte: títulos en azul, primera fila fija y cada columna
     * tan ancha como lo que lleva dentro.
     *
     * @param  array<int, string>              $titulos
     * @param  array<int, array<int, mixed>>   $filas
     * @param  array<int, int>                 $estiloColumnas  columna => estilo de LibroExcel
     * @param  array<int, int|array<int, int>> $estiloFilas     fila (desde 1) => estilo
     */
    protected function hojaDeReporte(
        LibroExcel $libro,
        string $nombre,
        array $titulos,
        array $filas,
        array $estiloColumnas = [],
        array $estiloFilas = []
    ): void {
        // El ancho sale de las primeras filas: mirar las 46 000 de un reporte
        // grande costaría más que escribirlo.
        $anchos = [];
        foreach ($titulos as $i => $titulo) {
            $largo = mb_strlen((string) $titulo);
            foreach (array_slice($filas, 0, 200) as $fila) {
                $largo = max($largo, mb_strlen((string) ($fila[$i] ?? '')));
            }
            $anchos[$i] = min(38, max(10, $largo + 2));
        }

        $libro->hoja($nombre, array_merge([$titulos], $filas), [
            'anchos'              => $anchos,
            'estiloColumnas'      => $estiloColumnas,
            'estiloFilas'         => [0 => LibroExcel::TITULO] + $estiloFilas,
            'altoFilas'           => [0 => 30],
            'congelarPrimeraFila' => true,
        ]);
    }
}
