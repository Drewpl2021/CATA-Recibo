<?php

namespace Tests\Feature;

use App\Models\Portal\Pagina;
use App\Models\Portal\Seccion;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Panel del portal, etapa 10 (contrato 2.13): el menú, los grupos de
 * Nosotros, el pie y los títulos de cada página (/v1/navegacion), el cierre
 * de Inicio (/v1/portada/cierre), las etiquetas del formulario y el nombre en
 * líneas de la cabecera.
 */
class PortalPanelNavegacionTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const SECCIONES = '/api/web-portal/sections';
    private const PAGINAS = '/api/web-portal/pages';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(ImportadorDeEjemplos::carpeta(), 'tipico'))->importar();
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    private function portal(string $ruta): mixed
    {
        $respuesta = $this->getJson("/api/portal{$ruta}")->assertOk();
        $this->assertCumpleElContrato(preg_replace('#^/v1/nosotros/paginas/[a-z0-9-]+$#', '/v1/nosotros/paginas/{slug}', $ruta), $respuesta->getContent());

        return $respuesta->json('data');
    }

    /** Guarda una ficha con lo que tiene ahora, cambiado con $cambio. */
    private function guardar(string $clave, callable $cambio): \Illuminate\Testing\TestResponse
    {
        $actual = $this->getJson(self::SECCIONES . "/{$clave}")->assertOk()->json('data');

        return $this->putJson(self::SECCIONES . "/{$clave}", $cambio($actual['contenido']) + ['version' => $actual['version']]);
    }

    private function slugsDelMenu(): array
    {
        return collect($this->portal('/v1/navegacion')['nosotros'])->flatMap(fn ($g) => array_column($g['paginas'], 'slug'))->all();
    }

    // ───────── Lo que ya estaba, vuelve a guardarse igual ─────────

    public function test_guardar_cada_ficha_tal_cual_deja_al_portal_identico_al_ejemplo(): void
    {
        foreach (['navegacion', 'titulos', 'portada-cierre', 'sitio', 'solicitud'] as $clave) {
            $this->guardar($clave, fn ($c) => $c)->assertOk();
        }

        $this->assertIgualAlEjemplo($this->ejemplo('tipico', 'navegacion.json')['data'], $this->portal('/v1/navegacion'));
        $this->assertIgualAlEjemplo($this->ejemplo('tipico', 'cierre.json')['data'], $this->portal('/v1/portada/cierre'));
        $this->assertSame(['Colegio Adventista', 'Túpac Amaru'], $this->portal('/v1/sitio')['nombreEnLineas']);
        $this->assertSame('Escribir por WhatsApp', $this->portal('/v1/solicitud')['etiquetas']['boton']);
    }

    // ───────── El menú ─────────

    public function test_el_menu_cambia_de_nombres_de_orden_y_de_paginas(): void
    {
        $this->guardar('navegacion', function ($c) {
            $c['menu'] = [
                ['pagina' => 'inicio', 'etiqueta' => '  Portada  '],
                ['pagina' => 'matricula', 'etiqueta' => 'Admisión'],
                ['pagina' => 'contacto', 'etiqueta' => 'Contacto'],
            ];
            $c['pie']['redes'] = 'Redes del colegio';

            return $c;
        })->assertOk();

        $navegacion = $this->portal('/v1/navegacion');
        $this->assertSame([
            ['pagina' => 'inicio', 'etiqueta' => 'Portada'],
            ['pagina' => 'matricula', 'etiqueta' => 'Admisión'],
            ['pagina' => 'contacto', 'etiqueta' => 'Contacto'],
        ], $navegacion['menu']);
        $this->assertSame('Redes del colegio', $navegacion['pie']['redes']);
    }

    public function test_las_reglas_del_menu(): void
    {
        $con = fn (array $menu) => $this->guardar('navegacion', fn ($c) => ['menu' => $menu] + $c);

        $con([])->assertJsonValidationErrors('menu');
        $con([['pagina' => 'inicio', 'etiqueta' => 'A'], ['pagina' => 'inicio', 'etiqueta' => 'B']])->assertJsonValidationErrors('menu.1.pagina');
        $con([['pagina' => 'blog', 'etiqueta' => 'Blog']])->assertJsonValidationErrors('menu.0.pagina');
        $con([['pagina' => 'inicio', 'etiqueta' => str_repeat('a', 25)]])->assertJsonValidationErrors('menu.0.etiqueta');
    }

    // ───────── Los grupos de Nosotros ─────────

    public function test_un_grupo_nuevo_recibe_paginas_y_sale_en_el_menu(): void
    {
        $this->guardar('navegacion', function ($c) {
            $c['grupos'][] = ['id' => null, 'titulo' => 'Comunidad'];
            $c['grupos'][0]['titulo'] = 'Nuestra institución';

            return $c;
        })->assertOk()->assertJsonPath('data.contenido.grupos.3', ['id' => 'comunidad', 'titulo' => 'Comunidad']);

        // Sin páginas, el grupo no sale en el menú.
        $this->assertSame(['Nuestra institución', 'Gestión', 'Documentos'], array_column($this->portal('/v1/navegacion')['nosotros'], 'titulo'));

        // Una subpágina nueva, con cualquier dirección: no necesita código en el portal.
        $this->postJson(self::PAGINAS, [
            'slug' => 'asociacion-de-padres', 'titulo' => 'Asociación de Padres de Familia',
            'grupo' => 'comunidad', 'nombre' => 'APAFA', 'estado' => 'publicado',
        ])->assertCreated()->assertJsonPath('data.grupoTitulo', 'Comunidad');

        $comunidad = collect($this->portal('/v1/navegacion')['nosotros'])->firstWhere('id', 'comunidad');
        $this->assertSame([['slug' => 'asociacion-de-padres', 'etiqueta' => 'APAFA']], $comunidad['paginas']);
        $this->assertSame([], $this->portal('/v1/nosotros/paginas/asociacion-de-padres')['bloques']);
    }

    public function test_un_grupo_con_paginas_no_se_puede_quitar(): void
    {
        $this->guardar('navegacion', function ($c) {
            $c['grupos'] = array_values(array_filter($c['grupos'], fn ($g) => $g['id'] !== 'documentos'));

            return $c;
        })->assertJsonValidationErrors(['grupos' => 'El grupo «Documentos» tiene páginas (como «Instrumentos de Gestión»). Pásalas a otro grupo o sácalas del menú antes de quitarlo.']);

        // Con sus páginas fuera del menú, sí.
        foreach (Pagina::where('grupo', 'documentos')->get() as $pagina) {
            $this->putJson(self::PAGINAS . "/{$pagina->id}", ['grupo' => null])->assertOk()->assertJsonPath('data.nombre', null);
        }
        $this->guardar('navegacion', function ($c) {
            $c['grupos'] = array_values(array_filter($c['grupos'], fn ($g) => $g['id'] !== 'documentos'));

            return $c;
        })->assertOk();

        $this->assertSame(['institucion', 'gestion'], array_column($this->portal('/v1/navegacion')['nosotros'], 'id'));
    }

    public function test_un_grupo_tiene_tope_de_doce_paginas(): void
    {
        // Institución tiene 8: entran 4 más.
        foreach (range(1, 4) as $n) {
            $this->postJson(self::PAGINAS, ['slug' => "extra-{$n}", 'titulo' => "Extra {$n}", 'grupo' => 'institucion', 'nombre' => "Extra {$n}", 'estado' => 'publicado'])->assertCreated();
        }

        $this->postJson(self::PAGINAS, ['slug' => 'extra-5', 'titulo' => 'Extra 5', 'grupo' => 'institucion', 'nombre' => 'Extra 5', 'estado' => 'publicado'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.contrato.0', 'El portal descartaría el menú y los títulos del sitio: hay más elementos de los que el portal muestra (en /data/nosotros/0/paginas).');
        $this->assertFalse(Pagina::where('slug', 'extra-5')->exists());

        // En borrador no cuenta: no sale en el menú.
        $this->postJson(self::PAGINAS, ['slug' => 'extra-5', 'titulo' => 'Extra 5', 'grupo' => 'institucion', 'nombre' => 'Extra 5'])->assertCreated();
    }

    public function test_reglas_del_lugar_de_una_pagina_en_el_menu(): void
    {
        $himno = Pagina::where('slug', 'himno')->firstOrFail();

        $this->putJson(self::PAGINAS . "/{$himno->id}", ['grupo' => 'no-existe'])->assertJsonValidationErrors('grupo');
        $this->putJson(self::PAGINAS . "/{$himno->id}", ['grupo' => 'gestion', 'nombre' => str_repeat('n', 41)])->assertJsonValidationErrors('nombre');

        // Cambia de grupo y de nombre: el menú lo sigue, en el orden de las páginas.
        $this->putJson(self::PAGINAS . "/{$himno->id}", ['grupo' => 'gestion', 'nombre' => 'Himno del colegio'])->assertOk();
        $gestion = collect($this->portal('/v1/navegacion')['nosotros'])->firstWhere('id', 'gestion');
        $this->assertSame('Himno del colegio', $gestion['paginas'][0]['etiqueta']);

        // Sin grupo, sale del menú (y el portal deja de abrirla).
        $this->putJson(self::PAGINAS . "/{$himno->id}", ['grupo' => null])->assertOk();
        $this->assertNotContains('himno', $this->slugsDelMenu());
    }

    /** El pendiente de la parte A de Nosotros: en borrador, la subpágina ya no sale en el menú del portal. */
    public function test_una_subpagina_en_borrador_sale_del_menu_y_vuelve_al_publicarla(): void
    {
        $himno = Pagina::where('slug', 'himno')->firstOrFail();
        $this->assertContains('himno', $this->slugsDelMenu());

        $this->patchJson(self::PAGINAS . "/{$himno->id}/status", ['estado' => 'borrador'])->assertOk();
        $this->assertNotContains('himno', $this->slugsDelMenu());
        $this->getJson('/api/portal/v1/nosotros/paginas/himno')->assertNotFound();

        $this->patchJson(self::PAGINAS . "/{$himno->id}/status", ['estado' => 'publicado'])->assertOk();
        $this->assertContains('himno', $this->slugsDelMenu());

        // Un grupo con todas sus páginas en borrador no sale.
        foreach (Pagina::where('grupo', 'documentos')->get() as $pagina) {
            $this->patchJson(self::PAGINAS . "/{$pagina->id}/status", ['estado' => 'borrador'])->assertOk();
        }
        $this->assertSame(['institucion', 'gestion'], array_column($this->portal('/v1/navegacion')['nosotros'], 'id'));
    }

    // ───────── Los títulos de cada página ─────────

    public function test_los_titulos(): void
    {
        $this->guardar('titulos', function ($c) {
            $c['inicio']['sedesVarias'] = 'Nuestras {n} sedes';
            $c['logros']['destacados'] = 'Lo más destacado';

            return $c;
        })->assertOk();

        $titulos = $this->portal('/v1/navegacion')['titulos'];
        $this->assertSame('Nuestras {n} sedes', $titulos['inicio']['sedesVarias']);
        $this->assertSame('Lo más destacado', $titulos['logros']['destacados']);

        $this->guardar('titulos', fn ($c) => array_replace_recursive($c, ['inicio' => ['sedesVarias' => 'Nuestras sedes']]))
            ->assertJsonValidationErrors('inicio.sedesVarias');
        $this->guardar('titulos', fn ($c) => array_replace_recursive($c, ['matricula' => ['accionPrincipal' => str_repeat('a', 33)]]))
            ->assertJsonValidationErrors(['matricula.accionPrincipal' => 'botón principal de Matrícula']);
        $this->guardar('titulos', function ($c) {
            unset($c['contacto']['mapa']);

            return $c;
        })->assertJsonValidationErrors('contacto.mapa');
    }

    // ───────── El cierre de Inicio ─────────

    public function test_el_cierre(): void
    {
        $boton = fn (string $etiqueta, string $ruta) => ['etiqueta' => $etiqueta, 'destino' => ['tipo' => 'pagina', 'ruta' => $ruta]];

        $this->guardar('portada-cierre', fn ($c) => ['titulo' => '¿Nos visitas?', 'acciones' => [$boton('Matrícula 2027', '/matricula')]])->assertOk();
        $this->assertSame(['titulo' => '¿Nos visitas?', 'acciones' => [$boton('Matrícula 2027', '/matricula')]], $this->portal('/v1/portada/cierre'));

        // Sin botones: solo el título.
        $this->guardar('portada-cierre', fn ($c) => ['titulo' => '¿Nos visitas?', 'acciones' => []])->assertOk();
        $this->assertSame([], $this->portal('/v1/portada/cierre')['acciones']);

        $tres = [$boton('A', '/'), $boton('B', '/'), $boton('C', '/')];
        $this->guardar('portada-cierre', fn ($c) => ['titulo' => 'T', 'acciones' => $tres])->assertJsonValidationErrors('acciones');
        $this->guardar('portada-cierre', fn ($c) => ['titulo' => str_repeat('t', 61), 'acciones' => []])->assertJsonValidationErrors('titulo');
        $this->guardar('portada-cierre', fn ($c) => ['titulo' => 'T', 'acciones' => [['etiqueta' => 'A', 'destino' => ['tipo' => 'url', 'url' => 'http://x.pe']]]])
            ->assertJsonValidationErrors(['acciones.0.destino.url' => 'dirección del botón dorado']);
    }

    // ───────── Cambios en fichas que ya estaban ─────────

    public function test_el_nombre_en_lineas_y_las_etiquetas_del_formulario(): void
    {
        $this->guardar('sitio', fn ($c) => ['nombreEnLineas' => ['Colegio Adventista Túpac Amaru']] + $c)->assertOk();
        $this->assertSame(['Colegio Adventista Túpac Amaru'], $this->portal('/v1/sitio')['nombreEnLineas']);

        $this->guardar('sitio', fn ($c) => ['nombreEnLineas' => ['Uno', 'Dos', 'Tres']] + $c)->assertJsonValidationErrors('nombreEnLineas');
        $this->guardar('sitio', fn ($c) => ['nombreEnLineas' => [str_repeat('n', 31)]] + $c)->assertJsonValidationErrors('nombreEnLineas.0');
        $this->guardar('sitio', fn ($c) => ['nombreEnLineas' => []] + $c)->assertJsonValidationErrors('nombreEnLineas');

        $this->guardar('solicitud', fn ($c) => array_replace_recursive($c, ['etiquetas' => ['boton' => 'Enviar por WhatsApp']]))->assertOk();
        $this->assertSame('Enviar por WhatsApp', $this->portal('/v1/solicitud')['etiquetas']['boton']);

        $this->guardar('solicitud', fn ($c) => array_replace_recursive($c, ['etiquetas' => ['boton' => str_repeat('b', 33)]]))
            ->assertJsonValidationErrors(['etiquetas.boton' => 'texto del botón']);
        $this->guardar('solicitud', function ($c) {
            unset($c['etiquetas']['gradoElegir']);

            return $c;
        })->assertJsonValidationErrors('etiquetas.gradoElegir');
    }

    public function test_sin_las_fichas_nuevas_el_portal_dice_que_falta(): void
    {
        Seccion::whereIn('clave', ['navegacion', 'portada-cierre'])->delete();

        $this->getJson('/api/portal/v1/navegacion')->assertStatus(503);
        $this->getJson('/api/portal/v1/portada/cierre')->assertStatus(503);
    }

    public function test_rrhh_no_edita_el_menu(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        foreach (['navegacion', 'titulos', 'portada-cierre'] as $clave) {
            $this->getJson(self::SECCIONES . "/{$clave}")->assertForbidden();
            $this->putJson(self::SECCIONES . "/{$clave}", [])->assertForbidden();
        }
    }
}
