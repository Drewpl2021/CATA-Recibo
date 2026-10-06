<?php

namespace App\Http\Controllers;

use App\Support\Progreso;
use Illuminate\Http\Request;

/** GET /progress/{id} — cuánto lleva un proceso largo de esta misma cuenta (ver App\Support\Progreso). */
class ProgresoController extends Controller
{
    public function ver(Request $request, string $id)
    {
        // null mientras el proceso todavía no empieza: la pantalla espera.
        return response()->json(['success' => true, 'data' => Progreso::leer($request->user()->id, $id)]);
    }
}
