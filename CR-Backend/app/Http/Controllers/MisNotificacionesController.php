<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Traits\ListadoPaginado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Los avisos del trabajador que ha iniciado sesión.
 *
 * Cada quien ve SOLO los suyos: no hay ningún parámetro para pedir los de
 * otro, el user_id sale siempre del token.
 */
class MisNotificacionesController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /mis-notificaciones?page=&size=
     *
     * Del más nuevo al más viejo. Junto a la página va el número de no
     * leídas, que es lo que pinta el globito de la campana: contarlas en el
     * navegador daría solo las de la página que se está viendo.
     */
    public function index(Request $request)
    {
        $query = Notificacion::with('documento')
            ->where('user_id', $request->user()->id);

        return $this->responderListado(
            $request,
            $query->orderBy('created_at', 'desc'),
            ['titulo', 'mensaje'],
            fn (Builder $suyas) => [
                'noLeidas' => (clone $suyas)->reorder()->whereNull('leida_at')->count(),
            ]
        );
    }

    /**
     * PATCH /mis-notificaciones/{id}/leida
     *
     * Marcar como leída es idempotente: repetirlo no cambia la fecha de la
     * primera vez, así que da igual si el clic se manda dos veces.
     */
    public function leida(Request $request, string $id)
    {
        $notificacion = Notificacion::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (is_null($notificacion->leida_at)) {
            $notificacion->update(['leida_at' => now()]);
        }

        return response()->json(['success' => true, 'data' => $notificacion]);
    }

    /**
     * POST /mis-notificaciones/marcar-todas
     *
     * Para el "marcar todo como leído" de la campana.
     */
    public function marcarTodas(Request $request)
    {
        $marcadas = Notificacion::where('user_id', $request->user()->id)
            ->whereNull('leida_at')
            ->update(['leida_at' => now()]);

        return response()->json([
            'success' => true,
            'data'    => ['marcadas' => $marcadas],
        ]);
    }
}
