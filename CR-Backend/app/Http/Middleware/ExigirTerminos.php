<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sin los términos de uso firmados no se entra a nada.
 *
 * Antes la firma solo se pedía en la pantalla del cambio de contraseña, así
 * que quien no pasaba por ahí no firmaba nunca: las cuentas que ya existían,
 * y quien se registraba solo —porque pone su contraseña él mismo—. El papel
 * que esto sustituye lo firmaba TODO el mundo; esto también.
 *
 * Va detrás de 'clave_nueva': a quien le falta cambiar la contraseña se le
 * responde 423 primero, y en esa pantalla ya firma antes de ponerla.
 *
 * Responde 428 (Precondition Required): la sesión es buena y la cuenta
 * también, solo falta un paso previo.
 */
class ExigirTerminos
{
    /**
     * Lo que se deja pasar sin firmar: saber quién eres, leer y firmar los
     * términos, cambiar la contraseña (que ya exige la firma por su cuenta) y
     * salir.
     */
    private const PERMITIDAS = [
        'api/me',
        'api/logout',
        'api/change-password',
        'api/terms',
        'api/terms/accept',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->terminosAlDia() && ! $request->is(...self::PERMITIDAS)) {
            return response()->json([
                'success' => false,
                'message' => 'Antes de continuar tienes que leer y aceptar los términos de uso.',
                'data'    => ['requiereTerminos' => true],
            ], 428);
        }

        return $next($request);
    }
}
