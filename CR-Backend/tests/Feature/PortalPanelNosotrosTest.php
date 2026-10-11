<?php

namespace Tests\Feature;

use App\Models\Portal\Docente;
use App\Models\Portal\DocenteNivel;
use App\Models\Portal\Imagen;
use App\Models\Portal\Pagina;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Panel del portal, etapa 9, parte A: Nosotros sin el editor de bloques. El
 * índice, las subpáginas (sus textos) y Personal Docente.
 */
class PortalPanelNosotrosTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const FICHA = '/api/web-portal/sections/nosotros';
    private const PAGINAS = '/api/web-portal/pages';
    private const NIVELES = '/api/web-portal/teacher-levels';
    private const DOCENTES = '/api/web-portal/teachers';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), 'tipico'))->importar();
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    private function portal(string $ruta): mixed
    {
        $respuesta = $this->getJson("/api/portal{$ruta}")->assertOk();
        $this->assertCumpleElContrato(preg_replace('#^/v1/nosotros/paginas/[a-z0-9-]+$#', '/v1/nosotros/paginas/{slug}', $ruta), $respuesta->getContent());

        return $respuesta->json('data');
    }

    private function nivel(string $clave): DocenteNivel
    {
        return DocenteNivel::where('clave', $clave)->firstOrFail();
    }

    // ───────── Índice y subpáginas ─────────

    public function test_el_indice(): void
    {
        $version = $this->getJson(self::FICHA)->json('data.version');
        $this->putJson(self::FICHA, ['titulo' => 'Quiénes somos', 'bajada' => null, 'version' => $version])->assertOk();

        $indice = $this->portal('/v1/nosotros');
        $this->assertSame('Quiénes somos', $indice['titulo']);
        $this->assertNull($indice['bajada']);

        $version = $this->getJson(self::FICHA)->json('data.version');
        $this->putJson(self::FICHA, ['titulo' => str_repeat('t', 61), 'version' => $version])->assertJsonValidationErrors('titulo');
    }

    public function test_los_textos_de_una_pagina_no_tocan_sus_bloques(): void
    {
        $pagina = Pagina::where('slug', 'mision-y-vision')->firstOrFail();
        $bloques = $pagina->bloques;

        $this->putJson(self::PAGINAS . "/{$pagina->id}", [
            'titulo'  => 'Misión, visión y valores',
            'bajada'  => 'Lo que nos guía.',
            'resumen' => 'Hacia dónde vamos y lo que nos guía.',
            // Los bloques no se editan aquí: se ignoran.
            'bloques' => [],
        ])->assertOk()
            ->assertJsonPath('data.grupo', 'Institución')
            ->assertJsonPath('data.nombre', 'Misión y Visión')
            ->assertJsonPath('data.bloques', count($bloques));

        $this->assertSame($bloques, $pagina->refresh()->bloques);
        $this->assertSame('Misión, visión y valores', $this->portal('/v1/nosotros/paginas/mision-y-vision')['titulo']);
        $this->assertContains(['slug' => 'mision-y-vision', 'resumen' => 'Hacia dónde vamos y lo que nos guía.'], $this->portal('/v1/nosotros')['paginas']);

        // Sin frase, la tarjeta del índice no lleva texto.
        $this->putJson(self::PAGINAS . "/{$pagina->id}", ['resumen' => null])->assertOk();
        $this->assertNotContains('mision-y-vision', array_column($this->portal('/v1/nosotros')['paginas'], 'slug'));

        $this->putJson(self::PAGINAS . "/{$pagina->id}", ['resumen' => str_repeat('r', 141)])->assertJsonValidationErrors('resumen');
        $this->putJson(self::PAGINAS . "/{$pagina->id}", ['bajada' => str_repeat('b', 161)])->assertJsonValidationErrors('bajada');
        $this->putJson(self::PAGINAS . "/{$pagina->id}", ['slug' => 'emblema'])
            ->assertJsonPath('errors.slug.0', 'El campo dirección no se puede cambiar.');
    }

    public function test_solo_se_crean_las_paginas_que_el_portal_sabe_abrir(): void
    {
        $this->postJson(self::PAGINAS, ['slug' => 'nuestra-historia', 'titulo' => 'Historia'])->assertJsonValidationErrors('slug');
        $this->postJson(self::PAGINAS, ['slug' => 'emblema', 'titulo' => 'Emblema'])->assertJsonValidationErrors('slug');

        $emblema = Pagina::where('slug', 'emblema')->firstOrFail();
        $this->deleteJson(self::PAGINAS . "/{$emblema->id}")->assertOk();
        $this->getJson('/api/portal/v1/nosotros/paginas/emblema')->assertNotFound();

        $this->postJson(self::PAGINAS, ['slug' => 'emblema', 'titulo' => 'El emblema', 'estado' => 'publicado'])
            ->assertCreated()
            ->assertJsonPath('data.bloques', 0)
            ->assertJsonPath('data.grupo', 'Institución')
            // Al final: después de la 16.ª del ejemplo, aunque ahora haya 15.
            ->assertJsonPath('data.orden', 17);

        // Sin bloques: «El contenido de esta página se publicará pronto».
        $this->assertSame([], $this->portal('/v1/nosotros/paginas/emblema')['bloques']);
    }

    public function test_en_borrador_la_pagina_no_se_ve(): void
    {
        $himno = Pagina::where('slug', 'himno')->firstOrFail();

        $this->patchJson(self::PAGINAS . "/{$himno->id}/status", ['estado' => 'borrador'])->assertOk();
        $this->getJson('/api/portal/v1/nosotros/paginas/himno')->assertNotFound();
        $this->assertNotContains('himno', array_column($this->portal('/v1/nosotros')['paginas'], 'slug'));

        $this->patchJson(self::PAGINAS . "/{$himno->id}/status", ['estado' => 'publicado'])->assertOk();
        $this->portal('/v1/nosotros/paginas/himno');
    }

    // ───────── Personal Docente ─────────

    public function test_los_niveles_de_personal_docente(): void
    {
        $id = $this->postJson(self::NIVELES, ['nombre' => 'Talleres', 'estado' => 'publicado'])
            ->assertCreated()->assertJsonPath('data.clave', 'talleres')->assertJsonPath('data.docentes', 0)->json('data.id');

        // Se publica aunque no tenga docentes.
        $this->assertContains(['id' => 'talleres', 'nombre' => 'Talleres'], $this->portal('/v1/docentes')['niveles']);

        $this->putJson(self::NIVELES . "/{$id}", ['nombre' => 'Talleres y academias'])->assertOk()->assertJsonPath('data.clave', 'talleres');
        $this->putJson(self::NIVELES . "/{$id}", ['nombre' => str_repeat('n', 41)])->assertJsonValidationErrors('nombre');

        $publicados = count($this->portal('/v1/docentes')['niveles']);
        foreach (range($publicados + 1, 6) as $i) {
            $this->postJson(self::NIVELES, ['nombre' => "Nivel {$i}", 'estado' => 'publicado'])->assertCreated();
        }
        $this->postJson(self::NIVELES, ['nombre' => 'Uno más', 'estado' => 'publicado'])->assertJsonValidationErrors('estado');
    }

    public function test_un_nivel_con_docentes_no_se_elimina(): void
    {
        $primaria = $this->nivel('primaria');
        $cuantos = $primaria->docentes()->count();

        $this->deleteJson(self::NIVELES . "/{$primaria->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.nivel.0', "«Primaria» tiene {$cuantos} docentes. Pásalos a otro nivel antes de eliminarlo.");

        // En borrador, sus docentes no salen (el portal descartaría la respuesta).
        $this->patchJson(self::NIVELES . "/{$primaria->id}/status", ['estado' => 'borrador'])->assertOk();
        $docentes = $this->portal('/v1/docentes')['docentes'];
        $this->assertNotContains('primaria', array_column($docentes, 'nivel'));
    }

    public function test_un_docente_con_su_foto_llega_al_portal(): void
    {
        $inicial = $this->nivel('inicial');
        $foto = Imagen::value('id');

        $this->postJson(self::DOCENTES, [
            'nombre'    => 'Rosa Quispe Mamani',
            'cargo'     => 'Tutora de 4 años',
            'nivel_id'  => $inicial->id,
            'imagen_id' => $foto,
            'estado'    => 'publicado',
        ])->assertCreated()
            ->assertJsonPath('data.clave', 'rosa-quispe-mamani')
            ->assertJsonPath('data.imagen.id', $foto)
            // Al final de los suyos.
            ->assertJsonPath('data.orden', (int) Docente::where('nivel_id', $inicial->id)->max('orden'));

        $docente = collect($this->portal('/v1/docentes')['docentes'])->firstWhere('id', 'rosa-quispe-mamani');
        $this->assertSame('inicial', $docente['nivel']);
        $this->assertSame('Tutora de 4 años', $docente['cargo']);
        $this->assertNotNull($docente['foto']);

        $this->postJson(self::DOCENTES, ['nombre' => 'X', 'cargo' => str_repeat('c', 81), 'nivel_id' => $inicial->id])->assertJsonValidationErrors('cargo');
        $this->postJson(self::DOCENTES, ['nombre' => 'X', 'cargo' => 'Y'])->assertJsonValidationErrors('nivel_id');
    }

    public function test_los_docentes_se_ordenan_dentro_de_su_nivel(): void
    {
        $primaria = $this->nivel('primaria');
        $ids = $primaria->docentes()->orderBy('orden')->pluck('id')->reverse()->values()->all();

        $this->postJson(self::DOCENTES . '/order', ['ids' => $ids])->assertJsonValidationErrors('nivel');
        $this->postJson(self::DOCENTES . "/order?nivel={$primaria->id}", ['ids' => $ids])->assertOk();
        $this->assertCount(count($ids), $this->getJson(self::DOCENTES . "?nivel={$primaria->id}")->json('data'));

        $esperado = Docente::whereIn('id', $ids)->get()->sortBy(fn ($d) => array_search($d->id, $ids))->pluck('clave')->values()->all();
        $enElPortal = collect($this->portal('/v1/docentes')['docentes'])->where('nivel', 'primaria')->pluck('id')->values()->all();
        $this->assertSame($esperado, $enElPortal);

        // Cambiar de nivel lo pone al final del nuevo.
        $secundaria = $this->nivel('secundaria');
        $this->putJson(self::DOCENTES . "/{$ids[0]}", ['nivel_id' => $secundaria->id])
            ->assertOk()->assertJsonPath('data.orden', (int) Docente::where('nivel_id', $secundaria->id)->max('orden'));
        $this->assertSame(Docente::find($ids[0])->clave, collect($this->portal('/v1/docentes')['docentes'])->where('nivel', 'secundaria')->last()['id']);
    }

    public function test_rrhh_no_edita_nosotros(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        foreach ([self::FICHA, self::PAGINAS, self::NIVELES, self::DOCENTES] as $ruta) {
            $this->getJson($ruta)->assertForbidden();
        }
    }
}
