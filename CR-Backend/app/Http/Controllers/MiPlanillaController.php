<?php

namespace App\Http\Controllers;

use App\Models\Planilla;
use Illuminate\Http\Request;

class MiPlanillaController extends Controller
{
    public function index(Request $request)
    {
        $empleado_id = $request->user()->empleado_id;

        // Sin ficha no hay planilla que enseñar, y eso no es un error de
        // permisos: es que esa cuenta no cobra por planilla. Se responde la
        // lista vacía en vez de un 403.
        $query = Planilla::with('empleado')
            ->whereRaw($empleado_id ? '1 = 1' : '1 = 0')
            ->where('empleado_id', $empleado_id);

        if ($request->has('mes'))
            $query->where('mes', $request->mes);

        if ($request->has('anio'))
            $query->where('anio', $request->anio);

        return response()->json(['success' => true, 'data' => $query->get()]);
    }
}