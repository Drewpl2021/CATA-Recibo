<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras la cuenta siga con la contraseña que le dieron, no se entra a nada.
 *
 * La contraseña inicial de un empleado es su DNI, que sabe cualquiera que vea
 * su ficha o su boleta. La pantalla del frontend ya manda a cambiarla, pero
 * una pantalla no es una cerradura: sin esto bastaba con llamar al API por
 * fuera para seguir trabajando con la contraseña pública.
 *
 * Lo único que se deja pasar es lo que hace falta para cambiarla: saber quién
 * eres, cambiarla, y salir.
 */
class ExigirCambioPassword
{
    /**
     * Rutas que siguen abiertas con el bloqueo puesto. El prefijo 'api' lo
     * pone el enrutador (bootstrap/app.php), por eso va en el patrón.
     */
    private const PERMITIDAS = [
        'api/me',
        'api/logout',
        'api/cambiar-password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && $usuario->debe_cambiar_password && ! $request->is(...self::PERMITIDAS)) {
            return response()->json([
                'success' => false,
                'data'    => [
                    'message'               => 'Antes de continuar tienes que cambiar tu contraseña.',
                    'debe_cambiar_password' => true,
                ],
            ], 423); // 423 Locked: la sesión es válida, la cuenta está trabada.
        }

        return $next($request);
    }
}
