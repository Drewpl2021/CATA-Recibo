<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
 * Cada quien sube la suya. RR.HH. y Administración pueden verla y también
 * ponérsela o quitársela desde su ficha (Editar / Nuevo empleado): muchos
 * no entran nunca a Mi Perfil y la foto la toma RR.HH. al contratarlos.
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

        $ruta = $this->guardar($request->user(), $request->file('foto'));

        return response()->json(['success' => true, 'data' => ['foto' => User::versionDeFoto($ruta)]]);
    }

    /** POST /employees/{id}/photo — RR.HH. o Admin, desde la ficha del trabajador. */
    public function subirDeEmpleado(Request $request, string $id)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $ruta = $this->guardar($this->cuentaDelEmpleado($id), $request->file('foto'));

        return response()->json(['success' => true, 'data' => ['foto' => User::versionDeFoto($ruta)]]);
    }

    /** DELETE /employees/{id}/photo — vuelve a sus iniciales. */
    public function quitarDeEmpleado(string $id)
    {
        $usuario = $this->cuentaDelEmpleado($id);

        $this->borrarArchivo($usuario->foto);
        $usuario->update(['foto' => null]);

        return response()->json(['success' => true, 'data' => ['foto' => null]]);
    }

    /** La cuenta del trabajador: la foto vive ahí. Sin cuenta no hay dónde ponerla. */
    private function cuentaDelEmpleado(string $empleadoId): User
    {
        Empleado::findOrFail($empleadoId);
        $usuario = User::where('empleado_id', $empleadoId)->first();

        // Como error de formulario: así el mensaje le llega tal cual a RR.HH.
        if (! $usuario) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'foto' => 'Este trabajador todavía no tiene cuenta de acceso: no hay dónde ponerle la foto.',
            ]);
        }

        return $usuario;
    }

    /** Guarda la foto nueva de la cuenta y borra la anterior. Devuelve la ruta. */
    private function guardar(User $usuario, UploadedFile $archivo): string
    {

        // El nombre lo pone el servidor: el del usuario puede traer barras o
        // tildes. La fecha y un sufijo al azar hacen que cada foto tenga su
        // propio nombre —con solo la fecha, dos cambios en el mismo segundo
        // daban el mismo y la pantalla seguía con la vieja—. La extensión
        // sale del contenido del archivo, no del nombre que trae: una imagen
        // llamada «cara.php» no se guarda como .php.
        $ruta = sprintf(
            'fotos-perfil/%d/foto-%s-%s.%s',
            $usuario->id,
            now()->format('Ymd-His'),
            strtolower(\Illuminate\Support\Str::random(6)),
            $archivo->extension() ?: 'jpg'
        );

        // La anterior se borra de verdad. Acá no hay historial que conservar
        // —es la misma persona, solo que con otra foto— y guardarlas todas
        // sería ir llenando el disco con caras repetidas.
        $this->borrarArchivo($usuario->foto);

        Storage::disk('local')->put($ruta, file_get_contents($archivo->getRealPath()));
        $usuario->update(['foto' => $ruta]);

        return $ruta;
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
            || $quien->puede(\App\Support\Permisos::GESTION);

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
