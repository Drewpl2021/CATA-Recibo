<?php

namespace App\Http\Controllers;

use App\Models\Planilla;
use Illuminate\Http\Request;

class MiPlanillaController extends Controller
{
    public function index(Request $request)
    {
        $empleado_id = $request->user()->empleado_id;

        if (!$empleado_id) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Tu usuario no tiene empleado vinculado.']
            ], 403);
        }

        $query = Planilla::with('empleado')->where('empleado_id', $empleado_id);

        if ($request->has('mes'))
            $query->where('mes', $request->mes);

        if ($request->has('anio'))
            $query->where('anio', $request->anio);

        return response()->json(['success' => true, 'data' => $query->get()]);
    }
}