<?php

namespace Tests\Feature;

use App\Models\Portal\Imagen;
use App\Models\Portal\Proyecto;
use App\Models\Portal\ProyectoCategoria;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Panel del portal, etapa 7: Proyectos. La cabecera, las categorías del
 * filtro, cada proyecto con sus secciones y su galería.
 */
class PortalPanelProyectosTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const CABECERA = '/api/web-portal/sections/proyectos';
    private const CATEGORIAS = '/api/web-portal/project-categories';
    private const PROYECTOS = '/api/web-portal/projects';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(ImportadorDeEjemplos::carpeta(), 'tipico'))->importar();
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    private function portal(string $ruta): array
    {
        $respuesta = $this->getJson("/api/portal{$ruta}")->assertOk();
        $this->assertCumpleElContrato(preg_replace('#^/v1/proyectos/[a-z0-9-]+$#', '/v1/proyectos/{slug}', $ruta), $respuesta->getContent());

        return $respuesta->json('data');
    }

    private function categoria(string $clave): string
    {
        return ProyectoCategoria::where('clave', $clave)->value('id');
    }

    private function proyecto(array $cambios = []): array
    {
        return array_replace([
            'titulo'       => 'Huerto escolar',
            'lema'         => 'Sembramos, cuidamos y compartimos.',
            'resumen'      => 'Los estudiantes cultivan hortalizas en la sede Oasis y aprenden sobre alimentación saludable.',
            'categoria_id' => $this->categoria('alimentacion'),
            'niveles'      => ['Primaria'],
            'etapa'        => 'en-curso',
            'fecha'        => '2026-03-16',
            'imagen_id'    => null,
            'descripcion'  => ['Un huerto en cada sede.', 'Con el apoyo de las familias.'],
            'secciones'    => [
                ['titulo' => 'Objetivos', 'tipo' => 'lista', 'items' => [['titulo' => null, 'texto' => 'Cultivar hortalizas.']]],
                ['titulo' => 'Etapas', 'tipo' => 'numerada', 'items' => [
                    ['titulo' => 'Siembra', 'texto' => 'Marzo y abril.'],
                    ['titulo' => 'Cosecha', 'texto' => 'Junio.'],
                ]],
            ],
            'estado'       => 'publicado',
        ], $cambios);
    }

    // ───────── Cabecera ─────────

    public function test_la_cabecera(): void
    {
        $version = $this->getJson(self::CABECERA)->json('data.version');
        $this->putJson(self::CABECERA, ['titulo' => 'Nuestros proyectos', 'bajada' => null, 'version' => $version])->assertOk();

        $this->assertSame('Nuestros proyectos', $this->portal('/v1/proyectos')['titulo']);

        $this->putJson(self::CABECERA, ['titulo' => 'P', 'bajada' => str_repeat('b', 241), 'version' => $this->getJson(self::CABECERA)->json('data.version')])
            ->assertUnprocessable()->assertJsonValidationErrors('bajada');
    }

    // ───────── Categorías ─────────

    public function test_las_categorias_del_filtro(): void
    {
        $this->postJson(self::CATEGORIAS, ['nombre' => 'Arte y música'])->assertCreated()->assertJsonPath('data.clave', 'arte-y-musica');
        $this->assertSame(['alimentacion', 'tecnologia', 'arte-y-musica'], array_column($this->portal('/v1/proyectos')['categorias'], 'id'));

        // Ordenarlas cambia el orden del filtro.
        $ids = ProyectoCategoria::orderBy('orden')->pluck('id')->all();
        $this->postJson(self::CATEGORIAS . '/order', ['ids' => array_reverse($ids)])->assertOk();
        $this->assertSame('arte-y-musica', $this->portal('/v1/proyectos')['categorias'][0]['id']);

        // Sin borrador.
        $this->patchJson(self::CATEGORIAS . "/{$ids[0]}/status", ['estado' => 'borrador'])->assertNotFound();

        $this->postJson(self::CATEGORIAS, ['nombre' => str_repeat('c', 41)])->assertUnprocessable()->assertJsonValidationErrors('nombre');
    }

    public function test_renombrar_una_categoria_llega_al_detalle_de_sus_proyectos(): void
    {
        $this->putJson(self::CATEGORIAS . '/' . $this->categoria('tecnologia'), ['nombre' => 'Ciencia y tecnología'])
            ->assertOk()->assertJsonPath('data.clave', 'tecnologia');

        $this->assertSame('Ciencia y tecnología', $this->portal('/v1/proyectos/robotica')['categoriaNombre']);
    }

    public function test_una_categoria_con_proyectos_no_se_elimina_y_el_tope_es_ocho(): void
    {
        $this->deleteJson(self::CATEGORIAS . '/' . $this->categoria('tecnologia'))
            ->assertUnprocessable()->assertJsonValidationErrors('categoria');
        $this->assertNotNull(ProyectoCategoria::where('clave', 'tecnologia')->first());

        $vacia = $this->postJson(self::CATEGORIAS, ['nombre' => 'Deporte'])->json('data.id');
        $this->deleteJson(self::CATEGORIAS . "/{$vacia}")->assertOk();

        for ($i = 3; $i <= 8; $i++) {
            $this->postJson(self::CATEGORIAS, ['nombre' => "Categoría {$i}"])->assertCreated();
        }
        $this->postJson(self::CATEGORIAS, ['nombre' => 'Novena'])->assertUnprocessable()->assertJsonValidationErrors('nombre');
        $this->portal('/v1/proyectos');
    }

    // ───────── Proyectos ─────────

    public function test_un_proyecto_con_sus_secciones_llega_a_la_lista_y_al_detalle(): void
    {
        $this->postJson(self::PROYECTOS, $this->proyecto())->assertCreated()->assertJsonPath('data.slug', 'huerto-escolar');

        $resumen = collect($this->portal('/v1/proyectos')['proyectos'])->firstWhere('slug', 'huerto-escolar');
        $this->assertSame(['alimentacion', 'en-curso', ['Primaria']], [$resumen['categoria'], $resumen['estado'], $resumen['niveles']]);

        $detalle = $this->portal('/v1/proyectos/huerto-escolar');
        $this->assertSame('Alimentación y salud', $detalle['categoriaNombre']);
        $this->assertSame(['Un huerto en cada sede.', 'Con el apoyo de las familias.'], $detalle['descripcion']);
        $this->assertSame(['titulo' => null, 'texto' => 'Cultivar hortalizas.'], $detalle['secciones'][0]['items'][0]);
        $this->assertSame('numerada', $detalle['secciones'][1]['tipo']);
        $this->assertSame([], $detalle['galeria']);
    }

    public function test_el_slug_se_corrige_en_borrador_y_no_cambia_publicado(): void
    {
        $id = $this->postJson(self::PROYECTOS, $this->proyecto(['estado' => 'borrador']))->json('data.id');

        $this->putJson(self::PROYECTOS . "/{$id}", ['slug' => 'huerto'])->assertOk()->assertJsonPath('data.slug', 'huerto');
        $this->putJson(self::PROYECTOS . "/{$id}", ['slug' => 'Huerto Escolar'])->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->putJson(self::PROYECTOS . "/{$id}", ['slug' => 'robotica'])->assertUnprocessable()->assertJsonValidationErrors('slug');

        // En borrador no hay detalle; publicado, sí.
        $this->getJson('/api/portal/v1/proyectos/huerto')->assertNotFound();
        $this->patchJson(self::PROYECTOS . "/{$id}/status", ['estado' => 'publicado'])->assertOk();
        $this->portal('/v1/proyectos/huerto');

        $this->putJson(self::PROYECTOS . "/{$id}", ['slug' => 'huerto-2026'])->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->putJson(self::PROYECTOS . "/{$id}", ['titulo' => 'Huerto escolar 2026'])->assertOk()->assertJsonPath('data.slug', 'huerto');
    }

    public function test_las_reglas_de_un_proyecto(): void
    {
        $mal = fn (array $cambios) => $this->postJson(self::PROYECTOS, $this->proyecto($cambios));

        $mal(['resumen' => str_repeat('r', 241)])->assertUnprocessable()->assertJsonValidationErrors('resumen');
        $mal(['categoria_id' => '01a00000-0000-7000-8000-000000000000'])->assertUnprocessable()->assertJsonValidationErrors('categoria_id');
        $mal(['etapa' => 'pausado'])->assertUnprocessable()->assertJsonValidationErrors('etapa');
        $mal(['niveles' => array_fill(0, 7, 'n')])->assertUnprocessable()->assertJsonValidationErrors('niveles');
        $mal(['descripcion' => []])->assertUnprocessable()->assertJsonValidationErrors('descripcion');
        $mal(['descripcion' => array_fill(0, 7, 'p')])->assertUnprocessable()->assertJsonValidationErrors('descripcion');
        $mal(['secciones' => array_fill(0, 9, ['titulo' => 'S', 'tipo' => 'lista', 'items' => [['titulo' => null, 'texto' => 't']]])])
            ->assertUnprocessable()->assertJsonValidationErrors('secciones');
        $mal(['secciones' => [['titulo' => 'S', 'tipo' => 'tabla', 'items' => [['titulo' => null, 'texto' => 't']]]]])
            ->assertUnprocessable()->assertJsonValidationErrors('secciones.0.tipo');
        $mal(['secciones' => [['titulo' => 'S', 'tipo' => 'lista', 'items' => []]]])
            ->assertUnprocessable()->assertJsonValidationErrors('secciones.0.items');
        $mal(['secciones' => [['titulo' => 'S', 'tipo' => 'lista', 'items' => array_fill(0, 13, ['titulo' => null, 'texto' => 't'])]]])
            ->assertUnprocessable()->assertJsonValidationErrors('secciones.0.items');

        $sinTexto = $mal(['secciones' => [['titulo' => 'S', 'tipo' => 'lista', 'items' => [['titulo' => null, 'texto' => '']]]]])
            ->assertUnprocessable()->assertJsonValidationErrors('secciones.0.items.0.texto');
        $this->assertStringContainsString('texto del elemento', $sinTexto->json('errors')['secciones.0.items.0.texto'][0]);
    }

    public function test_el_tope_de_veinticuatro_proyectos(): void
    {
        for ($i = Proyecto::where('estado', 'publicado')->count(); $i < 24; $i++) {
            $this->postJson(self::PROYECTOS, $this->proyecto(['titulo' => "Proyecto {$i}"]))->assertCreated();
        }

        $this->postJson(self::PROYECTOS, $this->proyecto(['titulo' => 'Uno de más']))->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->assertCount(24, $this->portal('/v1/proyectos')['proyectos']);
    }

    // ───────── Galería ─────────

    public function test_la_galeria_se_guarda_entera_y_en_orden(): void
    {
        $robotica = Proyecto::where('slug', 'robotica')->first();
        $actuales = $robotica->galeria()->pluck('imagen_id')->all();
        $otra = Imagen::whereNotIn('id', $actuales)->value('id');

        // Se invierte el orden y se suma una.
        $nuevas = [...array_reverse($actuales), $otra];
        $this->putJson(self::PROYECTOS . "/{$robotica->id}/gallery", ['imagenes' => $nuevas])
            ->assertOk()->assertJsonCount(3, 'data.galeria');

        $esperadas = Imagen::whereIn('id', $nuevas)->get()->keyBy('id');
        $urls = array_map(fn (string $id) => $esperadas[$id]->aPanel()['url'], $nuevas);
        $this->assertSame(array_map(fn ($u) => basename($u), $urls), array_map(fn ($f) => basename($f['url']), $this->portal('/v1/proyectos/robotica')['galeria']));

        // Vaciarla también vale.
        $this->putJson(self::PROYECTOS . "/{$robotica->id}/gallery", ['imagenes' => []])->assertOk()->assertJsonCount(0, 'data.galeria');
        $this->assertSame([], $this->portal('/v1/proyectos/robotica')['galeria']);

        $this->putJson(self::PROYECTOS . "/{$robotica->id}/gallery", ['imagenes' => array_fill(0, 13, $otra)])
            ->assertUnprocessable()->assertJsonValidationErrors('imagenes');
        $this->putJson(self::PROYECTOS . "/{$robotica->id}/gallery", ['imagenes' => [$otra, $otra]])
            ->assertUnprocessable()->assertJsonValidationErrors('imagenes.1');
    }

    public function test_eliminar_un_proyecto_se_lleva_su_galeria(): void
    {
        $robotica = Proyecto::where('slug', 'robotica')->first();
        $this->assertGreaterThan(0, $robotica->galeria()->count());

        $this->deleteJson(self::PROYECTOS . "/{$robotica->id}")->assertOk();
        $this->assertSame(0, \App\Models\Portal\ProyectoImagen::where('proyecto_id', $robotica->id)->count());
        $this->getJson('/api/portal/v1/proyectos/robotica')->assertNotFound();
    }

    public function test_rrhh_no_edita_proyectos(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        foreach ([self::CATEGORIAS, self::PROYECTOS, self::CABECERA] as $ruta) {
            $this->getJson($ruta)->assertForbidden();
        }
    }
}
