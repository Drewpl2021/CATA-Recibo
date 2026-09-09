<?php
namespace App\Http\Controllers;

use App\Models\ModuloPadre;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\ListadoPaginado;

class ModuloPadreController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /modulo-padres?page=&size=&search=
     *
     * Solo los vivos: destroy() hace baja lógica. Sin ?page devuelve todo,
     * que es como lo pide el menú lateral.
     */
    public function index(Request $request)
    {
        return $this->responderListado(
            $request,
            ModuloPadre::with('modulos')
                ->where('estado_registro', 'activo')
                ->orderBy('orden'),
            ['nombre']
        );
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100',
                Rule::unique('modulo_padre', 'nombre')->where('estado_registro', 'activo')],
            'icono'  => 'nullable|string|max:100',
            'orden'  => 'nullable|integer|min:0',
        ]);

        $moduloPadre = ModuloPadre::create($datos);

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

        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:100',
                Rule::unique('modulo_padre', 'nombre')->where('estado_registro', 'activo')->ignore($id)],
            'icono'  => 'nullable|string|max:100',
            'orden'  => 'nullable|integer|min:0',
        ]);

        $moduloPadre->update($datos);

        return response()->json(['success' => true, 'data' => $moduloPadre]);
    }

    public function destroy(string $id)
    {
        $moduloPadre = ModuloPadre::findOrFail($id);
        $moduloPadre->update(['estado_registro' => 'inactivo']);

        return response()->json(['success' => true, 'data' => ['message' => 'Módulo padre eliminado correctamente.']]);
    }
}