<?php

namespace App\Support\Portal;

use Illuminate\Support\Facades\Log;

/**
 * Los bloques de una subpágina de Nosotros (contrato §3.20), armados tipo
 * por tipo y campo por campo, con las mismas reglas que el resto de la API:
 * todas las claves, en el orden del contrato, y ninguna de más.
 *
 * Un bloque que no cumpliría el contrato (un tipo que no existe, una lista
 * sin ítems, un formato de audio que el portal no reproduce) NO se manda: el
 * esquema del portal es estricto y un solo bloque malo le haría descartar la
 * página entera. Se omite, se anota en el registro con la página y su
 * posición, y el resto de la página se muestra. El panel no debería dejar
 * guardarlos; esto es la última red.
 *
 * Recibe los bloques con las imágenes ya resueltas (ImagenesEnJson).
 */
class Bloques
{
    private const FORMATOS_DE_AUDIO = ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav'];
    private const FORMATOS_DE_ENLACE = ['pdf', 'web', 'hoja', 'presentacion', 'documento', 'canva'];
    private const ESTILOS_DE_LISTA = ['vinetas', 'tarjetas'];

    /** El organigrama llega hasta 5 niveles (§3.20). */
    private const NIVELES_DEL_ORGANIGRAMA = 5;

    public static function armar(array $bloques, string $pagina): array
    {
        $armados = [];

        foreach (array_values($bloques) as $posicion => $bloque) {
            $armado = is_array($bloque) ? self::bloque($bloque) : null;

            if ($armado === null) {
                Log::warning('Portal: se omitió un bloque que no cumple el contrato', [
                    'pagina'   => $pagina,
                    'posicion' => $posicion + 1,
                    'tipo'     => is_array($bloque) ? ($bloque['tipo'] ?? null) : null,
                ]);

                continue;
            }

            $armados[] = $armado;
        }

        return $armados;
    }

    private static function bloque(array $b): ?array
    {
        return match ($b['tipo'] ?? null) {
            'texto'       => self::texto($b),
            'destacado'   => self::destacado($b),
            'lista'       => self::lista($b),
            'hitos'       => self::hitos($b),
            'imagen'      => self::imagen($b),
            'personas'    => self::personas($b),
            'mensaje'     => self::mensaje($b),
            'letra'       => self::letra($b),
            'audio'       => self::audio($b),
            'organigrama' => self::organigrama($b),
            'enlaces'     => self::enlaces($b),
            default       => null,
        };
    }

    // ───────── Un método por tipo, en el orden de la tabla del contrato ─────────

    private static function texto(array $b): ?array
    {
        $parrafos = Formato::textos($b['parrafos'] ?? []);

        return $parrafos === [] ? null : [
            'tipo'     => 'texto',
            'titulo'   => Formato::opcional($b['titulo'] ?? null),
            'parrafos' => $parrafos,
            'firma'    => self::firma($b['firma'] ?? null),
        ];
    }

    private static function destacado(array $b): ?array
    {
        $rotulo = Formato::opcional($b['rotulo'] ?? null);
        $texto = Formato::opcional($b['texto'] ?? null);

        return $rotulo === null || $texto === null ? null : [
            'tipo'   => 'destacado',
            'rotulo' => $rotulo,
            'texto'  => $texto,
        ];
    }

    private static function lista(array $b): ?array
    {
        $items = self::sinVacios(array_map(fn ($item) => Formato::opcional($item['texto'] ?? null) === null ? null : [
            'titulo' => Formato::opcional($item['titulo'] ?? null),
            'texto'  => Formato::texto($item['texto']),
            'fuente' => Formato::opcional($item['fuente'] ?? null),
        ], $b['items'] ?? []));

        return $items === [] || ! in_array($b['estilo'] ?? null, self::ESTILOS_DE_LISTA, true) ? null : [
            'tipo'   => 'lista',
            'titulo' => Formato::opcional($b['titulo'] ?? null),
            'estilo' => $b['estilo'],
            'items'  => $items,
        ];
    }

    private static function hitos(array $b): ?array
    {
        $items = self::sinVacios(array_map(
            fn ($hito) => Formato::opcional($hito['fecha'] ?? null) === null || Formato::opcional($hito['texto'] ?? null) === null ? null : [
                'fecha' => Formato::texto($hito['fecha']),
                'texto' => Formato::texto($hito['texto']),
            ],
            $b['items'] ?? [],
        ));

        return $items === [] ? null : [
            'tipo'   => 'hitos',
            'titulo' => Formato::opcional($b['titulo'] ?? null),
            'items'  => $items,
        ];
    }

    private static function imagen(array $b): ?array
    {
        $imagen = Formato::imagenDeArreglo($b['imagen'] ?? null);
        $transcripcion = Formato::textos($b['transcripcion'] ?? []);

        return $imagen === null ? null : [
            'tipo'          => 'imagen',
            'imagen'        => $imagen,
            'pie'           => Formato::opcional($b['pie'] ?? null),
            'transcripcion' => $transcripcion === [] ? null : $transcripcion,
        ];
    }

    private static function personas(array $b): ?array
    {
        $personas = self::sinVacios(array_map(
            fn ($p) => Formato::opcional($p['nombre'] ?? null) === null || Formato::opcional($p['id'] ?? null) === null ? null : [
                'id'     => Formato::texto($p['id']),
                'nombre' => Formato::texto($p['nombre']),
                'cargo'  => Formato::texto($p['cargo'] ?? null),
                'foto'   => Formato::imagenDeArreglo($p['foto'] ?? null),
            ],
            $b['personas'] ?? [],
        ));

        return $personas === [] ? null : [
            'tipo'     => 'personas',
            'titulo'   => Formato::opcional($b['titulo'] ?? null),
            'personas' => $personas,
        ];
    }

    private static function mensaje(array $b): ?array
    {
        $parrafos = Formato::textos($b['parrafos'] ?? []);
        $firma = self::firma($b['firma'] ?? null);

        // La carta siempre va firmada: sin firma no es un bloque válido.
        return $parrafos === [] || $firma === null ? null : [
            'tipo'     => 'mensaje',
            'titulo'   => Formato::opcional($b['titulo'] ?? null),
            'parrafos' => $parrafos,
            'firma'    => $firma,
            'foto'     => Formato::imagenDeArreglo($b['foto'] ?? null),
        ];
    }

    private static function letra(array $b): ?array
    {
        $partes = self::sinVacios(array_map(function ($parte) {
            $versos = Formato::textos($parte['versos'] ?? []);

            return $versos === [] ? null : [
                'titulo' => Formato::texto($parte['titulo'] ?? null),
                'versos' => $versos,
            ];
        }, $b['partes'] ?? []));

        return $partes === [] ? null : [
            'tipo'   => 'letra',
            'titulo' => Formato::opcional($b['titulo'] ?? null),
            'partes' => $partes,
        ];
    }

    private static function audio(array $b): ?array
    {
        $titulo = Formato::opcional($b['titulo'] ?? null);
        $url = Formato::opcional($b['url'] ?? null);

        return $titulo === null || $url === null || ! in_array($b['formato'] ?? null, self::FORMATOS_DE_AUDIO, true) ? null : [
            'tipo'        => 'audio',
            'titulo'      => $titulo,
            'url'         => $url,
            'formato'     => $b['formato'],
            'descripcion' => Formato::opcional($b['descripcion'] ?? null),
        ];
    }

    private static function organigrama(array $b): ?array
    {
        $raiz = self::cargo($b['raiz'] ?? null, 1);

        return $raiz === null ? null : [
            'tipo'   => 'organigrama',
            'titulo' => Formato::opcional($b['titulo'] ?? null),
            'imagen' => Formato::imagenDeArreglo($b['imagen'] ?? null),
            'enlace' => Formato::enlace($b['enlace'] ?? null),
            'raiz'   => $raiz,
        ];
    }

    private static function enlaces(array $b): ?array
    {
        $enlaces = self::sinVacios(array_map(
            fn ($e) => Formato::opcional($e['etiqueta'] ?? null) === null
                || Formato::opcional($e['url'] ?? null) === null
                || ! in_array($e['formato'] ?? null, self::FORMATOS_DE_ENLACE, true) ? null : [
                    'etiqueta'    => Formato::texto($e['etiqueta']),
                    'url'         => Formato::texto($e['url']),
                    'descripcion' => Formato::opcional($e['descripcion'] ?? null),
                    'formato'     => $e['formato'],
                    'peso'        => isset($e['peso']) ? (int) $e['peso'] : null,
                ],
            $b['enlaces'] ?? [],
        ));

        return $enlaces === [] ? null : [
            'tipo'    => 'enlaces',
            'titulo'  => Formato::opcional($b['titulo'] ?? null),
            'enlaces' => $enlaces,
        ];
    }

    // ───────── Ayudas ─────────

    /** { nombre, detalle } de un texto o una carta. */
    private static function firma(?array $firma): ?array
    {
        if ($firma === null || Formato::opcional($firma['nombre'] ?? null) === null) {
            return null;
        }

        return [
            'nombre'  => Formato::texto($firma['nombre']),
            'detalle' => Formato::opcional($firma['detalle'] ?? null),
        ];
    }

    /**
     * Un cargo del organigrama con sus hijos. Lo que pase del quinto nivel no
     * se manda (el contrato no lo admite) y se anota en el registro.
     */
    private static function cargo(?array $cargo, int $nivel): ?array
    {
        if ($cargo === null || Formato::opcional($cargo['cargo'] ?? null) === null) {
            return null;
        }

        $hijos = $cargo['hijos'] ?? [];

        if ($nivel >= self::NIVELES_DEL_ORGANIGRAMA && $hijos !== []) {
            Log::warning('Portal: el organigrama pasa de 5 niveles; se cortó en el quinto', ['cargo' => $cargo['cargo']]);
            $hijos = [];
        }

        return [
            'cargo'   => Formato::texto($cargo['cargo']),
            'detalle' => Formato::opcional($cargo['detalle'] ?? null),
            'hijos'   => self::sinVacios(array_map(fn ($hijo) => self::cargo(is_array($hijo) ? $hijo : null, $nivel + 1), $hijos)),
        ];
    }

    private static function sinVacios(array $lista): array
    {
        return array_values(array_filter($lista, fn ($x) => $x !== null));
    }
}
