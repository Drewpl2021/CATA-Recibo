<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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

        $resultado = $pagina * $porPagina >= self::DESDE_AQUI_PAGINAR_POR_LLAVES
            ? $this->paginarPorLlaves($query, $porPagina, $pagina)
            : $query->paginate($porPagina, ['*'], 'page', $pagina + 1);

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
    /**
     * A partir de cuántas filas saltadas se pagina por llaves.
     *
     * Con un OFFSET grande, MySQL trae enteras TODAS las filas que se salta
     * para después tirarlas: la página 2 000 de Documentos leía 20 000 filas
     * completas para enseñar diez (81 ms). Por debajo de esto el OFFSET de
     * siempre es más barato que la consulta de más.
     */
    private const DESDE_AQUI_PAGINAR_POR_LLAVES = 1000;

    /**
     * La página en dos pasos: primero los ids, después sus filas.
     *
     * Los ids de la página salen del índice sin tocar la tabla —el mismo
     * salto de 20 000, pero sobre entradas de índice y no sobre filas—, y
     * luego se traen solo esas diez. De 81 ms a 6 en la página 2 000.
     */
    private function paginarPorLlaves(Builder $query, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $modelo = $query->getModel();
        $llave  = $modelo->getQualifiedKeyName();

        $total = (clone $query)->toBase()->getCountForPagination();

        $ids = (clone $query)->toBase()
            ->select($llave)
            ->forPage($pagina + 1, $porPagina)
            ->pluck($modelo->getKeyName());

        // Mismo orden que la consulta original: el whereIn no lo cambia y
        // los orderBy siguen puestos en el builder.
        $filas = $ids->isEmpty() ? $modelo->newCollection() : (clone $query)->whereIn($llave, $ids)->get();

        return new LengthAwarePaginator($filas, $total, $porPagina, $pagina + 1);
    }

    protected function conteoPorEstado(Builder $query, string $columna, array $valores): array
    {
        /*
         * UNA consulta agrupada, no una por estado.
         *
         * Antes era un COUNT del total y otro por cada estado, y cada uno
         * volvía a recorrer todo lo filtrado: con 46 000 documentos eran
         * tres recorridos para tres números que salen del mismo. Agrupando
         * por la columna se hace una vez, y el total es la suma de los
         * grupos —incluidos los valores que no se piden por separado—.
         */
        $grupos = (clone $query)->reorder()->toBase()
            ->select($columna . ' as valor_del_estado', DB::raw('COUNT(*) as cuantos'))
            ->groupBy($columna)
            ->pluck('cuantos', 'valor_del_estado');

        $conteos = ['total' => (int) $grupos->sum()];

        foreach ($valores as $etiqueta => $valor) {
            $conteos[$etiqueta] = (int) ($grupos[$valor] ?? 0);
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

        $patron = "%{$termino}%";

        $query->where(function (Builder $q) use ($buscarEn, $patron) {
            // Las columnas de una misma relación se juntan: "empleado.nombre",
            // "empleado.apellido" y "empleado.dni" son UNA subconsulta sobre
            // empleados, no tres.
            $porRelacion = [];

            foreach ($buscarEn as $campo) {
                // "=tipo": la palabra tiene que ser exactamente esa. Sirve para
                // columnas de pocos valores fijos ("boleta", "contrato"): un
                // like '%x%' sobre ellas no encuentra nada útil y, metido en
                // el OR, obliga a MySQL a recorrer la tabla entera. La
                // igualdad sí va por índice.
                //
                // Y solo se añade si el término ES uno de esos valores. Hasta
                // una igualdad, metida en el OR, le impide a MySQL entrar por
                // la subconsulta de empleados y lo manda a recorrer todos los
                // documentos: buscar "mamani" costaba 254 ms por una rama que
                // nunca iba a encontrar nada. Preguntar si existe va por
                // índice (0,1 ms) y deja la búsqueda en 16 ms.
                if (str_starts_with($campo, '=')) {
                    $columna = substr($campo, 1);
                    $valor   = trim($patron, '%');

                    if ($q->getModel()->newQuery()->where($columna, $valor)->exists()) {
                        $q->orWhere($columna, '=', $valor);
                    }
                    continue;
                }

                if (!str_contains($campo, '.')) {
                    $q->orWhere($campo, 'like', $patron);
                    continue;
                }

                [$relacion, $columna] = explode('.', $campo, 2);
                $porRelacion[$relacion][] = $columna;
            }

            foreach ($porRelacion as $relacion => $columnas) {
                $coincide = function (Builder $r) use ($columnas, $patron) {
                    $r->where(function (Builder $w) use ($columnas, $patron) {
                        foreach ($columnas as $columna) {
                            $w->orWhere($columna, 'like', $patron);
                        }
                    });
                };

                $rel = $q->getModel()->{$relacion}();

                /*
                 * "Los documentos cuyo empleado se llame así" se pedía con
                 * whereHas, que MySQL resuelve con una subconsulta POR FILA:
                 * con 46 000 boletas eran 46 000 × 3 búsquedas en empleados,
                 * 840 ms por teclear un apellido. Se da la vuelta: primero se
                 * buscan los empleados que coinciden (unos pocos, sobre una
                 * tabla chica) y después se toman sus documentos por la llave,
                 * que tiene índice.
                 */
                if ($rel instanceof BelongsTo) {
                    $sub = $rel->getRelated()->newQuery()->select($rel->getOwnerKeyName());
                    $coincide($sub);
                    $q->orWhereIn($rel->getQualifiedForeignKeyName(), $sub);
                } elseif ($rel instanceof HasOneOrMany) {
                    $sub = $rel->getRelated()->newQuery()->select($rel->getForeignKeyName());
                    $coincide($sub);
                    $q->orWhereIn($rel->getQualifiedParentKeyName(), $sub);
                } else {
                    // Cualquier otra relación, como antes.
                    $q->orWhereHas($relacion, $coincide);
                }
            }
        });
    }
}
