<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Rol;
use App\Models\Empleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            // Se pide el DNI de verdad. Antes se inventaba con substr(time(), -8),
            // lo que daba un documento falso y, peor, hacía chocar dos registros
            // hechos en el mismo segundo con un error de clave duplicada.
            'dni'      => 'required|string|regex:/^[0-9]{8}$/|unique:empleados,dni',
            'email'    => 'required|email|unique:users|ends_with:@cata.edu.pe',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $rolEmpleado = Rol::where('nombre', 'empleado')->first();

        // Empleado y usuario nacen juntos: si algo falla a mitad, no debe
        // quedar un empleado suelto sin cuenta con el DNI ya ocupado.
        $user = DB::transaction(function () use ($request, $rolEmpleado) {
            $empleado = Empleado::create([
                'nombre'        => $request->nombre,
                'apellido'      => $request->apellido,
                'dni'           => $request->dni,
                'fecha_ingreso' => now()->toDateString(),
                'estado'        => 'activo',
            ]);

            return User::create([
                'name'        => trim($request->nombre . ' ' . $request->apellido),
                'email'       => $request->email,
                'password'    => Hash::make($request->password),
                'rol_id'      => $rolEmpleado?->id,
                'empleado_id' => $empleado->id,
            ]);
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => ['user' => $user->load('rol', 'empleado'), 'token' => $token],
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales incorrectas.'],
            ]);
        }

        if ($user->estado_registro !== 'activo') {
            throw ValidationException::withMessages([
                'email' => ['Esta cuenta está desactivada.'],
            ]);
        }

        $user->tokens()->delete();

        // El token nace con su propia fecha de vencimiento; a partir de ahí
        // cada petición la empuja (middleware RenovarSesionActiva), así que
        // la sesión se cierra por estar sin usarse, no por antigüedad.
        $token = $user->createToken(
            'auth_token',
            ['*'],
            now()->addMinutes((int) config('sanctum.ventana_inactividad', 120))
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'user'            => $user->load('rol', 'empleado'),
                'token'           => $token,
                'es_institucional'=> $user->es_institucional,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'data'    => ['message' => 'Sesión cerrada correctamente.'],
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user()->load('rol', 'empleado'),
        ]);
    }
    public function cambiarPassword(Request $request)
    {
        $request->validate([
            'password_actual'      => 'required|string',
            'password_nuevo'       => 'required|string|min:8|confirmed|different:password_actual',
        ]);

        $user = $request->user();

        // Al dar de alta un empleado, su contraseña inicial ES su DNI. Dejar
        // que la "cambie" por el mismo DNI no cambia nada en la práctica.
        if ($user->empleado && $request->password_nuevo === $user->empleado->dni) {
            throw ValidationException::withMessages([
                'password_nuevo' => ['La contraseña no puede ser tu DNI: es la que te dieron al inicio.'],
            ]);
        }

        if (! Hash::check($request->password_actual, $user->password)) {
            throw ValidationException::withMessages([
                'password_actual' => ['La contraseña actual es incorrecta.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password_nuevo),
        ]);

        // Invalida todas las sesiones activas menos la actual, por seguridad
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'data'    => ['message' => 'Contraseña actualizada correctamente.'],
        ]);
    }
}