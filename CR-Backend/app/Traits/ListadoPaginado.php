<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listados con paginación y búsqueda resueltas en la base de datos.
 *
 * El problema que resuelve: hasta ahora cada index() devolvía la tabla
 * entera y el navegador se encargaba de cortarla en páginas y de filtrar
 * el buscador. Con 20 áreas da igual; con tres años de planillas
 * significa mandar miles de filas por la red para mostrar diez.
 *
 * Cómo se activa: SOLO si el cliente manda ?page. Sin ese parámetro el
 * endpoint sigue devolviendo la lista completa, que es lo que necesitan
 * los desplegables de los formularios (elegir un área, un empleado, una
 * sede) — a un <select> no se le pagina.
 *
 *   GET /areas                  -> data: [ ...todas... ]
 *   GET /areas?page=0&size=10   -> data: { content, totalElements,
 *                                          currentPage, totalPages }
 *
 * La página va en base 0, como la manda el frontend; Laravel las cuenta
 * desde 1, y la conversión se hace acá adentro para que ni el cliente ni
 * cada controlador tengan que acordarse.
 */
trait ListadoPaginado
{
    /** Techo de filas por página: evita que un ?size=100000 tumbe el servidor. */
    private const MAXIMO_POR_PAGINA = 200;

    /**
     * @param  Builder        $query    consulta ya filtrada por el controlador
     * @param  string[]       $buscarEn columnas sobre las que actúa ?search
     * @param  callable|null  $resumen  cifras del conjunto COMPLETO (no de la
     *                                  página): recibe la consulta ya filtrada
     *                                  y devuelve un arreglo que se añade a la
     *                                  respuesta. Sirve para totales como la
     *                                  masa salarial, que sumando solo las diez
     *                                  filas de la página saldrían mal.
     */
    protected function responderListado(
        Request $request,
        Builder $query,
        array $buscarEn = [],
        ?callable $resumen = null
    ): JsonResponse {
        $this->aplicarBusqueda($request, $query, $buscarEn);

        // Se calcula ANTES de paginar y sobre una copia, porque paginate()
        // ejecuta la consulta y le añade su propio limit.
        $extras = $resumen ? $resumen(clone $query) : [];

        // Sin ?page el contrato es el de siempre: un arreglo pelado.
        if (!$request->filled('page') && !$request->filled('size')) {
            return response()->json(['success' => true, 'data' => $query->get()]);
        }

        $porPagina = (int) $request->input('size', 10);
        $porPagina = max(1, min($porPagina, self::MAXIMO_POR_PAGINA));
        $pagina    = max((int) $request->input('page', 0), 0);

        $resultado = $query->paginate($porPagina, ['*'], 'page', $pagina + 1);

        return response()->json([
            'success' => true,
            'data'    => array_merge([
                'content'       => $resultado->items(),
                'totalElements' => $resultado->total(),
                'currentPage'   => $resultado->currentPage() - 1,
                'totalPages'    => $resultado->lastPage(),
            ], $extras),
        ]);
    }

    /**
     * Cuenta cuántos registros hay de cada valor de una columna, sobre el
     * conjunto YA filtrado y antes de paginar.
     *
     * Es lo que alimenta las cifras de la cabecera ("18 en total, 15 activas,
     * 3 inactivas"): contarlas en el navegador daría solo las de la página
     * que se está viendo.
     *
     * Cada conteo va sobre su propia copia porque un where() muta el builder
     * y el segundo saldría filtrado por el primero.
     *
     * @param  array<string,string>  $valores  etiqueta en la respuesta => valor en la columna
     */
    protected function conteoPorEstado(Builder $query, string $columna, array $valores): array
    {
        $conteos = ['total' => (clone $query)->reorder()->count()];

        foreach ($valores as $etiqueta => $valor) {
            $conteos[$etiqueta] = (clone $query)->reorder()->where($columna, $valor)->count();
        }

        return $conteos;
    }

    /**
     * Búsqueda por texto sobre las columnas que declare el controlador.
     *
     * Va entre paréntesis a propósito: sin el closure, el orWhere se
     * escaparía de los filtros anteriores y "?estado=activo&search=x"
     * devolvería también los inactivos que coincidan con la búsqueda.
     *
     * Acepta "empleado.nombre" para buscar dentro de una relación.
     */
    private function aplicarBusqueda(Request $request, Builder $query, array $buscarEn): void
    {
        $termino = trim((string) $request->input('search', ''));

        if ($termino === '' || empty($buscarEn)) {
            return;
        }

        $query->where(function (Builder $q) use ($buscarEn, $termino) {
            foreach ($buscarEn as $campo) {
                if (!str_contains($campo, '.')) {
                    $q->orWhere($campo, 'like', "%{$termino}%");
                    continue;
                }

                [$relacion, $columna] = explode('.', $campo, 2);
                $q->orWhereHas($relacion, function (Builder $r) use ($columna, $termino) {
                    $r->where($columna, 'like', "%{$termino}%");
                });
            }
        });
    }
}
