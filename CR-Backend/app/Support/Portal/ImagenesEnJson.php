<?php

namespace App\Support\Portal;

use App\Models\Portal\Imagen;

/**
 * Las imágenes que viven dentro de un JSON guardado: el contenido de
 * portal_secciones y los bloques de las páginas de Nosotros.
 *
 * Ahí no se guarda el objeto Imagen del contrato ({ url, alt, ancho, alto })
 * sino una referencia, { "imagenId": "…" }, a su fila en portal_imagenes. Así
 * toda imagen del portal, esté en una tabla o dentro de un JSON, se sube, se
 * edita (su alt) y se audita de la misma forma.
 *
 *   guardar():  objeto Imagen → fila nueva en portal_imagenes + referencia
 *   resolver(): referencia → objeto Imagen del contrato (una consulta para
 *               todas las del JSON)
 */
class ImagenesEnJson
{
    public const CLAVE = 'imagenId';

    /** Reemplaza cada objeto Imagen del contrato por su referencia, creando su fila. */
    public static function guardar(array $contenido): array
    {
        if (self::esImagen($contenido)) {
            return [self::CLAVE => self::crear($contenido)];
        }

        foreach ($contenido as $clave => $valor) {
            if (is_array($valor)) {
                $contenido[$clave] = self::guardar($valor);
            }
        }

        return $contenido;
    }

    /** Reemplaza cada referencia por el objeto Imagen del contrato. */
    public static function resolver(array $contenido): array
    {
        $ids = [];
        array_walk_recursive($contenido, function ($valor, $clave) use (&$ids) {
            if ($clave === self::CLAVE && is_string($valor)) {
                $ids[] = $valor;
            }
        });

        $imagenes = $ids === [] ? collect() : Imagen::whereIn('id', array_unique($ids))->get()->keyBy('id');

        return self::reemplazar($contenido, $imagenes->all());
    }

    /** El objeto Imagen del contrato, tal como llega en un mock o desde el panel. */
    public static function crear(array $imagen): string
    {
        return Imagen::create([
            'url_externa' => $imagen['url'],
            'alt'         => (string) $imagen['alt'],
            'ancho'       => $imagen['ancho'] ?? null,
            'alto'        => $imagen['alto'] ?? null,
        ])->id;
    }

    /** Las cuatro claves del tipo Imagen (§1.7), ni una más. */
    private static function esImagen(array $valor): bool
    {
        $claves = array_keys($valor);
        sort($claves);

        return $claves === ['alt', 'alto', 'ancho', 'url'];
    }

    private static function reemplazar(array $contenido, array $imagenes): array
    {
        if (array_keys($contenido) === [self::CLAVE]) {
            $imagen = $imagenes[$contenido[self::CLAVE]] ?? null;

            // Una referencia rota no se inventa ni se deja a medias: el panel
            // no deja borrar una imagen en uso, y si aun así pasa, la sección
            // dice qué falta.
            if ($imagen === null) {
                throw SeccionSinConfigurar::falta('imagen', $contenido[self::CLAVE]);
            }

            return $imagen->aContrato();
        }

        foreach ($contenido as $clave => $valor) {
            if (is_array($valor)) {
                $contenido[$clave] = self::reemplazar($valor, $imagenes);
            }
        }

        return $contenido;
    }
}
