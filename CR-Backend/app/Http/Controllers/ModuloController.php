<?php
namespace App\Http\Controllers;

use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\ListadoPaginado;

class ModuloController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /modulos?modulo_padre_id=&page=&size=&search=
     *
     * Solo los vivos: destroy() hace baja lógica, y sin este filtro la
     * pantalla de administración seguía listando módulos ya eliminados.
     * Sin ?page devuelve todo — el sidebar necesita el árbol completo.
     */
    public function index(Request $request)
    {
        $query = Modulo::with('moduloPadre', 'roles')
            ->where('estado_registro', 'activo');

        if ($request->filled('modulo_padre_id')) {
            $query->where('modulo_padre_id', $request->modulo_padre_id);
        }

        return $this->responderListado(
            $request,
            $query->orderBy('orden'),
            ['nombre', 'ruta']
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'modulo_padre_id' => 'required|uuid|exists:modulo_padre,id',
            'nombre'          => ['required', 'string', 'max:255',
                Rule::unique('modulos', 'nombre')->where('estado_registro', 'activo')],
            'ruta'            => 'nullable|string|max:255',
            'icono'           => 'nullable|string|max:255',
            'orden'           => 'nullable|integer|min:0',
        ]);

        $modulo = Modulo::create($request->all());

        return response()->json(['success' => true, 'data' => $modulo], 201);
    }

    public function show(string $id)
    {
        $modulo = Modulo::with('moduloPadre', 'roles')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $modulo]);
    }

    public function update(Request $request, string $id)
    {
        $modulo = Modulo::findOrFail($id);

        $request->validate([
            'modulo_padre_id' => 'sometimes|uuid|exists:modulo_padre,id',
            'nombre'          => ['sometimes', 'string', 'max:255',
                Rule::unique('modulos', 'nombre')->where('estado_registro', 'activo')->ignore($id)],
            'ruta'            => 'nullable|string|max:255',
            'icono'           => 'nullable|string|max:255',
            'orden'           => 'nullable|integer|min:0',
        ]);

        $modulo->update($request->all());

        return response()->json(['success' => true, 'data' => $modulo]);
    }

    public function destroy(string $id)
    {
        $modulo = Modulo::findOrFail($id);
        $modulo->update(['estado_registro' => 'inactivo']);

        return response()->json(['success' => true, 'data' => ['message' => 'Módulo eliminado correctamente.']]);
    }

    /**
     * Asigna o reemplaza los roles que pueden VER este módulo en el sidebar (mis-modulos).
     * Esto NO otorga permisos reales sobre los endpoints del módulo: el acceso a las
     * rutas sigue controlado aparte por el middleware `rol:` en routes/api.php.
     */
    public function asignarRoles(Request $request, string $id)
    {
        $modulo = Modulo::findOrFail($id);

        $request->validate([
            'roles'   => 'required|array',
            'roles.*' => 'uuid|exists:roles,id|distinct',
        ]);

        $modulo->roles()->sync($request->roles);

        return response()->json([
            'success' => true,
            'data'    => $modulo->load('roles'),
            'message' => 'Roles actualizados para este módulo.'
        ]);
    }
}