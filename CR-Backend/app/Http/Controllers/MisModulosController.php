<?php
namespace App\Http\Controllers;

use App\Models\ModuloPadre;
use Illuminate\Http\Request;

class MisModulosController extends Controller
{
    public function index(Request $request)
    {
        $rolId = $request->user()->rol_id;

        $modulos = ModuloPadre::where('estado_registro', 'activo')
            ->with(['modulos' => function ($query) use ($rolId) {
                $query->where('estado_registro', 'activo')
                    ->whereHas('roles', function ($q) use ($rolId) {
                        $q->where('roles.id', $rolId);
                    })->orderBy('orden');
            }])->orderBy('orden')->get();

        // Filtrar padres que tengan al menos un módulo para este rol
        $modulos = $modulos->filter(fn($padre) => $padre->modulos->isNotEmpty())->values();

        return response()->json(['success' => true, 'data' => $modulos]);
    }
}