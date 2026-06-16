<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Rol;
use App\Models\Empleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users|ends_with:@cata.edu.pe',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $rolEmpleado = Rol::where('nombre', 'empleado')->first();

        $empleado = Empleado::create([
            'nombre'       => $request->name,
            'apellido'     => '',
            'dni'          => substr(time(), -8),
            'fecha_ingreso'=> now()->toDateString(),
            'estado'       => 'activo',
        ]);

        $user = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'rol_id'      => $rolEmpleado?->id,
            'empleado_id' => $empleado->id,
        ]);

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

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'user'            => $user->load('rol', 'empleado'),
                'token'           => $token,
                'es_institucional'=> str_ends_with($user->email, '@cata.edu.pe'),
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
}