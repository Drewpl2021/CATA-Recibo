<?php

namespace App\Support\Portal;

use App\Models\Portal\Banner;
use App\Models\Portal\Ciclo;
use App\Models\Portal\Cifra;
use App\Models\Portal\Docente;
use App\Models\Portal\DocenteNivel;
use App\Models\Portal\FechaMatricula;
use App\Models\Portal\GrupoGrados;
use App\Models\Portal\Imagen;
use App\Models\Portal\Logro;
use App\Models\Portal\Nivel;
use App\Models\Portal\NivelPropuesta;
use App\Models\Portal\Noticia;
use App\Models\Portal\Pagina;
use App\Models\Portal\Pilar;
use App\Models\Portal\Plataforma;
use App\Models\Portal\Pregunta;
use App\Models\Portal\ProcesoMatricula;
use App\Models\Portal\Proyecto;
use App\Models\Portal\ProyectoCategoria;
use App\Models\Portal\ProyectoImagen;
use App\Models\Portal\RedSocial;
use App\Models\Portal\Seccion;
use App\Models\Portal\Sede;
use App\Models\Portal\Universidad;
use App\Models\Portal\Vacante;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga en las tablas del portal un escenario de los ejemplos del contrato
 * (tests/Fixtures/portal/mock): el contenido completo de los 26 endpoints.
 *
 * Sirve para las pruebas, para tener contenido al desarrollar y para la
 * demostración. Lee los archivos como el portal: lo que un escenario no trae
 * lo toma de `tipico`.
 *
 * Reemplaza TODO el contenido del portal (dentro de una transacción: o carga
 * el escenario entero o no toca nada). Los ejemplos traen datos y fotos de
 * prueba, así que en producción no se usa sin pensarlo: ver el comando
 * `portal:importar`.
 */
class ImportadorDeEjemplos
{
    public const ESCENARIOS = ['tipico', 'corto', 'largo', 'sin-foto', 'alt-vacio', 'vacio'];

    /**
     * Las tablas del portal, de las que dependen a las que son dependidas:
     * en este orden se pueden vaciar sin romper ninguna llave.
     */
    public const TABLAS = [
        'portal_proyecto_imagenes', 'portal_proyectos', 'portal_proyecto_categorias',
        'portal_docentes', 'portal_docentes_niveles',
        'portal_ciclos', 'portal_universidades',
        'portal_banners', 'portal_cifras', 'portal_niveles', 'portal_sedes', 'portal_noticias',
        'portal_procesos_matricula', 'portal_vacantes', 'portal_fechas_matricula',
        'portal_preguntas', 'portal_grados',
        'portal_paginas', 'portal_logros',
        'portal_pilares', 'portal_niveles_propuesta', 'portal_plataformas',
        'portal_redes', 'portal_secciones',
        'portal_imagenes',
    ];

    public function __construct(
        private readonly string $raiz,
        private readonly string $escenario = 'tipico',
    ) {
        if (! in_array($escenario, self::ESCENARIOS, true)) {
            throw new RuntimeException("No hay un escenario «{$escenario}». Están: " . implode(', ', self::ESCENARIOS) . '.');
        }

        if (! is_dir("{$raiz}/tipico")) {
            throw new RuntimeException("No encuentro los ejemplos en {$raiz}.");
        }
    }

    /** Si ya hay contenido del portal cargado (para no pisarlo sin avisar). */
    public static function hayContenido(): bool
    {
        return collect(self::TABLAS)->contains(fn ($tabla) => DB::table($tabla)->exists());
    }

    /** @return array<string, int> filas cargadas por tabla */
    public function importar(): array
    {
        DB::transaction(function () {
            self::vaciar();

            $this->sitio();
            $this->inicio();
            $this->matricula();
            $this->contacto();
            $this->proyectos();
            $this->nosotros();
            $this->propuesta();
        });

        return collect(self::TABLAS)
            ->reverse()
            ->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->count()])
            ->all();
    }

    public static function vaciar(): void
    {
        foreach (self::TABLAS as $tabla) {
            DB::table($tabla)->delete();
        }
    }

    // ───────── Por página ─────────

    private function sitio(): void
    {
        $sitio = $this->leer('sitio');

        foreach ($sitio['redes'] as $i => $red) {
            RedSocial::create($red + ['orden' => $i + 1]);
        }

        unset($sitio['redes']);
        $this->seccion('sitio', $sitio);
    }

    private function inicio(): void
    {
        foreach ($this->leer('banners') as $i => $b) {
            Banner::create([
                'clave'           => $b['id'],
                'categoria'       => $b['categoria'],
                'titulo'          => $b['titulo'],
                'fecha'           => $b['fecha'],
                'imagen_id'       => $this->imagen($b['imagen']),
                'imagen_movil_id' => $this->imagen($b['imagenMovil']),
                'foco_x'          => $b['puntoFocal']['x'] ?? null,
                'foco_y'          => $b['puntoFocal']['y'] ?? null,
                'texto_en_imagen' => $b['textoEnImagen'],
                'lado_accion'     => $b['ladoAccion'],
                'accion'          => $b['accion'],
                'orden'           => $i + 1,
            ]);
        }

        $this->cifras('portada', $this->leer('cifras'));
        $this->seccion('quienes-somos', $this->leer('quienes-somos'));

        $niveles = $this->leer('niveles');
        foreach ($niveles['items'] as $i => $n) {
            Nivel::create([
                'clave'          => $n['id'],
                'nombre'         => $n['nombre'],
                'rango'          => $n['rango'],
                'sede'           => $n['sede'],
                'reconocimiento' => $n['reconocimiento'],
                'imagen_id'      => $this->imagen($n['foto']),
                'orden'          => $i + 1,
            ]);
        }
        unset($niveles['items']);
        $this->seccion('niveles', $niveles);

        foreach ($this->leer('sedes') as $i => $s) {
            Sede::create([
                'clave'          => $s['id'],
                'nombre'         => $s['nombre'],
                'direccion'      => $s['direccion'],
                'referencia'     => $s['referencia'],
                'distrito'       => $s['distrito'],
                'telefono'       => $s['telefono'],
                'nota'           => $s['nota'],
                'imagen_id'      => $this->imagen($s['foto']),
                'mapa_url'       => $s['mapaUrl'],
                'correo'         => $s['correo'],
                'horario'        => $s['horario'],
                'mapa_embed_url' => $s['mapaEmbedUrl'],
                'orden'          => $i + 1,
            ]);
        }

        // Los ejemplos traen solo la primera página de noticias.
        foreach ($this->leer('noticias') as $i => $n) {
            Noticia::create([
                'clave'     => $n['id'],
                'slug'      => $n['slug'],
                'tipo'      => $n['tipo'],
                'titulo'    => $n['titulo'],
                'resumen'   => $n['resumen'],
                'fecha'     => $n['fecha'],
                'imagen_id' => $this->imagen($n['foto']),
                'enlace'    => $n['enlace'],
                'orden'     => $i + 1,
            ]);
        }
    }

    private function matricula(): void
    {
        $this->seccion('matricula-cabecera', $this->leer('matricula-cabecera'));

        foreach ($this->leer('matricula-procesos') as $i => $p) {
            ProcesoMatricula::create([
                'clave'   => $p['id'],
                'nombre'  => $p['nombre'],
                'resumen' => $p['resumen'],
                'pasos'   => $p['pasos'],
                'listas'  => $p['listas'],
                'orden'   => $i + 1,
            ]);
        }

        $vacantes = $this->leer('matricula-vacantes');
        foreach ($vacantes['niveles'] as $i => $v) {
            Vacante::create([
                'clave'          => $v['id'],
                'nivel'          => $v['nivel'],
                'detalle'        => $v['detalle'],
                'disponibilidad' => $v['estado'],
                'vacantes'       => $v['vacantes'],
                'orden'          => $i + 1,
            ]);
        }
        unset($vacantes['niveles']);
        $this->seccion('matricula-vacantes', $vacantes);

        $fechas = $this->leer('matricula-fechas');
        foreach ($fechas['items'] as $i => $f) {
            FechaMatricula::create([
                'clave'   => $f['id'],
                'titulo'  => $f['titulo'],
                'inicio'  => $f['inicio'],
                'fin'     => $f['fin'],
                'detalle' => $f['detalle'],
                'orden'   => $i + 1,
            ]);
        }
        unset($fechas['items']);
        $this->seccion('matricula-fechas', $fechas);

        foreach ($this->leer('matricula-preguntas') as $i => $p) {
            Pregunta::create([
                'clave'     => $p['id'],
                'pregunta'  => $p['pregunta'],
                'respuesta' => $p['respuesta'],
                'orden'     => $i + 1,
            ]);
        }

        foreach ($this->leer('grados') as $i => $g) {
            GrupoGrados::create(['nivel' => $g['nivel'], 'opciones' => $g['opciones'], 'orden' => $i + 1]);
        }

        $this->seccion('solicitud', $this->leer('solicitud'));
    }

    private function contacto(): void
    {
        $this->seccion('contacto', $this->leer('contacto'));
    }

    private function proyectos(): void
    {
        $pagina = $this->leer('proyectos');

        $categorias = [];
        foreach ($pagina['categorias'] as $i => $c) {
            $categorias[$c['id']] = ProyectoCategoria::create([
                'clave'  => $c['id'],
                'nombre' => $c['nombre'],
                'orden'  => $i + 1,
            ])->id;
        }

        foreach ($pagina['proyectos'] as $i => $resumen) {
            // El detalle completo está en su propio archivo.
            $p = $this->leer("proyecto--{$resumen['slug']}");

            $proyecto = Proyecto::create([
                'clave'        => $p['id'],
                'slug'         => $p['slug'],
                'titulo'       => $p['titulo'],
                'lema'         => $p['lema'],
                'resumen'      => $p['resumen'],
                'categoria_id' => $categorias[$p['categoria']]
                    ?? throw new RuntimeException("El proyecto «{$p['slug']}» apunta a la categoría «{$p['categoria']}», que no está."),
                'niveles'      => $p['niveles'],
                'etapa'        => $p['estado'],
                'fecha'        => $p['fecha'],
                'imagen_id'    => $this->imagen($p['foto']),
                'descripcion'  => $p['descripcion'],
                'secciones'    => $p['secciones'],
                'orden'        => $i + 1,
            ]);

            foreach ($p['galeria'] as $j => $foto) {
                ProyectoImagen::create([
                    'proyecto_id' => $proyecto->id,
                    'imagen_id'   => $this->imagen($foto),
                    'orden'       => $j + 1,
                ]);
            }
        }

        unset($pagina['categorias'], $pagina['proyectos']);
        $this->seccion('proyectos', $pagina);
    }

    private function nosotros(): void
    {
        $indice = $this->leer('nosotros');
        $resumenes = array_column($indice['paginas'], 'resumen', 'slug');
        $enElIndice = array_flip(array_keys($resumenes));

        // Todas las subpáginas que el portal puede abrir en este escenario.
        $slugs = collect($this->archivosQueEmpiezanCon('nosotros-pagina--'))
            ->map(fn ($archivo) => substr($archivo, strlen('nosotros-pagina--')))
            ->sortBy(fn ($slug) => [$enElIndice[$slug] ?? PHP_INT_MAX, $slug])
            ->values();

        foreach (array_keys($resumenes) as $slug) {
            if (! $slugs->contains($slug)) {
                throw new RuntimeException("El índice de Nosotros nombra «{$slug}», pero no hay su página.");
            }
        }

        foreach ($slugs as $i => $slug) {
            $p = $this->leer("nosotros-pagina--{$slug}");

            Pagina::create([
                'slug'    => $p['slug'],
                'titulo'  => $p['titulo'],
                'bajada'  => $p['bajada'],
                'resumen' => $resumenes[$slug] ?? null,
                'bloques' => ImagenesEnJson::guardar($p['bloques']),
                'orden'   => $i + 1,
            ]);
        }

        unset($indice['paginas']);
        $this->seccion('nosotros', $indice);

        $docentes = $this->leer('docentes');
        $niveles = [];
        foreach ($docentes['niveles'] as $i => $n) {
            $niveles[$n['id']] = DocenteNivel::create([
                'clave'  => $n['id'],
                'nombre' => $n['nombre'],
                'orden'  => $i + 1,
            ])->id;
        }
        foreach ($docentes['docentes'] as $i => $d) {
            Docente::create([
                'clave'     => $d['id'],
                'nombre'    => $d['nombre'],
                'cargo'     => $d['cargo'],
                'nivel_id'  => $niveles[$d['nivel']]
                    ?? throw new RuntimeException("El docente «{$d['id']}» apunta al nivel «{$d['nivel']}», que no está."),
                'imagen_id' => $this->imagen($d['foto']),
                'orden'     => $i + 1,
            ]);
        }

        $logros = $this->leer('logros');
        $this->cifras('logros', $logros['cifras']);
        foreach ($logros['logros'] as $i => $l) {
            Logro::create([
                'clave'       => $l['id'],
                'titulo'      => $l['titulo'],
                'categoria'   => $l['categoria'],
                'anio'        => $l['anio'],
                'nivel'       => $l['nivel'],
                'descripcion' => $l['descripcion'],
                'imagen_id'   => $this->imagen($l['foto']),
                'destacado'   => $l['destacado'],
                'fuente'      => $l['fuente'],
                'aclaracion'  => $l['aclaracion'],
                'orden'       => $i + 1,
            ]);
        }
        unset($logros['cifras'], $logros['logros']);
        $this->seccion('logros', $logros);
    }

    private function propuesta(): void
    {
        $this->seccion('propuesta-cabecera', $this->leer('propuesta-cabecera'));

        $pilares = $this->leer('propuesta-pilares');
        $this->cifras('propuesta', $pilares['cifras']);
        foreach ($pilares['pilares'] as $i => $p) {
            Pilar::create([
                'clave'  => $p['id'],
                'titulo' => $p['titulo'],
                'texto'  => $p['texto'],
                'puntos' => $p['puntos'],
                'orden'  => $i + 1,
            ]);
        }
        unset($pilares['cifras'], $pilares['pilares']);
        $this->seccion('propuesta-pilares', $pilares);

        foreach ($this->leer('propuesta-niveles') as $i => $n) {
            NivelPropuesta::create([
                'clave'       => $n['id'],
                'nombre'      => $n['nombre'],
                'rango'       => $n['rango'],
                'descripcion' => $n['descripcion'],
                'rasgos'      => $n['rasgos'],
                'talleres'    => $n['talleres'],
                'imagen_id'   => $this->imagen($n['foto']),
                'orden'       => $i + 1,
            ]);
        }

        $academia = $this->leer('propuesta-academia');
        foreach ($academia['universidades'] as $i => $u) {
            $universidad = Universidad::create(['clave' => $u['id'], 'nombre' => $u['nombre'], 'orden' => $i + 1]);

            foreach ($u['ciclos'] as $j => $c) {
                Ciclo::create([
                    'clave'          => $c['id'],
                    'universidad_id' => $universidad->id,
                    'nombre'         => $c['nombre'],
                    'turno'          => $c['turno'],
                    'modalidades'    => $c['modalidades'],
                    'precio'         => $c['precio'],
                    'precio_virtual' => $c['precioVirtual'],
                    'inicio'         => $c['inicio'],
                    'fin'            => $c['fin'],
                    'orden'          => $j + 1,
                ]);
            }
        }
        unset($academia['universidades']);
        $this->seccion('propuesta-academia', $academia);

        foreach ($this->leer('propuesta-plataformas') as $i => $p) {
            Plataforma::create([
                'clave'       => $p['id'],
                'nombre'      => $p['nombre'],
                'descripcion' => $p['descripcion'],
                'funciones'   => $p['funciones'],
                'enlace'      => $p['enlace'],
                'aclaracion'  => $p['aclaracion'],
                'orden'       => $i + 1,
            ]);
        }
    }

    // ───────── Ayudas ─────────

    /** El `data` de un archivo del escenario, o de `tipico` si el escenario no lo trae. */
    private function leer(string $archivo): array
    {
        foreach ([$this->escenario, 'tipico'] as $carpeta) {
            $ruta = "{$this->raiz}/{$carpeta}/{$archivo}.json";

            if (is_file($ruta)) {
                return json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR)['data'];
            }
        }

        throw new RuntimeException("No hay «{$archivo}.json» ni en «{$this->escenario}» ni en «tipico».");
    }

    /** Los archivos con ese prefijo en el escenario o en `tipico`, sin repetir ni extensión. */
    private function archivosQueEmpiezanCon(string $prefijo): array
    {
        return collect([$this->escenario, 'tipico'])
            ->flatMap(fn ($carpeta) => glob("{$this->raiz}/{$carpeta}/{$prefijo}*.json") ?: [])
            ->map(fn ($ruta) => basename($ruta, '.json'))
            ->unique()
            ->values()
            ->all();
    }

    private function seccion(string $clave, array $contenido): void
    {
        Seccion::create(['clave' => $clave, 'contenido' => ImagenesEnJson::guardar($contenido)]);
    }

    private function cifras(string $ambito, array $cifras): void
    {
        foreach ($cifras as $i => $c) {
            Cifra::create([
                'ambito'   => $ambito,
                'clave'    => $c['id'],
                'valor'    => $c['valor'],
                'sufijo'   => $c['sufijo'],
                'etiqueta' => $c['etiqueta'],
                'orden'    => $i + 1,
            ]);
        }
    }

    private function imagen(?array $imagen): ?string
    {
        return $imagen === null ? null : ImagenesEnJson::crear($imagen);
    }
}
