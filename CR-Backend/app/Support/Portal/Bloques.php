<?php

namespace App\Support\Portal;

/**
 * Los bloques de una subpágina de Nosotros (contrato §3.20), armados tipo
 * por tipo y campo por campo, con las mismas reglas que el resto de la API:
 * todas las claves, en el orden del contrato, y ninguna de más.
 *
 * Un bloque que no cumpliría el contrato (un tipo que no existe, una lista
 * sin ítems, un formato de audio que el portal no reproduce, más elementos
 * de los que admite) NO se manda: el esquema del portal es estricto y un solo
 * bloque malo le haría descartar la página entera. Se omite, se anota en el
 * registro con la página, su posición y el porqué, y el resto de la página se
 * muestra. Dentro de un bloque, lo vacío (un párrafo en blanco, un elemento
 * sin texto) se descarta y el bloque sigue.
 *
 * El panel usa esta misma clase para no dejar guardar nada de eso:
 * problemas() recorre los bloques igual que armar() y devuelve, en palabras
 * de quien edita, qué falla y en qué bloque (RevisionDeBloques).
 *
 * Recibe los bloques con las imágenes ya resueltas (ImagenesEnJson).
 */
class Bloques
{
    public const FORMATOS_DE_AUDIO = ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav'];
    public const FORMATOS_DE_ENLACE = ['pdf', 'web', 'hoja', 'presentacion', 'documento', 'canva'];
    public const ESTILOS_DE_LISTA = ['vinetas', 'tarjetas'];

    /** Cuántos bloques por página (§3.20). */
    public const MAXIMO_DE_BLOQUES = 30;

    /** El organigrama llega hasta 5 niveles y 12 cargos debajo de cada uno (§3.20). */
    private const NIVELES_DEL_ORGANIGRAMA = 5;
    private const HIJOS_POR_CARGO = 12;

    /** Lo que el portal muestra de la página: los bloques que cumplen el contrato. */
    public static function armar(array $bloques, string $pagina): array
    {
        return self::recorrer($bloques, new RevisionDeBloques($pagina));
    }

    /**
     * Qué no cumpliría el contrato, bloque por bloque: lo que armar() omitiría
     * o descartaría. Vacío = la página se puede guardar tal cual.
     */
    public static function problemas(array $bloques): array
    {
        $revision = new RevisionDeBloques();

        if (count($bloques) > self::MAXIMO_DE_BLOQUES) {
            $revision->anotarEnLaPagina('La página tiene ' . count($bloques) . ' bloques: admite hasta ' . self::MAXIMO_DE_BLOQUES . '.');
        }

        self::recorrer($bloques, $revision);

        return $revision->problemas();
    }

    private static function recorrer(array $bloques, RevisionDeBloques $r): array
    {
        $armados = [];

        foreach (array_values($bloques) as $posicion => $bloque) {
            $r->empezarBloque($posicion + 1, is_array($bloque) ? ($bloque['tipo'] ?? null) : null);
            $armado = is_array($bloque) ? self::bloque($bloque, $r) : null;

            if (! is_array($bloque)) {
                $r->anotar('no es un bloque.');
            }

            if ($armado === null) {
                $r->omitirBloque();

                continue;
            }

            $armados[] = $armado;
        }

        return $armados;
    }

    private static function bloque(array $b, RevisionDeBloques $r): ?array
    {
        return match ($b['tipo'] ?? null) {
            'texto'       => self::texto($b, $r),
            'destacado'   => self::destacado($b, $r),
            'lista'       => self::lista($b, $r),
            'hitos'       => self::hitos($b, $r),
            'imagen'      => self::imagen($b, $r),
            'personas'    => self::personas($b, $r),
            'mensaje'     => self::mensaje($b, $r),
            'letra'       => self::letra($b, $r),
            'audio'       => self::audio($b, $r),
            'organigrama' => self::organigrama($b, $r),
            'enlaces'     => self::enlaces($b, $r),
            default       => self::noExiste($b, $r),
        };
    }

    private static function noExiste(array $b, RevisionDeBloques $r): null
    {
        $tipo = $b['tipo'] ?? null;
        $r->anotar(is_string($tipo) ? "el tipo «{$tipo}» no existe." : 'no dice de qué tipo es.');

        return null;
    }

    // ───────── Un método por tipo, en el orden de la tabla del contrato ─────────

    private static function texto(array $b, RevisionDeBloques $r): ?array
    {
        $parrafos = self::textos($b['parrafos'] ?? [], 'párrafo', 1, 20, $r);

        return $parrafos === null ? null : [
            'tipo'     => 'texto',
            'titulo'   => Formato::opcional($b['titulo'] ?? null),
            'parrafos' => $parrafos,
            'firma'    => self::firma($b['firma'] ?? null, $r),
        ];
    }

    private static function destacado(array $b, RevisionDeBloques $r): ?array
    {
        $rotulo = Formato::opcional($b['rotulo'] ?? null);
        $texto = Formato::opcional($b['texto'] ?? null);

        if ($rotulo === null) {
            $r->anotar('falta el rótulo (Misión, Visión, Lema…).');
        }
        if ($texto === null) {
            $r->anotar('falta el texto.');
        } elseif (mb_strlen($texto) > 200) {
            // El esquema no lo comprueba (el portal lo mostraría), pero el contrato pide hasta 200.
            $r->anotar('el texto tiene ' . mb_strlen($texto) . ' caracteres: admite hasta 200.');
        }

        return $rotulo === null || $texto === null ? null : [
            'tipo'   => 'destacado',
            'rotulo' => $rotulo,
            'texto'  => $texto,
        ];
    }

    private static function lista(array $b, RevisionDeBloques $r): ?array
    {
        $estiloValido = in_array($b['estilo'] ?? null, self::ESTILOS_DE_LISTA, true);
        if (! $estiloValido) {
            $r->anotar('el estilo debe ser «vinetas» o «tarjetas».');
        }

        $items = self::elementos($b['items'] ?? [], 'elemento', 1, 20, $r, function ($item, int $n) use ($r) {
            if (Formato::opcional($item['texto'] ?? null) === null) {
                $r->anotar("el elemento {$n} no tiene texto.");

                return null;
            }

            return [
                'titulo' => Formato::opcional($item['titulo'] ?? null),
                'texto'  => Formato::texto($item['texto']),
                'fuente' => Formato::opcional($item['fuente'] ?? null),
            ];
        });

        return $items === null || ! $estiloValido ? null : [
            'tipo'   => 'lista',
            'titulo' => Formato::opcional($b['titulo'] ?? null),
            'estilo' => $b['estilo'],
            'items'  => $items,
        ];
    }

    private static function hitos(array $b, RevisionDeBloques $r): ?array
    {
        $items = self::elementos($b['items'] ?? [], 'hito', 1, 20, $r, function ($hito, int $n) use ($r) {
            $fecha = Formato::opcional($hito['fecha'] ?? null);
            $texto = Formato::opcional($hito['texto'] ?? null);

            if ($fecha === null || $texto === null) {
                $r->anotar("al hito {$n} le falta " . ($fecha === null ? ($texto === null ? 'la fecha y el texto' : 'la fecha') : 'el texto') . '.');

                return null;
            }

            return ['fecha' => $fecha, 'texto' => $texto];
        });

        return $items === null ? null : [
            'tipo'   => 'hitos',
            'titulo' => Formato::opcional($b['titulo'] ?? null),
            'items'  => $items,
        ];
    }

    private static function imagen(array $b, RevisionDeBloques $r): ?array
    {
        $imagen = Formato::imagenDeArreglo($b['imagen'] ?? null);
        if ($imagen === null) {
            $r->anotar('falta la imagen.');
        }

        $transcripcion = Formato::textos($b['transcripcion'] ?? []);
        if (count($transcripcion) > 20) {
            $r->anotar('la transcripción tiene ' . count($transcripcion) . ' párrafos: admite hasta 20.');
        }

        return $imagen === null || count($transcripcion) > 20 ? null : [
            'tipo'          => 'imagen',
            'imagen'        => $imagen,
            'pie'           => Formato::opcional($b['pie'] ?? null),
            'transcripcion' => $transcripcion === [] ? null : $transcripcion,
        ];
    }

    private static function personas(array $b, RevisionDeBloques $r): ?array
    {
        $personas = self::elementos($b['personas'] ?? [], 'persona', 1, 40, $r, function ($p, int $n) use ($r) {
            if (Formato::opcional($p['nombre'] ?? null) === null) {
                $r->anotar("la persona {$n} no tiene nombre.");

                return null;
            }
            if (Formato::opcional($p['id'] ?? null) === null) {
                $r->anotar("la persona {$n} no tiene id.");

                return null;
            }

            return [
                'id'     => Formato::texto($p['id']),
                'nombre' => Formato::texto($p['nombre']),
                'cargo'  => Formato::texto($p['cargo'] ?? null),
                'foto'   => Formato::imagenDeArreglo($p['foto'] ?? null),
            ];
        });

        return $personas === null ? null : [
            'tipo'     => 'personas',
            'titulo'   => Formato::opcional($b['titulo'] ?? null),
            'personas' => $personas,
        ];
    }

    private static function mensaje(array $b, RevisionDeBloques $r): ?array
    {
        $parrafos = self::textos($b['parrafos'] ?? [], 'párrafo', 1, 12, $r);
        $firma = self::firma($b['firma'] ?? null, $r);

        // La carta siempre va firmada: sin firma no es un bloque válido.
        if ($firma === null && ! is_array($b['firma'] ?? null)) {
            $r->anotar('la carta va firmada: falta el nombre de quien la firma.');
        }

        return $parrafos === null || $firma === null ? null : [
            'tipo'     => 'mensaje',
            'titulo'   => Formato::opcional($b['titulo'] ?? null),
            'parrafos' => $parrafos,
            'firma'    => $firma,
            'foto'     => Formato::imagenDeArreglo($b['foto'] ?? null),
        ];
    }

    private static function letra(array $b, RevisionDeBloques $r): ?array
    {
        $demasiadosVersos = false;

        $partes = self::elementos($b['partes'] ?? [], 'parte', 1, 12, $r, function ($parte, int $n) use ($r, &$demasiadosVersos) {
            $versos = Formato::textos($parte['versos'] ?? []);

            if ($versos === []) {
                $r->anotar("la parte {$n} no tiene versos.");

                return null;
            }
            if (count($versos) > 16) {
                $r->anotar("la parte {$n} tiene " . count($versos) . ' versos: admite hasta 16.');
                $demasiadosVersos = true;
            }

            return [
                'titulo' => Formato::texto($parte['titulo'] ?? null),
                'versos' => $versos,
            ];
        });

        return $partes === null || $demasiadosVersos ? null : [
            'tipo'   => 'letra',
            'titulo' => Formato::opcional($b['titulo'] ?? null),
            'partes' => $partes,
        ];
    }

    private static function audio(array $b, RevisionDeBloques $r): ?array
    {
        $titulo = Formato::opcional($b['titulo'] ?? null);
        $url = Formato::opcional($b['url'] ?? null);
        $formato = in_array($b['formato'] ?? null, self::FORMATOS_DE_AUDIO, true);

        if ($titulo === null) {
            $r->anotar('falta el título.');
        }
        if ($url === null) {
            $r->anotar('falta la dirección del audio.');
        } elseif (! self::esHttps($url)) {
            $r->anotar('la dirección del audio debe empezar con https://.');
        }
        if (! $formato) {
            $r->anotar('el formato debe ser MP3, M4A, OGG o WAV: el portal no reproduce otros.');
        }

        return $titulo === null || $url === null || ! self::esHttps($url) || ! $formato ? null : [
            'tipo'        => 'audio',
            'titulo'      => $titulo,
            'url'         => $url,
            'formato'     => $b['formato'],
            'descripcion' => Formato::opcional($b['descripcion'] ?? null),
        ];
    }

    private static function organigrama(array $b, RevisionDeBloques $r): ?array
    {
        $raiz = is_array($b['raiz'] ?? null) ? $b['raiz'] : null;
        if ($raiz === null || Formato::opcional($raiz['cargo'] ?? null) === null) {
            $r->anotar('falta el cargo de arriba del organigrama.');

            return null;
        }

        $demasiados = false;
        $raiz = self::cargo($raiz, 1, $r, $demasiados);

        $enlace = $b['enlace'] ?? null;
        if (is_array($enlace) && ! self::esHttps(Formato::opcional($enlace['url'] ?? null) ?? '')) {
            $r->anotar('la dirección del enlace debe empezar con https://; el enlace no se muestra.');
            $enlace = null;
        }

        return $demasiados ? null : [
            'tipo'   => 'organigrama',
            'titulo' => Formato::opcional($b['titulo'] ?? null),
            'imagen' => Formato::imagenDeArreglo($b['imagen'] ?? null),
            'enlace' => Formato::enlace($enlace),
            'raiz'   => $raiz,
        ];
    }

    private static function enlaces(array $b, RevisionDeBloques $r): ?array
    {
        $enlaces = self::elementos($b['enlaces'] ?? [], 'documento', 1, 30, $r, function ($e, int $n) use ($r) {
            $etiqueta = Formato::opcional($e['etiqueta'] ?? null);
            $url = Formato::opcional($e['url'] ?? null);
            $peso = $e['peso'] ?? null;

            $falta = match (true) {
                $etiqueta === null                                         => 'no tiene nombre',
                $url === null                                              => 'no tiene dirección',
                ! self::esHttps($url)                                      => 'tiene una dirección que no empieza con https://',
                ! in_array($e['formato'] ?? null, self::FORMATOS_DE_ENLACE, true) => 'no dice su formato (PDF, web, hoja, presentación, documento o Canva)',
                $peso !== null && (! is_numeric($peso) || (int) $peso <= 0) => 'tiene un peso que no es un número de bytes mayor que 0',
                default                                                    => null,
            };

            if ($falta !== null) {
                $r->anotar("el documento {$n} {$falta}.");

                return null;
            }

            return [
                'etiqueta'    => $etiqueta,
                'url'         => $url,
                'descripcion' => Formato::opcional($e['descripcion'] ?? null),
                'formato'     => $e['formato'],
                'peso'        => $peso === null ? null : (int) $peso,
            ];
        });

        return $enlaces === null ? null : [
            'tipo'    => 'enlaces',
            'titulo'  => Formato::opcional($b['titulo'] ?? null),
            'enlaces' => $enlaces,
        ];
    }

    // ───────── Ayudas ─────────

    /**
     * Una lista de textos (párrafos, versos) sin los vacíos, entre $min y
     * $max. null (y el bloque se omite) si no alcanza o se pasa.
     */
    private static function textos(mixed $lista, string $que, int $min, int $max, RevisionDeBloques $r): ?array
    {
        $lista = is_array($lista) ? array_values($lista) : [];

        foreach ($lista as $i => $texto) {
            if (! is_scalar($texto) || trim((string) $texto) === '') {
                $r->anotar("el {$que} " . ($i + 1) . ' está vacío.');
            }
        }

        return self::cuantos(Formato::textos($lista), $que, $min, $max, $r);
    }

    /**
     * Los elementos de un bloque (ítems, personas, documentos): cada uno lo
     * arma $armar (null = se descarta y queda anotado), y después se
     * comprueba cuántos quedan.
     */
    private static function elementos(mixed $lista, string $que, int $min, int $max, RevisionDeBloques $r, callable $armar): ?array
    {
        $lista = is_array($lista) ? array_values($lista) : [];
        $armados = [];

        foreach ($lista as $i => $elemento) {
            $armado = is_array($elemento) ? $armar($elemento, $i + 1) : null;

            if (! is_array($elemento)) {
                $el = in_array($que, ['persona', 'parte'], true) ? 'la' : 'el';
                $r->anotar("{$el} {$que} " . ($i + 1) . ' no se puede leer.');
            }
            if ($armado !== null) {
                $armados[] = $armado;
            }
        }

        return self::cuantos($armados, $que, $min, $max, $r);
    }

    private static function cuantos(array $lista, string $que, int $min, int $max, RevisionDeBloques $r): ?array
    {
        $plural = $que === 'párrafo' ? 'párrafos' : "{$que}s";
        $ninguno = in_array($que, ['persona', 'parte'], true) ? 'ninguna' : 'ningún';

        if (count($lista) < $min) {
            $r->anotar($min === 1 ? "no tiene {$ninguno} {$que}." : "tiene menos de {$min} {$plural}.");

            return null;
        }
        if (count($lista) > $max) {
            $r->anotar('tiene ' . count($lista) . " {$plural}: admite hasta {$max}.");

            return null;
        }

        return $lista;
    }

    /** { nombre, detalle } de un texto o una carta. Sin nombre, no hay firma. */
    private static function firma(mixed $firma, RevisionDeBloques $r): ?array
    {
        if (! is_array($firma)) {
            return null;
        }

        if (Formato::opcional($firma['nombre'] ?? null) === null) {
            $r->anotar('la firma no tiene el nombre de quien firma.');

            return null;
        }

        return [
            'nombre'  => Formato::texto($firma['nombre']),
            'detalle' => Formato::opcional($firma['detalle'] ?? null),
        ];
    }

    /**
     * Un cargo del organigrama con sus hijos. Lo que pase del quinto nivel no
     * se manda (el contrato no lo admite): se corta y se anota. Más de 12
     * cargos debajo de uno omite el organigrama ($demasiados).
     */
    private static function cargo(array $cargo, int $nivel, RevisionDeBloques $r, bool &$demasiados): array
    {
        $nombre = Formato::texto($cargo['cargo'] ?? null);
        $hijos = is_array($cargo['hijos'] ?? null) ? array_values($cargo['hijos']) : [];

        if ($nivel >= self::NIVELES_DEL_ORGANIGRAMA && $hijos !== []) {
            $r->cortarOrganigrama($nombre);
            $hijos = [];
        }

        $armados = [];
        foreach ($hijos as $i => $hijo) {
            if (! is_array($hijo) || Formato::opcional($hijo['cargo'] ?? null) === null) {
                $r->anotar('debajo de «' . $nombre . '», el cargo ' . ($i + 1) . ' no tiene nombre.');

                continue;
            }
            $armados[] = self::cargo($hijo, $nivel + 1, $r, $demasiados);
        }

        if (count($armados) > self::HIJOS_POR_CARGO) {
            $r->anotar("«{$nombre}» tiene " . count($armados) . ' cargos debajo: admite hasta ' . self::HIJOS_POR_CARGO . '.');
            $demasiados = true;
        }

        return [
            'cargo'   => $nombre,
            'detalle' => Formato::opcional($cargo['detalle'] ?? null),
            'hijos'   => $armados,
        ];
    }

    private static function esHttps(string $url): bool
    {
        return str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
