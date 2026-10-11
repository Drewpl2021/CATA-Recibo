<?php

namespace Tests\Feature;

use App\Models\Portal\Ciclo;
use App\Models\Portal\Imagen;
use App\Models\Portal\Universidad;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Panel del portal, etapa 8: Propuesta Educativa. La cabecera, la
 * introducción y los pilares, los niveles, la academia con sus
 * universidades y ciclos, y las plataformas digitales.
 */
class PortalPanelPropuestaTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const SECCIONES = '/api/web-portal/sections/';
    private const PILARES = '/api/web-portal/pillars';
    private const NIVELES = '/api/web-portal/proposal-levels';
    private const UNIVERSIDADES = '/api/web-portal/universities';
    private const CICLOS = '/api/web-portal/cycles';
    private const PLATAFORMAS = '/api/web-portal/platforms';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), 'tipico'))->importar();
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    private function portal(string $ruta): mixed
    {
        $respuesta = $this->getJson("/api/portal/v1/propuesta-educativa/{$ruta}")->assertOk();
        $this->assertCumpleElContrato("/v1/propuesta-educativa/{$ruta}", $respuesta->getContent());

        return $respuesta->json('data');
    }

    private function guardarFicha(string $clave, array $contenido): \Illuminate\Testing\TestResponse
    {
        $version = $this->getJson(self::SECCIONES . $clave)->json('data.version');

        return $this->putJson(self::SECCIONES . $clave, $contenido + ['version' => $version]);
    }

    private function universidad(string $clave): Universidad
    {
        return Universidad::where('clave', $clave)->firstOrFail();
    }

    // ───────── Cabecera ─────────

    public function test_la_cabecera_con_sus_figuras_y_botones(): void
    {
        [$recorte, $foto] = Imagen::orderBy('id')->limit(2)->pluck('id')->all();

        $this->guardarFicha('propuesta-cabecera', [
            'titulo'           => 'Aprender con propósito',
            'bajada'           => null,
            'figuras'          => [['tipo' => 'recorte', 'imagen' => $recorte], ['tipo' => 'foto', 'imagen' => $foto]],
            'accionPrincipal'  => ['etiqueta' => 'Proceso de matrícula', 'destino' => ['tipo' => 'pagina', 'ruta' => '/matricula']],
            'accionSecundaria' => null,
        ])->assertOk()->assertJsonPath('data.contenido.figuras.0.imagen.imagenId', $recorte);

        $cabecera = $this->portal('cabecera');
        $this->assertSame('Aprender con propósito', $cabecera['titulo']);
        $this->assertSame(['recorte', 'foto'], array_column($cabecera['figuras'], 'tipo'));
        $this->assertSame('/matricula', $cabecera['accionPrincipal']['destino']['ruta']);
        $this->assertNull($cabecera['accionSecundaria']);

        // Sin figuras: cabecera solo con texto.
        $this->guardarFicha('propuesta-cabecera', [
            'titulo'          => 'Aprender con propósito',
            'figuras'         => [],
            'accionPrincipal' => ['etiqueta' => 'Contáctanos', 'destino' => ['tipo' => 'seccion', 'seccion' => 'contacto']],
        ])->assertOk();
        $this->assertSame([], $this->portal('cabecera')['figuras']);
    }

    public function test_las_reglas_de_la_cabecera(): void
    {
        $imagenes = Imagen::orderBy('id')->limit(4)->pluck('id')->all();
        $base = [
            'titulo'          => 'Título',
            'figuras'         => [],
            'accionPrincipal' => ['etiqueta' => 'Ir', 'destino' => ['tipo' => 'pagina', 'ruta' => '/matricula']],
        ];

        $this->guardarFicha('propuesta-cabecera', ['titulo' => str_repeat('t', 71)] + $base)->assertJsonValidationErrors('titulo');
        $this->guardarFicha('propuesta-cabecera', ['figuras' => array_map(fn ($id) => ['tipo' => 'foto', 'imagen' => $id], $imagenes)] + $base)
            ->assertJsonValidationErrors('figuras');
        $this->guardarFicha('propuesta-cabecera', ['figuras' => [['tipo' => 'video', 'imagen' => $imagenes[0]]]] + $base)
            ->assertJsonValidationErrors('figuras.0.tipo');
        $this->guardarFicha('propuesta-cabecera', ['figuras' => [['tipo' => 'foto', 'imagen' => $imagenes[0]], ['tipo' => 'recorte', 'imagen' => $imagenes[0]]]] + $base)
            ->assertJsonValidationErrors('figuras.1.imagen');
        $this->guardarFicha('propuesta-cabecera', ['accionPrincipal' => null] + $base)->assertJsonValidationErrors('accionPrincipal');

        // El error dice de cuál botón habla.
        $this->guardarFicha('propuesta-cabecera', $base + [
            'accionSecundaria' => ['etiqueta' => 'Ver', 'destino' => ['tipo' => 'url', 'url' => 'http://cata.edu.pe']],
        ])->assertJsonValidationErrors('accionSecundaria.destino.url');
        $this->assertStringContainsString('botón secundario', $this->guardarFicha('propuesta-cabecera', $base + [
            'accionSecundaria' => ['etiqueta' => 'Ver', 'destino' => ['tipo' => 'url', 'url' => 'http://cata.edu.pe']],
        ])->json('errors')['accionSecundaria.destino.url'][0]);
    }

    // ───────── Introducción y pilares ─────────

    public function test_la_introduccion_se_edita_y_se_puede_quitar(): void
    {
        $this->guardarFicha('propuesta-pilares', ['introduccion' => ['titulo' => 'Educación adventista', 'parrafos' => ['Uno.', 'Dos.']]])->assertOk();
        $this->assertSame(['Uno.', 'Dos.'], $this->portal('pilares')['introduccion']['parrafos']);

        $this->guardarFicha('propuesta-pilares', ['introduccion' => null])->assertOk();
        $this->assertNull($this->portal('pilares')['introduccion']);

        $this->guardarFicha('propuesta-pilares', ['introduccion' => ['titulo' => 'T', 'parrafos' => array_fill(0, 7, 'p')]])
            ->assertJsonValidationErrors('introduccion.parrafos');
        $this->guardarFicha('propuesta-pilares', ['introduccion' => ['titulo' => 'T', 'parrafos' => []]])
            ->assertJsonValidationErrors('introduccion.parrafos');
    }

    public function test_un_pilar_con_sus_aspectos_llega_al_portal(): void
    {
        $id = $this->postJson(self::PILARES, [
            'titulo' => 'Familia y escuela',
            'texto'  => 'Las familias acompañan cada etapa del aprendizaje.',
            'puntos' => ['Padres', 'Docentes'],
            'estado' => 'publicado',
        ])->assertCreated()->assertJsonPath('data.clave', 'familia-y-escuela')->json('data.id');

        $pilar = collect($this->portal('pilares')['pilares'])->firstWhere('id', 'familia-y-escuela');
        $this->assertSame(['Padres', 'Docentes'], $pilar['puntos']);

        // Sin aspectos también vale.
        $this->putJson(self::PILARES . "/{$id}", ['puntos' => []])->assertOk();
        $this->assertSame([], collect($this->portal('pilares')['pilares'])->firstWhere('id', 'familia-y-escuela')['puntos']);

        $this->putJson(self::PILARES . "/{$id}", ['puntos' => array_map(fn ($i) => "A{$i}", range(1, 9))])->assertJsonValidationErrors('puntos');
        $this->putJson(self::PILARES . "/{$id}", ['puntos' => ['Mental', 'Mental']])->assertJsonValidationErrors('puntos.1');
        $this->putJson(self::PILARES . "/{$id}", ['puntos' => [str_repeat('a', 31)]])->assertJsonValidationErrors('puntos.0');
        $this->putJson(self::PILARES . "/{$id}", ['texto' => str_repeat('a', 401)])->assertJsonValidationErrors('texto');
    }

    public function test_el_tope_de_ocho_pilares(): void
    {
        $publicados = count($this->portal('pilares')['pilares']);
        foreach (range($publicados + 1, 8) as $i) {
            $this->postJson(self::PILARES, ['titulo' => "Pilar {$i}", 'texto' => 'Texto.', 'estado' => 'publicado'])->assertCreated();
        }

        $this->postJson(self::PILARES, ['titulo' => 'Uno más', 'texto' => 'Texto.', 'estado' => 'publicado'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->assertCount(8, $this->portal('pilares')['pilares']);
    }

    // ───────── Niveles ─────────

    public function test_un_nivel_con_rasgos_y_talleres(): void
    {
        $foto = Imagen::value('id');

        $this->postJson(self::NIVELES, [
            'nombre'      => 'Talleres',
            'rango'       => 'De 6 a 16 años',
            'descripcion' => ['Música, arte y deporte.'],
            'rasgos'      => ['Creatividad', 'Trabajo en equipo'],
            'talleres'    => [['titulo' => 'Banda', 'texto' => ''], ['titulo' => 'Ajedrez', 'texto' => 'Los viernes.']],
            'imagen_id'   => $foto,
            'estado'      => 'publicado',
        ])->assertCreated()->assertJsonPath('data.talleres.0.texto', null)->assertJsonPath('data.imagen.id', $foto);

        $nivel = collect($this->portal('niveles'))->firstWhere('id', 'talleres');
        $this->assertSame(['Creatividad', 'Trabajo en equipo'], $nivel['rasgos']);
        $this->assertSame([['titulo' => 'Banda', 'texto' => null], ['titulo' => 'Ajedrez', 'texto' => 'Los viernes.']], $nivel['talleres']);
        $this->assertNotNull($nivel['foto']);

        $this->postJson(self::NIVELES, ['nombre' => 'X', 'talleres' => array_fill(0, 13, ['titulo' => 'T', 'texto' => null])])
            ->assertJsonValidationErrors('talleres');
        $this->postJson(self::NIVELES, ['nombre' => 'X', 'rasgos' => [str_repeat('r', 51)]])->assertJsonValidationErrors('rasgos.0');
        $this->postJson(self::NIVELES, ['nombre' => str_repeat('n', 41)])->assertJsonValidationErrors('nombre');
    }

    public function test_el_tope_de_seis_niveles(): void
    {
        $publicados = count($this->portal('niveles'));
        foreach (range($publicados + 1, 6) as $i) {
            $this->postJson(self::NIVELES, ['nombre' => "Nivel {$i}", 'estado' => 'publicado'])->assertCreated();
        }

        $this->postJson(self::NIVELES, ['nombre' => 'Uno más', 'estado' => 'publicado'])->assertJsonValidationErrors('estado');
        $this->assertCount(6, $this->portal('niveles'));
    }

    // ───────── Academia ─────────

    public function test_los_textos_de_la_academia(): void
    {
        $this->guardarFicha('propuesta-academia', ['titulo' => 'Academia CATA', 'descripcion' => null, 'aclaracion' => 'Precios 2027 por confirmar'])->assertOk();

        $academia = $this->portal('academia');
        $this->assertSame('Academia CATA', $academia['titulo']);
        $this->assertNull($academia['descripcion']);
        $this->assertSame('Precios 2027 por confirmar', $academia['aclaracion']);

        $this->guardarFicha('propuesta-academia', ['titulo' => 'A', 'aclaracion' => str_repeat('a', 81)])->assertJsonValidationErrors('aclaracion');
    }

    public function test_una_universidad_se_publica_solo_con_ciclos(): void
    {
        $id = $this->postJson(self::UNIVERSIDADES, ['nombre' => 'Universidad Andina Néstor Cáceres Velásquez'])
            ->assertCreated()->assertJsonPath('data.estado', 'borrador')->assertJsonPath('data.ciclos', 0)->json('data.id');

        $this->patchJson(self::UNIVERSIDADES . "/{$id}/status", ['estado' => 'publicado'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->postJson(self::UNIVERSIDADES, ['nombre' => 'Otra', 'estado' => 'publicado'])->assertJsonValidationErrors('estado');

        $this->postJson(self::CICLOS, [
            'universidad_id' => $id,
            'nombre'         => 'Verano',
            'turno'          => 'Mañana',
            'modalidades'    => ['virtual', 'presencial'],
            'precio'         => 440,
            'precio_virtual' => 380.5,
            'inicio'         => '2027-01-04',
            'fin'            => '2027-02-26',
        ])->assertCreated()
            ->assertJsonPath('data.clave', 'universidad-andina-nestor-caceres-velasquez-verano')
            ->assertJsonPath('data.modalidades', ['presencial', 'virtual']);

        $this->patchJson(self::UNIVERSIDADES . "/{$id}/status", ['estado' => 'publicado'])->assertOk()->assertJsonPath('data.ciclos', 1);

        $uancv = collect($this->portal('academia')['universidades'])->last();
        $this->assertSame('Universidad Andina Néstor Cáceres Velásquez', $uancv['nombre']);
        $this->assertEquals(['id' => 'universidad-andina-nestor-caceres-velasquez-verano', 'nombre' => 'Verano', 'turno' => 'Mañana',
            'modalidades' => ['presencial', 'virtual'], 'precio' => 440, 'precioVirtual' => 380.5, 'inicio' => '2027-01-04', 'fin' => '2027-02-26'], $uancv['ciclos'][0]);
    }

    public function test_las_reglas_de_un_ciclo(): void
    {
        $unsa = $this->universidad('unsa');
        $ciclo = $unsa->ciclos->first();
        $base = ['universidad_id' => $unsa->id, 'nombre' => 'Intensivo', 'modalidades' => ['presencial']];

        $this->postJson(self::CICLOS, ['modalidades' => []] + $base)->assertJsonValidationErrors('modalidades');
        $this->postJson(self::CICLOS, ['modalidades' => ['hibrida']] + $base)->assertJsonValidationErrors('modalidades.0');
        $this->postJson(self::CICLOS, ['precio' => -1] + $base)->assertJsonValidationErrors('precio');
        $this->postJson(self::CICLOS, ['precio' => 10.555] + $base)->assertJsonValidationErrors('precio');
        $this->postJson(self::CICLOS, ['inicio' => '2027-03-01', 'fin' => '2027-02-01'] + $base)->assertJsonValidationErrors('fin');

        // Al editar solo el fin, se compara con el inicio guardado.
        $this->putJson(self::CICLOS . "/{$ciclo->id}", ['inicio' => '2027-03-01'])->assertOk();
        $this->putJson(self::CICLOS . "/{$ciclo->id}", ['fin' => '2027-02-01'])->assertJsonValidationErrors('fin');

        // No se muda a otra universidad.
        $this->putJson(self::CICLOS . "/{$ciclo->id}", ['universidad_id' => $this->universidad('una-puno')->id])
            ->assertJsonPath('errors.universidad_id.0', 'El campo universidad no se puede cambiar.');
        $this->postJson(self::CICLOS, ['precio' => 10.555] + $base)->assertJsonPath('errors.precio.0', 'El campo precio admite hasta dos decimales.');

        // El precio virtual solo con la modalidad virtual.
        $this->putJson(self::CICLOS . "/{$ciclo->id}", ['modalidades' => ['presencial'], 'precio_virtual' => 300])
            ->assertOk()->assertJsonPath('data.precio_virtual', null);

        $this->portal('academia');
    }

    public function test_los_ciclos_se_ordenan_dentro_de_su_universidad_y_el_tope_es_doce(): void
    {
        $unsa = $this->universidad('unsa');
        $ids = $unsa->ciclos->pluck('id')->reverse()->values()->all();

        $this->postJson(self::CICLOS . '/order', ['ids' => $ids])->assertJsonValidationErrors('universidad');
        $this->postJson(self::CICLOS . "/order?universidad={$unsa->id}", ['ids' => $ids])->assertOk();

        $this->assertSame(['unsa-semestral', 'unsa-verano', 'unsa-repaso'],
            array_column(collect($this->portal('academia')['universidades'])->firstWhere('id', 'unsa')['ciclos'], 'id'));
        $this->assertCount(3, $this->getJson(self::CICLOS . "?universidad={$unsa->id}")->json('data'));

        foreach (range(4, 12) as $i) {
            $this->postJson(self::CICLOS, ['universidad_id' => $unsa->id, 'nombre' => "Ciclo {$i}", 'modalidades' => ['virtual']])->assertCreated();
        }
        $this->postJson(self::CICLOS, ['universidad_id' => $unsa->id, 'nombre' => 'Uno más', 'modalidades' => ['virtual']])
            ->assertJsonValidationErrors('universidad_id');
        $this->assertCount(12, collect($this->portal('academia')['universidades'])->firstWhere('id', 'unsa')['ciclos']);
    }

    public function test_el_ultimo_ciclo_de_una_universidad_publicada_no_se_elimina(): void
    {
        $unsa = $this->universidad('unsa');
        [$a, $b, $c] = $unsa->ciclos->pluck('id')->all();

        $this->deleteJson(self::CICLOS . "/{$a}")->assertOk();
        $this->deleteJson(self::CICLOS . "/{$b}")->assertOk();
        $this->deleteJson(self::CICLOS . "/{$c}")->assertUnprocessable()->assertJsonValidationErrors('ciclo');

        // En borrador, sí.
        $this->patchJson(self::UNIVERSIDADES . "/{$unsa->id}/status", ['estado' => 'borrador'])->assertOk();
        $this->deleteJson(self::CICLOS . "/{$c}")->assertOk();
        $this->assertNull(collect($this->portal('academia')['universidades'])->firstWhere('id', 'unsa'));
    }

    public function test_eliminar_una_universidad_se_lleva_sus_ciclos(): void
    {
        $unsa = $this->universidad('unsa');

        $this->deleteJson(self::UNIVERSIDADES . "/{$unsa->id}")->assertOk();

        $this->assertSame(0, Ciclo::where('universidad_id', $unsa->id)->count());
        $this->assertNull(collect($this->portal('academia')['universidades'])->firstWhere('id', 'unsa'));
    }

    // ───────── Plataformas ─────────

    public function test_una_plataforma_con_sus_funciones_y_su_enlace(): void
    {
        $id = $this->postJson(self::PLATAFORMAS, [
            'nombre'      => 'Biblioteca virtual',
            'descripcion' => 'Libros y revistas para toda la comunidad.',
            'funciones'   => [['titulo' => 'Estudiantes', 'texto' => 'Leen y descargan libros.']],
            'enlace'      => ['etiqueta' => 'Entrar a la biblioteca', 'url' => 'https://biblioteca.cata.edu.pe'],
            'aclaracion'  => null,
            'estado'      => 'publicado',
        ])->assertCreated()->assertJsonPath('data.enlace.externo', true)->json('data.id');

        $plataforma = collect($this->portal('plataformas'))->firstWhere('id', 'biblioteca-virtual');
        $this->assertSame('https://biblioteca.cata.edu.pe', $plataforma['enlace']['url']);
        $this->assertSame('Estudiantes', $plataforma['funciones'][0]['titulo']);

        // Sin dirección, sin enlace.
        $this->putJson(self::PLATAFORMAS . "/{$id}", ['enlace' => ['etiqueta' => '', 'url' => '']])->assertJsonValidationErrors('enlace.url');
        $this->putJson(self::PLATAFORMAS . "/{$id}", ['enlace' => null])->assertOk()->assertJsonPath('data.enlace', null);

        $this->putJson(self::PLATAFORMAS . "/{$id}", ['enlace' => ['etiqueta' => 'Entrar', 'url' => 'http://biblioteca.cata.edu.pe']])
            ->assertJsonValidationErrors('enlace.url');
        $this->putJson(self::PLATAFORMAS . "/{$id}", ['funciones' => array_fill(0, 9, ['titulo' => 'A', 'texto' => 'B'])])
            ->assertJsonValidationErrors('funciones');
        $this->putJson(self::PLATAFORMAS . "/{$id}", ['funciones' => [['titulo' => 'A', 'texto' => '']]])
            ->assertJsonValidationErrors('funciones.0.texto');
        $this->assertNull(collect($this->portal('plataformas'))->firstWhere('id', 'biblioteca-virtual')['enlace']);
    }

    public function test_rrhh_no_edita_propuesta(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        foreach ([self::PILARES, self::NIVELES, self::UNIVERSIDADES, self::CICLOS, self::PLATAFORMAS, self::SECCIONES . 'propuesta-cabecera'] as $ruta) {
            $this->getJson($ruta)->assertForbidden();
        }
    }
}
