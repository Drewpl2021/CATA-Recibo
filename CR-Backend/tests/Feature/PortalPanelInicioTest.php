<?php

namespace Tests\Feature;

use App\Models\Portal\Banner;
use App\Models\Portal\Cifra;
use App\Models\Portal\Nivel;
use App\Models\Portal\Noticia;
use App\Support\Portal\ImportadorDeEjemplos;
use App\Support\Portal\ValidadorDeContrato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Panel del portal, etapa 6: Inicio. Los banners con su punto focal y su
 * botón, las cifras de Inicio, Quiénes somos, los niveles y las noticias.
 */
class PortalPanelInicioTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const FICHAS = '/api/web-portal/sections/';
    private const BANNERS = '/api/web-portal/banners';
    private const NIVELES = '/api/web-portal/home-levels';
    private const NOTICIAS = '/api/web-portal/news';
    private const CIFRAS = '/api/web-portal/stats';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), 'tipico'))->importar();
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    private function portal(string $ruta): array
    {
        $respuesta = $this->getJson("/api/portal{$ruta}")->assertOk();
        $this->assertCumpleElContrato(strtok($ruta, '?'), $respuesta->getContent());

        return $respuesta->json('data');
    }

    private function guardarFicha(string $clave, array $datos)
    {
        $version = $this->getJson(self::FICHAS . $clave)->assertOk()->json('data.version');

        return $this->putJson(self::FICHAS . $clave, $datos + ['version' => $version]);
    }

    /** Una imagen ya subida (las del ejemplo sirven). */
    private function imagen(): string
    {
        return Banner::first()->imagen_id;
    }

    // ───────── Banners ─────────

    private function banner(array $cambios = []): array
    {
        return array_replace([
            'titulo'          => 'Feria de ciencias 2026',
            'categoria'       => 'evento',
            'fecha'           => '2026-10-20',
            'imagen_id'       => $this->imagen(),
            'imagen_movil_id' => null,
            'foco_x'          => 30,
            'foco_y'          => 60,
            'texto_en_imagen' => false,
            'lado_accion'     => 'izquierda',
            'accion'          => ['etiqueta' => 'Ver las sedes', 'destino' => ['tipo' => 'seccion', 'seccion' => 'sedes']],
            'estado'          => 'publicado',
        ], $cambios);
    }

    public function test_un_banner_nuevo_entra_arriba_con_su_foco_y_su_boton(): void
    {
        $this->postJson(self::BANNERS, $this->banner())->assertCreated()->assertJsonPath('data.orden', 1);

        $primero = $this->portal('/v1/portada/banners')[0];
        $this->assertSame('feria-de-ciencias-2026', $primero['id']);
        $this->assertSame(['x' => 30, 'y' => 60], $primero['puntoFocal']);
        $this->assertSame(['etiqueta' => 'Ver las sedes', 'destino' => ['tipo' => 'seccion', 'seccion' => 'sedes']], $primero['accion']);
        $this->assertSame('izquierda', $primero['ladoAccion']);

        // Los demás bajaron un lugar, sin huecos ni repetidos.
        $this->assertSame(range(1, Banner::count()), Banner::orderBy('orden')->pluck('orden')->all());
    }

    public function test_el_boton_de_un_banner_lleva_a_una_seccion_una_pagina_o_una_direccion(): void
    {
        $id = $this->postJson(self::BANNERS, $this->banner())->json('data.id');

        // A una página: solo se guarda lo de su tipo.
        $this->putJson(self::BANNERS . "/{$id}", ['accion' => ['etiqueta' => 'Matrícula', 'destino' => ['tipo' => 'pagina', 'ruta' => '/matricula', 'seccion' => 'sedes']]])->assertOk();
        // MySQL guarda el JSON con otro orden de claves: se compara sin orden.
        $this->assertEquals(['tipo' => 'pagina', 'ruta' => '/matricula'], Banner::find($id)->accion['destino']);

        $this->putJson(self::BANNERS . "/{$id}", ['accion' => ['etiqueta' => 'Inscríbete', 'destino' => ['tipo' => 'url', 'url' => 'https://forms.gle/abc', 'externo' => true]]])->assertOk();
        $this->assertSame(['tipo' => 'url', 'url' => 'https://forms.gle/abc', 'externo' => true], $this->portal('/v1/portada/banners')[0]['accion']['destino']);

        // Sin botón.
        $this->putJson(self::BANNERS . "/{$id}", ['accion' => null])->assertOk();
        $this->assertNull($this->portal('/v1/portada/banners')[0]['accion']);

        $mal = fn (array $destino) => $this->putJson(self::BANNERS . "/{$id}", ['accion' => ['etiqueta' => 'Ir', 'destino' => $destino]]);
        $mal(['tipo' => 'seccion', 'seccion' => 'galeria'])->assertUnprocessable()->assertJsonValidationErrors('accion.destino.seccion');
        $mal(['tipo' => 'pagina', 'ruta' => 'matricula'])->assertUnprocessable()->assertJsonValidationErrors('accion.destino.ruta');
        $mal(['tipo' => 'pagina', 'ruta' => '/Matrícula'])->assertUnprocessable()->assertJsonValidationErrors('accion.destino.ruta');
        $mal(['tipo' => 'url', 'url' => 'http://forms.gle/abc'])->assertUnprocessable()->assertJsonValidationErrors('accion.destino.url');
        $mal(['tipo' => 'telefono'])->assertUnprocessable()->assertJsonValidationErrors('accion.destino.tipo');
        $this->putJson(self::BANNERS . "/{$id}", ['accion' => ['etiqueta' => str_repeat('b', 33), 'destino' => ['tipo' => 'pagina', 'ruta' => '/']]])
            ->assertUnprocessable()->assertJsonValidationErrors('accion.etiqueta');
    }

    public function test_las_reglas_de_un_banner(): void
    {
        $this->postJson(self::BANNERS, $this->banner(['imagen_id' => null]))->assertUnprocessable()->assertJsonValidationErrors('imagen_id');
        $this->postJson(self::BANNERS, $this->banner(['foco_x' => 101]))->assertUnprocessable()->assertJsonValidationErrors('foco_x');
        // El foco va entero o no va (null = centro).
        $this->postJson(self::BANNERS, $this->banner(['foco_y' => null]))->assertUnprocessable()->assertJsonValidationErrors('foco_y');
        $this->postJson(self::BANNERS, $this->banner(['foco_x' => null, 'foco_y' => null]))->assertCreated();
        $this->postJson(self::BANNERS, $this->banner(['categoria' => 'promocion']))->assertUnprocessable()->assertJsonValidationErrors('categoria');
        $this->postJson(self::BANNERS, $this->banner(['lado_accion' => 'centro']))->assertUnprocessable()->assertJsonValidationErrors('lado_accion');
        $this->postJson(self::BANNERS, $this->banner(['titulo' => str_repeat('t', 81)]))->assertUnprocessable()->assertJsonValidationErrors('titulo');
    }

    public function test_el_tope_de_ocho_banners(): void
    {
        $publicados = Banner::where('estado', 'publicado')->count();

        for ($i = $publicados; $i < 8; $i++) {
            $this->postJson(self::BANNERS, $this->banner(['titulo' => "Banner {$i}"]))->assertCreated();
        }

        $this->postJson(self::BANNERS, $this->banner(['titulo' => 'Uno de más']))->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->assertCount(8, $this->portal('/v1/portada/banners'));
    }

    // ───────── Cifras de Inicio ─────────

    public function test_las_cifras_de_inicio(): void
    {
        $this->postJson(self::CIFRAS, ['ambito' => 'portada', 'valor' => 1200, 'sufijo' => '+', 'etiqueta' => 'estudiantes', 'estado' => 'publicado'])->assertCreated();

        $this->assertSame(['valor' => 1200, 'sufijo' => '+'], array_intersect_key(collect($this->portal('/v1/portada/cifras'))->last(), ['valor' => 0, 'sufijo' => 0]));
        $this->assertSame(Cifra::de('portada')->where('estado', 'publicado')->count(), count($this->portal('/v1/portada/cifras')));
    }

    // ───────── Quiénes somos ─────────

    public function test_quienes_somos(): void
    {
        $foto = $this->getJson(self::FICHAS . 'quienes-somos')->json('data.contenido.foto.imagenId');

        $this->guardarFicha('quienes-somos', [
            'titulo'   => 'Educación adventista en Juliaca desde 1971.',
            'parrafos' => ['Primero.', 'Segundo.'],
            'foto'     => $foto,
            'simbolos' => [['nombre' => 'La antorcha', 'significado' => 'La luz del conocimiento.']],
        ])->assertOk();

        $qs = $this->portal('/v1/quienes-somos');
        $this->assertSame(['Primero.', 'Segundo.'], $qs['parrafos']);
        $this->assertSame([['nombre' => 'La antorcha', 'significado' => 'La luz del conocimiento.']], $qs['simbolos']);
        $this->assertNotNull($qs['foto']);

        // Sin símbolos, el bloque se oculta; sin foto también vale.
        $this->guardarFicha('quienes-somos', ['titulo' => 'Quiénes somos', 'parrafos' => ['Uno.'], 'foto' => null, 'simbolos' => []])->assertOk();
        $this->assertSame([], $this->portal('/v1/quienes-somos')['simbolos']);

        $this->guardarFicha('quienes-somos', ['titulo' => 'Q', 'parrafos' => [], 'simbolos' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('parrafos');
        $this->guardarFicha('quienes-somos', ['titulo' => 'Q', 'parrafos' => array_fill(0, 7, 'p'), 'simbolos' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('parrafos');
        $this->guardarFicha('quienes-somos', ['titulo' => 'Q', 'parrafos' => [str_repeat('p', 601)], 'simbolos' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('parrafos.0');
        $this->guardarFicha('quienes-somos', ['titulo' => 'Q', 'parrafos' => ['p'], 'simbolos' => array_fill(0, 9, ['nombre' => 'n', 'significado' => 's'])])
            ->assertUnprocessable()->assertJsonValidationErrors('simbolos');
    }

    // ───────── Niveles ─────────

    public function test_la_seccion_niveles_y_sus_niveles(): void
    {
        $this->guardarFicha('niveles', ['titulo' => 'Nuestros niveles', 'propuestaUrl' => null])->assertOk();
        $niveles = $this->portal('/v1/niveles');
        $this->assertSame('Nuestros niveles', $niveles['titulo']);
        $this->assertNull($niveles['propuestaUrl']);

        $this->guardarFicha('niveles', ['titulo' => 'N', 'propuestaUrl' => 'http://cata.edu.pe/'])
            ->assertUnprocessable()->assertJsonValidationErrors('propuestaUrl');

        // Hay 3; el cuarto entra y el quinto ya no se publica.
        $this->postJson(self::NIVELES, ['nombre' => 'Talleres', 'rango' => 'Todas las edades', 'estado' => 'publicado'])->assertCreated();
        $this->postJson(self::NIVELES, ['nombre' => 'Academia', 'estado' => 'publicado'])->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->assertSame('Talleres', collect($this->portal('/v1/niveles')['items'])->last()['nombre']);

        $this->postJson(self::NIVELES, ['nombre' => str_repeat('n', 25)])->assertUnprocessable()->assertJsonValidationErrors('nombre');

        $ids = Nivel::orderBy('orden')->pluck('id')->all();
        $this->postJson(self::NIVELES . '/order', ['ids' => array_reverse($ids)])->assertOk();
        $this->assertSame('Talleres', $this->portal('/v1/niveles')['items'][0]['nombre']);
    }

    // ───────── Noticias ─────────

    public function test_las_noticias_van_por_fecha_con_su_slug(): void
    {
        $id = $this->postJson(self::NOTICIAS, [
            'tipo'    => 'comunicado',
            'titulo'  => 'Suspensión de clases por el feriado',
            'resumen' => null,
            'fecha'   => '2026-10-10',
            'enlace'  => ['etiqueta' => 'Ver el comunicado', 'url' => 'https://cata.edu.pe/comunicado.pdf', 'externo' => true],
            'estado'  => 'publicado',
        ])->assertCreated()->assertJsonPath('data.slug', 'suspension-de-clases-por-el-feriado')->json('data.id');

        $primera = $this->portal('/v1/noticias')[0];
        $this->assertSame('suspension-de-clases-por-el-feriado', $primera['slug']);
        $this->assertSame(['etiqueta' => 'Ver el comunicado', 'url' => 'https://cata.edu.pe/comunicado.pdf', 'externo' => true], $primera['enlace']);

        // El slug no cambia al cambiar el título; uno repetido lleva -2.
        $this->putJson(self::NOTICIAS . "/{$id}", ['titulo' => 'Otro título'])->assertOk()->assertJsonPath('data.slug', 'suspension-de-clases-por-el-feriado');
        $this->postJson(self::NOTICIAS, ['tipo' => 'noticia', 'titulo' => 'Suspensión de clases por el feriado', 'fecha' => '2026-01-01'])
            ->assertCreated()->assertJsonPath('data.slug', 'suspension-de-clases-por-el-feriado-2');

        // Una más vieja queda abajo, en el portal y en el panel.
        $this->postJson(self::NOTICIAS, ['tipo' => 'noticia', 'titulo' => 'Antigua', 'fecha' => '2020-01-01', 'estado' => 'publicado'])->assertCreated();
        $this->assertSame('antigua', collect($this->portal('/v1/noticias?limite=24'))->last()['slug']);
        $this->assertSame('antigua', collect($this->getJson(self::NOTICIAS)->json('data'))->last()['slug']);

        // Sin orden a mano.
        $this->postJson(self::NOTICIAS . '/order', ['ids' => []])->assertNotFound();
    }

    public function test_las_reglas_de_una_noticia(): void
    {
        $base = ['tipo' => 'noticia', 'titulo' => 'Título', 'fecha' => '2026-10-10'];

        $this->postJson(self::NOTICIAS, ['tipo' => 'aviso'] + $base)->assertUnprocessable()->assertJsonValidationErrors('tipo');
        $this->postJson(self::NOTICIAS, ['fecha' => '10/10/2026'] + $base)->assertUnprocessable()->assertJsonValidationErrors('fecha');
        $this->postJson(self::NOTICIAS, ['resumen' => str_repeat('r', 281)] + $base)->assertUnprocessable()->assertJsonValidationErrors('resumen');
        $this->postJson(self::NOTICIAS, ['enlace' => ['etiqueta' => 'Ver', 'url' => 'http://x.pe']] + $base)->assertUnprocessable()->assertJsonValidationErrors('enlace.url');
        $this->postJson(self::NOTICIAS, ['enlace' => ['url' => 'https://x.pe']] + $base)->assertUnprocessable()->assertJsonValidationErrors('enlace.etiqueta');
    }

    public function test_se_valida_la_pagina_del_portal_donde_cae_la_noticia(): void
    {
        // 30 noticias: la más vieja cae en la segunda página de 24.
        for ($i = 1; $i <= 30; $i++) {
            Noticia::create([
                'clave' => "prueba-{$i}", 'slug' => "prueba-{$i}", 'tipo' => 'noticia', 'titulo' => "Noticia {$i}",
                'fecha' => now()->subDays($i)->toDateString(), 'orden' => $i, 'estado' => 'publicado',
            ]);
        }

        $vieja = Noticia::where('clave', 'prueba-30')->first();
        $this->assertContains('/v1/noticias?limite=24&pagina=2', $this->rutasDe($vieja));

        // Una noticia de esa página que el portal no podría mostrar (un slug
        // fuera de lo que admite el contrato) se frena al tocarla.
        $vieja->forceFill(['slug' => 'Con Mayúsculas'])->saveQuietly();
        $this->putJson(self::NOTICIAS . "/{$vieja->id}", ['titulo' => 'Corregida'])
            ->assertUnprocessable()->assertJsonValidationErrors('contrato');
    }

    private function rutasDe(Noticia $noticia): array
    {
        $controlador = app(\App\Http\Controllers\Portal\Panel\NoticiasController::class);
        $metodo = new \ReflectionMethod($controlador, 'rutasAfectadas');

        return $metodo->invoke($controlador, $noticia);
    }

    public function test_rrhh_no_edita_inicio(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        foreach ([self::BANNERS, self::NIVELES, self::NOTICIAS, self::FICHAS . 'quienes-somos', self::FICHAS . 'niveles'] as $ruta) {
            $this->getJson($ruta)->assertForbidden();
        }
    }
}
