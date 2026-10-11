<?php

namespace App\Console\Commands;

use App\Models\Portal\Imagen;
use App\Support\Portal\ImagenesEnJson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Borra las imágenes del portal que ya nadie usa.
 *
 *   php artisan portal:limpiar-imagenes            (las sueltas hace más de 24 horas)
 *   php artisan portal:limpiar-imagenes --simular  (solo dice cuáles)
 *
 * Una imagen queda suelta cuando se reemplaza la foto de algo, se elimina un
 * registro o se sube una y no se llega a guardar el formulario. Se espera un
 * día antes de borrarla: quien la acaba de subir puede estar todavía
 * llenando el formulario donde va.
 *
 * «Usada» es: apuntada por una columna *_imagen_id de alguna tabla del
 * portal, o nombrada como {"imagenId": …} dentro de un JSON (las fichas de
 * portal_secciones y los bloques de las páginas de Nosotros).
 *
 * También borra del disco los archivos que no tienen fila: los deja una
 * subida que falló a mitad de camino.
 */
class LimpiarImagenesDelPortal extends Command
{
    protected $signature = 'portal:limpiar-imagenes
        {--horas=24 : Cuánto tiempo suelta antes de borrarla}
        {--simular : Mostrar lo que se borraría, sin borrar nada}';

    protected $description = 'Borra las imágenes del portal que ya nadie usa';

    /** tabla → columnas que apuntan a portal_imagenes. */
    private const COLUMNAS = [
        'portal_banners'           => ['imagen_id', 'imagen_movil_id'],
        'portal_niveles'           => ['imagen_id'],
        'portal_sedes'             => ['imagen_id'],
        'portal_noticias'          => ['imagen_id'],
        'portal_proyectos'         => ['imagen_id'],
        'portal_proyecto_imagenes' => ['imagen_id'],
        'portal_docentes'          => ['imagen_id'],
        'portal_logros'            => ['imagen_id'],
        'portal_niveles_propuesta' => ['imagen_id'],
    ];

    /** tabla → columna JSON donde van referencias {"imagenId": …}. */
    private const JSON = [
        'portal_secciones' => 'contenido',
        'portal_paginas'   => 'bloques',
    ];

    public function handle(): int
    {
        $usadas = $this->usadas();
        $limite = now()->subHours((int) $this->option('horas'));
        $simular = (bool) $this->option('simular');

        $sueltas = Imagen::where('created_at', '<', $limite)->whereNotIn('id', $usadas)->get();

        foreach ($sueltas as $imagen) {
            $this->line(($simular ? '  se borraría: ' : '  borrada: ') . ($imagen->ruta ?? $imagen->url_externa));

            if (! $simular) {
                if ($imagen->ruta) {
                    Storage::disk('portal')->delete($imagen->ruta);
                }
                $imagen->delete();
            }
        }

        // Archivos en el disco sin fila (una subida que se cortó).
        $registrados = Imagen::whereNotNull('ruta')->pluck('ruta')->flip();
        $perdidos = collect(Storage::disk('portal')->files())
            ->reject(fn ($archivo) => isset($registrados[$archivo]))
            ->filter(fn ($archivo) => Storage::disk('portal')->lastModified($archivo) < $limite->getTimestamp());

        foreach ($perdidos as $archivo) {
            $this->line(($simular ? '  archivo sin fila, se borraría: ' : '  archivo sin fila, borrado: ') . $archivo);

            if (! $simular) {
                Storage::disk('portal')->delete($archivo);
            }
        }

        $this->info(($simular ? 'Se borrarían ' : 'Se borraron ') . $sueltas->count() . ' imagen(es) suelta(s) y ' . $perdidos->count() . ' archivo(s) sin fila.');

        return self::SUCCESS;
    }

    /** Los ids de las imágenes que algo del portal sigue usando. */
    private function usadas(): array
    {
        $ids = [];

        foreach (self::COLUMNAS as $tabla => $columnas) {
            foreach ($columnas as $columna) {
                array_push($ids, ...DB::table($tabla)->whereNotNull($columna)->pluck($columna)->all());
            }
        }

        foreach (self::JSON as $tabla => $columna) {
            foreach (DB::table($tabla)->pluck($columna) as $json) {
                $datos = json_decode((string) $json, true) ?? [];
                array_walk_recursive($datos, function ($valor, $clave) use (&$ids) {
                    if ($clave === ImagenesEnJson::CLAVE && is_string($valor)) {
                        $ids[] = $valor;
                    }
                });
            }
        }

        return array_values(array_unique($ids));
    }
}
