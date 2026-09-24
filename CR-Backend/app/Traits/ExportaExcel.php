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
    /**
     * La hoja "Filtros": con qué se filtró, quién lo bajó y cuándo.
     *
     * Sin esto, dos archivos con el mismo nombre —uno de una sede y otro de
     * todo el colegio— no se distinguen una semana después, y el que revisa
     * no tiene cómo saber si le falta gente o es que se filtró.
     *
     * Se agrega SIEMPRE al final del libro: la importación de empleados lee
     * la primera hoja, así que la de datos tiene que seguir siendo la
     * primera.
     *
     * @param  array<string, string|null>  $filtros  etiqueta => lo que se eligió
     */
    protected function hojaDeFiltros(LibroExcel $libro, array $filtros, ?string $quien = null): void
    {
        $filas = [['Filtro', 'Lo que se eligió']];

        foreach ($filtros as $etiqueta => $valor) {
            if ($valor !== null && $valor !== '') {
                $filas[] = [$etiqueta, (string) $valor];
            }
        }

        if (count($filas) === 1) {
            $filas[] = ['Sin filtros', 'Salió todo lo que hay registrado'];
        }

        $filas[] = ['', ''];
        $filas[] = ['Descargado el', now()->format('d/m/Y H:i')];

        if ($quien) {
            $filas[] = ['Descargado por', $quien];
        }

        $libro->hoja('Filtros', $filas, [
            'anchos'      => [0 => 28, 1 => 46],
            'estiloFilas' => [0 => LibroExcel::TITULO],
            'altoFilas'   => [0 => 26],
        ]);
    }

    /** El nombre de un área, cargo o sede a partir de su id. */
    protected function nombreDeCatalogo(string $clase, mixed $id): ?string
    {
        if (! $id) {
            return null;
        }

        return $clase::find($id)?->nombre ?? (string) $id;
    }

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
