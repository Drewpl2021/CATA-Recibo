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
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Buscar rol empleado automáticamente
        $rolEmpleado = Rol::where('nombre', 'empleado')->first();

        // Crear empleado básico
        $empleado = Empleado::create([
                'nombre'       => $request->name,
                'apellido'     => '',
                'dni'          => '00000000',
                'cargo'        => 'Sin asignar',
                'area'         => 'Sin asignar',
                'fecha_ingreso'=> now()->toDateString(),
            ]);

                    // Crear usuario vinculado
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
            'data'    => ['user' => $user->load('rol', 'empleado'), 'token' => $token],
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