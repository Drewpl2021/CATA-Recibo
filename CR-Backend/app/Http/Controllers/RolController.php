<?php
namespace App\Http\Controllers;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ListadoPaginado;

class RolController extends Controller
{
    use ListadoPaginado;

    /**
     * Los tres roles que el sistema tiene escritos en el código.
     *
     * routes/api.php protege rutas con `rol:admin` y varios controladores
     * comparan contra 'rrhh' en texto plano. Si alguien renombra uno de
     * estos desde la pantalla de Roles no falla nada en ese momento: falla
     * después, cuando Recursos Humanos entra y ya no ve sus módulos, y
     * nadie relaciona una cosa con la otra.
     *
     * La descripción sí se puede cambiar, y borrarlos no se puede.
     */
    private const ROLES_DEL_SISTEMA = ['admin', 'rrhh', 'empleado'];

    /**
     * GET /roles
     *
     * Sin ?page devuelve la lista completa (así la piden los desplegables
     * de los formularios). Con ?page&size la corta el servidor y manda
     * además el total, para que el frontend no tenga que traerse todo
     * para saber cuántos hay. Ver App\Traits\ListadoPaginado.
     */
    public function index(Request $request)
    {
        return $this->responderListado(
            $request,
            Rol::query()->orderBy('nombre'),
            ['nombre', 'descripcion']
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:45|unique:roles,nombre',
            'descripcion' => 'nullable|string|max:255',
        ]);
        $rol = Rol::create($request->only(['nombre', 'descripcion']));
        return response()->json(['success' => true, 'data' => $rol], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Rol::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $rol = Rol::findOrFail($id);
        $request->validate([
            'nombre' => 'sometimes|string|max:45|unique:roles,nombre,' . $id,
            'descripcion' => 'nullable|string|max:255',
        ]);

        $datos = $request->only(['nombre', 'descripcion']);

        // A un rol del sistema se le cambia la descripción, no el nombre.
        if ($this->esDelSistema($rol) && isset($datos['nombre']) && $datos['nombre'] !== $rol->nombre) {
            return response()->json([
                'success' => false,
                'message' => "El rol \"{$rol->nombre}\" es del sistema y no se le puede cambiar el nombre: "
                    . 'los permisos de todas las pantallas dependen de él. Si querías cambiar cómo se ve, '
                    . 'eso se hace en la etiqueta, no en el nombre interno.',
            ], 422);
        }

        $rol->update($datos);
        return response()->json(['success' => true, 'data' => $rol]);
    }

    public function destroy(string $id)
    {
        $rol = Rol::findOrFail($id);

        if ($this->esDelSistema($rol)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => "El rol \"{$rol->nombre}\" es del sistema y no se puede eliminar."],
            ], 409);
        }

        $usuariosConEsteRol = User::where('rol_id', $id)->count();
        if ($usuariosConEsteRol > 0) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => "No se puede eliminar: hay {$usuariosConEsteRol} usuario(s) con este rol asignado."],
            ], 409);
        }

        $rol->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Rol eliminado.']]);
    }

    /** ¿Es uno de los tres roles que el backend tiene escritos en el código? */
    private function esDelSistema(Rol $rol): bool
    {
        return in_array(mb_strtolower($rol->nombre), self::ROLES_DEL_SISTEMA, true);
    }
}
