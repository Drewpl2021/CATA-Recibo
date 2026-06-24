<?php
namespace App\Http\Controllers;

use App\Models\ModuloPadre;
use Illuminate\Http\Request;

class ModuloPadreController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => ModuloPadre::with('modulos')->orderBy('orden')->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'icono'  => 'nullable|string|max:100',
            'orden'  => 'nullable|integer|min:0',
        ]);

        $moduloPadre = ModuloPadre::create($request->all());

        return response()->json(['success' => true, 'data' => $moduloPadre], 201);
    }

    public function show(string $id)
    {
        $moduloPadre = ModuloPadre::with('modulos')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $moduloPadre]);
    }

    public function update(Request $request, string $id)
    {
        $moduloPadre = ModuloPadre::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'icono'  => 'nullable|string|max:100',
            'orden'  => 'nullable|integer|min:0',
        ]);

        $moduloPadre->update($request->all());

        return response()->json(['success' => true, 'data' => $moduloPadre]);
    }

    public function destroy(string $id)
    {
        $moduloPadre = ModuloPadre::findOrFail($id);
        $moduloPadre->update(['estado_registro' => 'inactivo']);

        return response()->json(['success' => true, 'data' => ['message' => 'Módulo padre eliminado correctamente.']]);
    }
}