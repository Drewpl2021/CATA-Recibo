<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Traits\ListadoPaginado;
use Illuminate\Http\Request;

/**
 * El registro de auditoría: solo lectura, solo Admin.
 *
 * No hay store, update ni destroy a propósito. Las filas las escribe el
 * propio sistema al pasar las cosas, y un registro que se pudiera retocar
 * desde una pantalla no probaría nada.
 */
class AuditoriaController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /auditoria?entidad=&entidad_id=&user_id=&desde=&hasta=&search=&page=&size=
     */
    public function index(Request $request)
    {
        $query = Auditoria::query()->orderByDesc('created_at')->orderByDesc('id');

        // La historia de UNA ficha: "todo lo que le pasó a este empleado".
        foreach (['entidad', 'entidad_id', 'user_id', 'accion'] as $filtro) {
            if ($request->filled($filtro)) {
                $query->where($filtro, $request->input($filtro));
            }
        }

        if ($request->filled('desde')) {
            $query->where('created_at', '>=', $request->date('desde')->startOfDay());
        }

        if ($request->filled('hasta')) {
            $query->where('created_at', '<=', $request->date('hasta')->endOfDay());
        }

        return $this->responderListado($request, $query, ['descripcion', 'usuario_nombre']);
    }
}
