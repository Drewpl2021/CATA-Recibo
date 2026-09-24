<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * La foto de perfil de una cuenta.
 *
 * Va al disco privado "local" (storage/app/private), igual que la firma y la
 * huella: una foto de la cara es un dato personal y no puede quedar
 * accesible por una URL pública. Por eso la imagen NO se sirve como archivo
 * estático sino por `ver()`, que comprueba antes quién la pide.
 *
 * Cada quien sube la suya. RR.HH. y Administración pueden VER la de
 * cualquiera —sale en su ficha—, pero cambiarla es cosa del dueño de la
 * cuenta: una foto de perfil es cómo se presenta cada persona.
 */
class FotoPerfilController extends Controller
{
    /** POST /my-photo */
    public function subirMia(Request $request)
    {
        $request->validate([
            // 4 MB: una foto de teléfono ronda los 2-3 MB sin tocarla, y
            // obligar a encogerla antes sería trabajo que nadie va a hacer.
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $usuario = $request->user();
        $archivo = $request->file('foto');

        // El nombre lo pone el servidor: el del usuario puede traer barras o
        // tildes, y la fecha evita que el navegador siga enseñando la vieja
        // desde su caché cuando alguien se la cambia.
        $ruta = sprintf(
            'fotos-perfil/%d/foto-%s.%s',
            $usuario->id,
            now()->format('Ymd-His'),
            strtolower($archivo->getClientOriginalExtension())
        );

        // La anterior se borra de verdad. Acá no hay historial que conservar
        // —es la misma persona, solo que con otra foto— y guardarlas todas
        // sería ir llenando el disco con caras repetidas.
        $this->borrarArchivo($usuario->foto);

        Storage::disk('local')->put($ruta, file_get_contents($archivo->getRealPath()));
        $usuario->update(['foto' => $ruta]);

        return response()->json(['success' => true, 'data' => ['foto' => $ruta]]);
    }

    /** DELETE /my-photo — vuelve a las iniciales. */
    public function quitarMia(Request $request)
    {
        $usuario = $request->user();

        $this->borrarArchivo($usuario->foto);
        $usuario->update(['foto' => null]);

        return response()->json(['success' => true, 'data' => ['foto' => null]]);
    }

    /**
     * GET /users/{id}/photo — la imagen, con permiso comprobado.
     *
     * Devuelve los bytes y no una URL porque el archivo está en el disco
     * privado. El navegador no puede pedirla con `<img src>` a secas: la
     * pantalla la trae con el token y la pinta desde memoria.
     */
    public function ver(Request $request, string $id)
    {
        $usuario = User::findOrFail($id);
        $quien   = $request->user();

        $puede = $quien->id === $usuario->id
            || in_array($quien->rol?->nombre, ['admin', 'rrhh'], true);

        if (! $puede) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'No tienes permiso para ver esta foto.'],
            ], 403);
        }

        if (! $usuario->foto || ! Storage::disk('local')->exists($usuario->foto)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Esta cuenta no tiene foto de perfil.'],
            ], 404);
        }

        return Storage::disk('local')->response($usuario->foto);
    }

    /** Borra el archivo anterior si existe. Nunca falla por no encontrarlo. */
    private function borrarArchivo(?string $ruta): void
    {
        if ($ruta && Storage::disk('local')->exists($ruta)) {
            Storage::disk('local')->delete($ruta);
        }
    }
}
