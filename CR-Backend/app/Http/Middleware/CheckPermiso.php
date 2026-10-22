<?php

namespace App\Http\Middleware;

use App\Support\Permisos;
use Closure;
use Illuminate\Http\Request;

/**
 * «permiso:/planillas,/boletas»: entra quien tenga en su rol al menos uno de
 * esos módulos (o sea superadministrador). Reemplaza a «rol:admin,rrhh», que
 * preguntaba por el nombre del rol y dejaba fuera a cualquier rol nuevo.
 * Ver App\Support\Permisos.
 */
class CheckPermiso
{
    public function handle(Request $request, Closure $next, string ...$modulos): mixed
    {
        $usuario = $request->user();

        if (! $usuario) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'No autenticado.'],
            ], 401);
        }

        if (! Permisos::puede($usuario->loadMissing('rol'), ...$modulos)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'No tienes permiso para esta acción. Pide a Administración que le dé ese módulo a tu rol.'],
            ], 403);
        }

        return $next($request);
    }
}
