<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Rol;
use App\Models\Empleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
// La REGLA de contraseña, con otro nombre: "Password" a secas ya es la
// fachada de los enlaces de recuperación que usa este mismo controlador.
use Illuminate\Validation\Rules\Password as ReglaDeClave;

class AuthController extends Controller
{
    /**
     * La ÚNICA respuesta a un intento de acceso fallido, sea cual sea el
     * motivo: el correo no está registrado, la contraseña no coincide, o la
     * cuenta ya no está activa.
     *
     * No la especialices. Cada variante que se agregue vuelve a abrir la
     * puerta a enumerar usuarios: comparando respuestas, cualquiera de fuera
     * puede ir descubriendo qué direcciones existen.
     */
    private const CREDENCIALES_INVALIDAS =
        'Credenciales incorrectas. Verifica tus datos e intenta nuevamente.';

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        /*
         * Los tres motivos de rechazo se contestan igual.
         *
         * Antes la cuenta desactivada tenía su propio mensaje, y eso era una
         * fuga: para verlo había que acertar el correo Y la contraseña, así
         * que el mensaje confirmaba las dos cosas de golpe.
         *
         * El Hash::make de abajo no guarda nada; está para gastar el mismo
         * tiempo que habría costado comprobar una contraseña real. Sin él,
         * el caso "ese correo no existe" contesta muy por debajo del resto y
         * el cronómetro delata las direcciones válidas aunque el texto sea
         * idéntico.
         */
        if (! $user) {
            Hash::make($request->password);
        }

        if (! $user
            || ! Hash::check($request->password, $user->password)
            || $user->estado_registro !== 'activo') {
            throw ValidationException::withMessages([
                'email' => [self::CREDENCIALES_INVALIDAS],
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
                // Si viene en true, el frontend manda a cambiar la contraseña
                // y no deja pasar a ninguna otra pantalla. El backend lo
                // vuelve a comprobar en cada petición (ExigirCambioPassword),
                // así que no basta con esquivar la pantalla.
                'debe_cambiar_password' => (bool) $user->debe_cambiar_password,
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
            'password_nuevo'       => ['required', 'string', 'confirmed', 'different:password_actual', ReglaDeClave::defaults()],
        ]);

        $user = $request->user();

        // Primero los términos, después la contraseña. Es el orden que tenía
        // en papel: se firmaba la hoja al entrar, no después. Y hacerlo aquí
        // y no solo en la pantalla es lo que lo vuelve una regla: por la API
        // tampoco se puede saltar.
        if (! $user->terminosAlDia()) {
            return response()->json([
                'success' => false,
                'message' => 'Antes de poner tu contraseña tienes que leer y aceptar los términos de uso.',
                'data'    => ['requiereTerminos' => true],
            ], 409);
        }

        // El documento de identidad no vale como contraseña: figura en varios
        // documentos del trabajador. El mensaje se queda en el consejo y no
        // menciona de dónde salía la contraseña provisional.
        if ($user->empleado && $request->password_nuevo === $user->empleado->dni) {
            throw ValidationException::withMessages([
                'password_nuevo' => ['No uses datos personales como contraseña. Elige una distinta.'],
            ]);
        }

        if (! Hash::check($request->password_actual, $user->password)) {
            throw ValidationException::withMessages([
                'password_actual' => ['La contraseña actual es incorrecta.'],
            ]);
        }

        $user->update([
            'password'              => Hash::make($request->password_nuevo),
            // Ya puso una suya: se levanta el bloqueo del primer ingreso.
            'debe_cambiar_password' => false,
        ]);

        // Invalida todas las sesiones activas menos la actual, por seguridad
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'data'    => ['message' => 'Contraseña actualizada correctamente.'],
        ]);
    }

    /**
     * POST /olvide-password — "no me acuerdo de mi contraseña".
     *
     * Manda al correo un enlace de un solo uso que vence en una hora.
     *
     * Responde lo mismo exista o no el correo, a propósito (prevención de
     * enumeración de usuarios): si dijera "ese correo no está registrado",
     * bastaría con ir probando direcciones para descubrir cuáles están
     * dadas de alta.
     */
    public function olvidePassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $estado = Password::sendResetLink($request->only('email'));

        // El único caso que sí se avisa: pidió otro enlace demasiado pronto.
        // Callarlo haría que la persona siguiera esperando un correo que no
        // va a salir.
        if ($estado === Password::RESET_THROTTLED) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Ya te enviamos un enlace hace poco. Revisa tu correo y espera un minuto antes de pedir otro.'],
            ], 429);
        }

        return response()->json([
            'success' => true,
            'data'    => ['message' => 'Si el correo está registrado, en unos minutos te llegará el enlace para poner una contraseña nueva.'],
        ]);
    }

    /**
     * POST /restablecer-password — pone la contraseña nueva con el token del
     * correo.
     *
     * Al terminar cierra TODAS las sesiones abiertas de esa cuenta: si alguien
     * más había entrado, se queda fuera, que es justo lo que se busca cuando
     * se repone una contraseña.
     */
    public function restablecerPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => ['required', 'string', 'confirmed', ReglaDeClave::defaults()],
        ]);

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'              => Hash::make($password),
                    'debe_cambiar_password' => false,
                    'remember_token'        => Str::random(60),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['El enlace ya venció o no es válido. Pide uno nuevo desde la pantalla de ingreso.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => ['message' => 'Listo, ya puedes entrar con tu contraseña nueva.'],
        ]);
    }
}
