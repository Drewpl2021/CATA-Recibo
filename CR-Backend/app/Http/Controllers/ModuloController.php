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
        $datos = $request->validate([
            'modulo_padre_id' => 'required|uuid|exists:modulo_padre,id',
            'nombre'          => ['required', 'string', 'max:255',
                Rule::unique('modulos', 'nombre')->where('estado_registro', 'activo')],
            'ruta'            => 'nullable|string|max:255',
            'icono'           => 'nullable|string|max:255',
            'orden'           => 'nullable|integer|min:0',
        ]);

        $modulo = Modulo::create($datos);

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

        $datos = $request->validate([
            'modulo_padre_id' => 'sometimes|uuid|exists:modulo_padre,id',
            'nombre'          => ['sometimes', 'string', 'max:255',
                Rule::unique('modulos', 'nombre')->where('estado_registro', 'activo')->ignore($id)],
            'ruta'            => 'nullable|string|max:255',
            'icono'           => 'nullable|string|max:255',
            'orden'           => 'nullable|integer|min:0',
        ]);

        $modulo->update($datos);
        \App\Support\Permisos::refrescar();

        return response()->json(['success' => true, 'data' => $modulo]);
    }

    public function destroy(string $id)
    {
        $modulo = Modulo::findOrFail($id);
        $modulo->update(['estado_registro' => 'inactivo']);
        \App\Support\Permisos::refrescar();

        return response()->json(['success' => true, 'data' => ['message' => 'Módulo eliminado correctamente.']]);
    }

    /**
     * Deja la lista de roles que tienen este módulo: es a la vez su menú y su
     * permiso real (ver App\Support\Permisos), así que pasa por las mismas
     * reglas que dar o quitar un módulo desde Roles.
     */
    public function asignarRoles(Request $request, string $id)
    {
        $datos = $request->validate([
            'roles'   => 'present|array',
            'roles.*' => 'uuid|exists:roles,id|distinct',
        ]);

        $modulo = Modulo::with('roles')->findOrFail($id);

        $antes = $modulo->roles->pluck('id')->map(fn ($r) => (string) $r)->all();
        $ahora = array_map('strval', $datos['roles']);
        $cambian = [
            ...array_map(fn ($rol) => [$rol, true], array_diff($ahora, $antes)),
            ...array_map(fn ($rol) => [$rol, false], array_diff($antes, $ahora)),
        ];
        foreach ($cambian as [$rolId, $activo]) {
            $motivo = \App\Support\Permisos::porQueNoPuedeCambiar($request->user(), \App\Models\Rol::findOrFail($rolId), $modulo, $activo);
            if ($motivo) {
                return response()->json(['success' => false, 'message' => $motivo], 403);
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($modulo, $ahora, $cambian) {
            $modulo->roles()->sync($ahora);
            if ($cambian) {
                \App\Models\Auditoria::registrar('cambió', 'modulo', (string) $modulo->id, "Cambió los roles que tienen el módulo {$modulo->nombre}.");
            }
        });
        \App\Support\Permisos::refrescar();

        return response()->json([
            'success' => true,
            'data'    => $modulo->load('roles'),
            'message' => 'Roles actualizados para este módulo.'
        ]);
    }
}