<?php
namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Traits\ListadoPaginado;

class UserController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /users?page=&size=&search=
     *
     * Los usuarios dados de baja solo los ve el admin.
     */
    public function index(Request $request)
    {
        $query = User::with('rol', 'empleado');

        if (! $request->user()->esSuperadmin()) {
            $query->where('estado_registro', 'activo');
        }

        return $this->responderListado(
            $request,
            $query->orderBy('name'),
            ['name', 'email', 'empleado.dni'],
            // Las cifras de la cabecera: se cuentan sobre todo lo que pasa el
            // filtro, no sobre la página que se está viendo.
            fn (Builder $filtrada) => $this->conteoPorEstado($filtrada, 'estado_registro', ['activos' => 'activo', 'inactivos' => 'inactivo'])
        );
    }

    public function show(string $id)
    {
        $user = User::with('rol', 'empleado')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function update(Request $request, string $id)
    {
        $datos = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'email'           => 'sometimes|email|unique:users,email,' . $id,
            'rol_id'          => 'sometimes|uuid|exists:roles,id',
            'empleado_id'     => ['nullable', 'uuid', 'exists:empleados,id', Rule::unique('users', 'empleado_id')->ignore($id)],
            'estado_registro' => 'sometimes|in:activo,inactivo',
        ]);

        $user = User::with('rol')->findOrFail($id);
        $quien = $request->user();

        // El formulario manda todos los campos en cada edición: solo cuenta lo
        // que de verdad cambia.
        $cambios = array_filter($datos, fn ($valor, $campo) => $campo === 'email'
            ? mb_strtolower((string) $valor) !== mb_strtolower((string) $user->email)
            : (string) $valor !== (string) $user->{$campo}, ARRAY_FILTER_USE_BOTH);

        if (! $quien->esSuperadmin()) {
            $soloAdministrador = match (true) {
                array_key_exists('rol_id', $cambios)          => 'Solo un Administrador puede cambiar el rol de un usuario.',
                array_key_exists('estado_registro', $cambios) => 'Solo un Administrador puede activar o desactivar un usuario.',
                // La ficha decide de quién son las boletas que esta cuenta firma.
                array_key_exists('empleado_id', $cambios)     => 'Solo un Administrador puede vincular una cuenta a un trabajador.',
                $cambios !== [] && $user->protegidaPara($quien) => self::SOLO_ADMINISTRADOR,
                default => null,
            };

            if ($soloAdministrador) {
                return $this->prohibido($soloAdministrador);
            }
        }

        $quitaAdministrador = ($cambios['estado_registro'] ?? null) === 'inactivo'
            || (isset($cambios['rol_id']) && ! Rol::whereKey($cambios['rol_id'])->value('es_superadmin'));
        if ($quitaAdministrador && $this->esElUltimoAdministrador($user)) {
            return response()->json(['success' => false, 'data' => ['message' => self::ULTIMO_ADMINISTRADOR]], 422);
        }

        DB::transaction(function () use ($user, $cambios) {
            $user->update($cambios);
            if (($cambios['estado_registro'] ?? null) === 'inactivo') {
                $user->tokens()->delete();
            }
        });
        $user->load('rol', 'empleado');

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function destroy(Request $request, string $id)
    {
        $user = User::with('rol')->findOrFail($id);
        $quien = $request->user();

        if ((string) $quien->id === (string) $user->id) {
            return response()->json(['success' => false, 'data' => ['message' => 'No puedes darte de baja a ti mismo.']], 422);
        }
        if ($user->protegidaPara($quien)) {
            return $this->prohibido(self::SOLO_ADMINISTRADOR);
        }
        if ($this->esElUltimoAdministrador($user)) {
            return response()->json(['success' => false, 'data' => ['message' => self::ULTIMO_ADMINISTRADOR]], 422);
        }

        DB::transaction(function () use ($user) {
            $user->update(['estado_registro' => 'inactivo']);
            $user->tokens()->delete();
        });

        return response()->json(['success' => true, 'data' => ['message' => 'Usuario eliminado correctamente.']]);
    }

    private const SOLO_ADMINISTRADOR = 'Esta cuenta maneja módulos del sistema: solo un Administrador puede cambiarla.';

    private const ULTIMO_ADMINISTRADOR = 'Es el único Administrador activo: nombra a otro antes de quitarle el acceso.';

    private function prohibido(string $mensaje)
    {
        return response()->json(['success' => false, 'data' => ['message' => $mensaje]], 403);
    }

    private function esElUltimoAdministrador(User $user): bool
    {
        return $user->esSuperadmin() && ! User::where('estado_registro', 'activo')
            ->whereKeyNot($user->id)
            ->whereHas('rol', fn (Builder $rol) => $rol->where('es_superadmin', true))
            ->exists();
    }

    /**
     * POST /users/{id}/restablecer-password
     *
     * Para cuando alguien se queda fuera de su cuenta.
     *
     * Si tiene correo, se le envía «Tu acceso a CATA-Recibo» con el enlace para
     * crear una contraseña nueva (y la que tenía deja de servir). Si no tiene
     * correo, se genera una clave temporal aleatoria que se devuelve UNA sola
     * vez, para que RR.HH. se la entregue en persona; el sistema le obliga a
     * cambiarla al entrar. Nunca el DNI: no es un secreto, está en la boleta.
     */
    public function restablecerPassword(Request $request, string $id)
    {
        $user = User::with('rol', 'empleado')->findOrFail($id);
        $quienPide = $request->user();

        // Reponerse la contraseña a uno mismo por acá cerraría la sesión con
        // la que se está trabajando. Para eso está "Cambiar contraseña".
        if ($quienPide->id === $user->id) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Para tu propia cuenta usa Cambiar contraseña, en tu perfil.'],
            ], 422);
        }

        // Sin esto, RR.HH. podía ponerle una clave a una cuenta con más poder
        // que la suya y entrar con ella.
        if ($user->protegidaPara($quienPide)) {
            return $this->prohibido(self::SOLO_ADMINISTRADOR);
        }

        // Con correo: el enlace, y la clave que tenía deja de servir.
        if ($user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL) && $user->estado_registro === 'activo') {
            \App\Support\AccesoPorCorreo::restablecer($user);

            return response()->json([
                'success' => true,
                'data'    => [
                    'message'    => "Le enviamos a {$user->email} el enlace para crear una contraseña nueva. Vence en 72 horas; la que tenía ya no sirve.",
                    'por_correo' => true,
                ],
            ]);
        }

        // Sin correo: una clave temporal aleatoria, que se muestra una sola vez.
        $nueva = Str::password(12, symbols: false);
        $user->update([
            'password'              => Hash::make($nueva),
            'debe_cambiar_password' => true,
        ]);
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'data'    => [
                'message'           => 'Contraseña restablecida. Entrégale esta clave temporal en persona: se muestra una sola vez, y el sistema le pedirá cambiarla al entrar.',
                'password_temporal' => $nueva,
                'por_correo'        => false,
            ],
        ]);
    }
}
