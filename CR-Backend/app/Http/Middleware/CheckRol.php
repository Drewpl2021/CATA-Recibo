<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class CheckRol
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! $request->user()) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'No autenticado.'],
            ], 401);
        }

        $rolNombre = $request->user()->rol?->nombre;

        if (! in_array($rolNombre, $roles)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'No tienes permiso para esta acción.'],
            ], 403);
        }

        return $next($request);
    }
}