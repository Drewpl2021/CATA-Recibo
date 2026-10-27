<?php

namespace Tests\Feature;

use App\Models\Portal\Imagen;
use App\Models\Portal\Pagina;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Panel del portal, etapa 9, parte B: el editor de bloques de las
 * subpáginas de Nosotros. No se guarda un bloque que el portal no mostraría
 * tal cual, y el mensaje dice qué falla y en qué bloque (Bloques::problemas,
 * la misma clase que los sirve).
 */
class PortalPanelBloquesTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const PAGINAS = '/api/web-portal/pages';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), 'tipico'))->importar();
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    private function pagina(string $slug = 'mision-y-vision'): Pagina
    {
        return Pagina::where('slug', $slug)->firstOrFail();
    }

    private function guardar(Pagina $pagina, array $bloques): \Illuminate\Testing\TestResponse
    {
        $version = $this->getJson(self::PAGINAS . "/{$pagina->id}")->json('data.version');

        return $this->putJson(self::PAGINAS . "/{$pagina->id}/blocks", ['bloques' => $bloques, 'version' => $version]);
    }

    private function enElPortal(string $slug = 'mision-y-vision'): array
    {
        $respuesta = $this->getJson("/api/portal/v1/nosotros/paginas/{$slug}")->assertOk();
        $this->assertCumpleElContrato('/v1/nosotros/paginas/{slug}', $respuesta->getContent());

        return $respuesta->json('data.bloques');
    }

    /** Un bloque de cada uno de los 11 tipos, todos válidos. */
    private function losOnce(): array
    {
        $foto = ['imagenId' => Imagen::value('id')];
        $cargo = fn (string $c, array $hijos = []) => ['cargo' => $c, 'detalle' => null, 'hijos' => $hijos];

        return [
            ['tipo' => 'texto', 'titulo' => 'Nuestra historia', 'parrafos' => ['Uno.', 'Dos.'], 'firma' => ['nombre' => 'Dirección', 'detalle' => null]],
            ['tipo' => 'destacado', 'rotulo' => 'Misión', 'texto' => 'Formar ciudadanos íntegros.'],
            ['tipo' => 'lista', 'titulo' => 'Valores', 'estilo' => 'tarjetas', 'items' => [['titulo' => 'Integridad', 'texto' => '«…»', 'fuente' => 'La Educación, p. 57']]],
            ['tipo' => 'hitos', 'titulo' => null, 'items' => [['fecha' => '1971', 'texto' => 'Se funda el colegio.']]],
            ['tipo' => 'imagen', 'imagen' => $foto, 'pie' => 'El emblema', 'transcripcion' => null],
            ['tipo' => 'personas', 'titulo' => 'Consejo', 'personas' => [
                ['nombre' => 'Ana Pérez', 'cargo' => 'Presidenta', 'foto' => $foto],
                ['nombre' => 'Ana Pérez', 'cargo' => 'Secretaria', 'foto' => null],
            ]],
            ['tipo' => 'mensaje', 'titulo' => 'Querida familia:', 'parrafos' => ['Bienvenidos.'], 'firma' => ['nombre' => 'La directora', 'detalle' => 'Directora'], 'foto' => null],
            ['tipo' => 'letra', 'titulo' => 'Himno', 'partes' => [['titulo' => 'Coro', 'versos' => ['Verso uno', 'Verso dos']]]],
            ['tipo' => 'audio', 'titulo' => 'Himno cantado', 'url' => 'https://cata.edu.pe/himno.mp3', 'formato' => 'audio/mpeg', 'descripcion' => null],
            ['tipo' => 'organigrama', 'titulo' => null, 'imagen' => null, 'enlace' => null, 'raiz' => $cargo('Dirección', [$cargo('Coordinación')])],
            ['tipo' => 'enlaces', 'titulo' => 'Documentos', 'enlaces' => [
                ['etiqueta' => 'Reglamento', 'url' => 'https://cata.edu.pe/r.pdf', 'descripcion' => null, 'formato' => 'pdf', 'peso' => 120000],
            ]],
        ];
    }

    public function test_los_once_tipos_se_guardan_y_el_portal_los_muestra_todos(): void
    {
        $pagina = $this->pagina();

        $this->guardar($pagina, $this->losOnce())->assertOk()
            ->assertJsonCount(11, 'data.bloques')
            // Las personas reciben su id, sin repetirse en el bloque.
            ->assertJsonPath('data.bloques.5.personas.0.id', 'ana-perez')
            ->assertJsonPath('data.bloques.5.personas.1.id', 'ana-perez-2');

        $bloques = $this->enElPortal();
        $this->assertSame(
            ['texto', 'destacado', 'lista', 'hitos', 'imagen', 'personas', 'mensaje', 'letra', 'audio', 'organigrama', 'enlaces'],
            array_column($bloques, 'tipo'),
        );
        $this->assertStringStartsWith('https://', $bloques[4]['imagen']['url']);
    }

    public function test_cada_pagina_del_ejemplo_se_vuelve_a_guardar_tal_cual(): void
    {
        foreach (Pagina::all() as $pagina) {
            $leida = $this->getJson(self::PAGINAS . "/{$pagina->id}")->assertOk();
            $this->assertCount(count($pagina->bloques), $leida->json('data.bloques'));

            $this->putJson(self::PAGINAS . "/{$pagina->id}/blocks", [
                'bloques' => $leida->json('data.bloques'),
                'version' => $leida->json('data.version'),
            ])->assertOk();

            $this->assertCount(count($pagina->bloques), $this->enElPortal($pagina->slug), "La página «{$pagina->slug}» perdió bloques.");
        }
    }

    public function test_dice_que_falla_y_en_que_bloque(): void
    {
        $pagina = $this->pagina();
        $antes = $pagina->bloques;
        $bueno = ['tipo' => 'destacado', 'rotulo' => 'Misión', 'texto' => 'Formar ciudadanos íntegros.'];
        $cargo = fn (string $c, array $hijos = []) => ['cargo' => $c, 'detalle' => null, 'hijos' => $hijos];

        $casos = [
            'Bloque 2 (Lista): no tiene ningún elemento.'
                => ['tipo' => 'lista', 'titulo' => null, 'estilo' => 'vinetas', 'items' => []],
            'Bloque 2 (Lista): el elemento 2 no tiene texto.'
                => ['tipo' => 'lista', 'titulo' => null, 'estilo' => 'vinetas', 'items' => [['texto' => 'Uno'], ['titulo' => 'Dos', 'texto' => ' ']]],
            'Bloque 2: el tipo «carrusel» no existe.'
                => ['tipo' => 'carrusel'],
            'Bloque 2 (Carta): la carta va firmada: falta el nombre de quien la firma.'
                => ['tipo' => 'mensaje', 'titulo' => null, 'parrafos' => ['Hola.'], 'firma' => null, 'foto' => null],
            'Bloque 2 (Audio): el formato debe ser MP3, M4A, OGG o WAV: el portal no reproduce otros.'
                => ['tipo' => 'audio', 'titulo' => 'Himno', 'url' => 'https://x.pe/h.flac', 'formato' => 'audio/flac', 'descripcion' => null],
            'Bloque 2 (Audio): la dirección del audio debe empezar con https://.'
                => ['tipo' => 'audio', 'titulo' => 'Himno', 'url' => 'http://x.pe/h.mp3', 'formato' => 'audio/mpeg', 'descripcion' => null],
            'Bloque 2 (Destacado): el texto tiene 201 caracteres: admite hasta 200.'
                => ['tipo' => 'destacado', 'rotulo' => 'Visión', 'texto' => str_repeat('v', 201)],
            'Bloque 2 (Texto): tiene 21 párrafos: admite hasta 20.'
                => ['tipo' => 'texto', 'titulo' => null, 'parrafos' => array_fill(0, 21, 'p'), 'firma' => null],
            'Bloque 2 (Texto): el párrafo 2 está vacío.'
                => ['tipo' => 'texto', 'titulo' => null, 'parrafos' => ['Uno.', '   '], 'firma' => null],
            'Bloque 2 (Letra): la parte 1 tiene 17 versos: admite hasta 16.'
                => ['tipo' => 'letra', 'titulo' => null, 'partes' => [['titulo' => 'Estrofa', 'versos' => array_fill(0, 17, 'v')]]],
            'Bloque 2 (Documentos): el documento 1 tiene una dirección que no empieza con https://.'
                => ['tipo' => 'enlaces', 'titulo' => null, 'enlaces' => [['etiqueta' => 'PEI', 'url' => 'http://x.pe/pei.pdf', 'descripcion' => null, 'formato' => 'pdf', 'peso' => null]]],
            'Bloque 2 (Organigrama): «5» está en el quinto nivel del organigrama y tiene cargos debajo: el organigrama llega hasta 5 niveles.'
                => ['tipo' => 'organigrama', 'titulo' => null, 'imagen' => null, 'enlace' => null,
                    'raiz' => $cargo('1', [$cargo('2', [$cargo('3', [$cargo('4', [$cargo('5', [$cargo('6')])])])])])],
            'Bloque 2 (Organigrama): «Dirección» tiene 13 cargos debajo: admite hasta 12.'
                => ['tipo' => 'organigrama', 'titulo' => null, 'imagen' => null, 'enlace' => null,
                    'raiz' => $cargo('Dirección', array_map(fn ($i) => $cargo("Área {$i}"), range(1, 13)))],
            'Bloque 2 (Imagen): una de sus imágenes ya no existe. Vuelve a subirla.'
                => ['tipo' => 'imagen', 'imagen' => ['imagenId' => '0199aaaa-0000-7000-8000-000000000000'], 'pie' => null, 'transcripcion' => null],
            'Bloque 2 (Imagen): falta la imagen.'
                => ['tipo' => 'imagen', 'imagen' => null, 'pie' => null, 'transcripcion' => null],
        ];

        foreach ($casos as $mensaje => $malo) {
            $this->guardar($pagina, [$bueno, $malo])
                ->assertUnprocessable()
                ->assertJsonPath('errors.bloques.0', $mensaje);
        }

        // Nada de eso se guardó.
        $this->assertSame($antes, $pagina->refresh()->bloques);
    }

    public function test_varios_problemas_se_dicen_todos_y_la_pagina_tiene_tope(): void
    {
        $pagina = $this->pagina();

        $errores = $this->guardar($pagina, [
            ['tipo' => 'hitos', 'titulo' => null, 'items' => [['fecha' => '', 'texto' => 'Sin fecha']]],
            ['tipo' => 'personas', 'titulo' => null, 'personas' => [['nombre' => '', 'cargo' => 'Docente']]],
        ])->assertUnprocessable()->json('errors.bloques');

        $this->assertSame([
            'Bloque 1 (Línea de tiempo): al hito 1 le falta la fecha.',
            'Bloque 1 (Línea de tiempo): no tiene ningún hito.',
            'Bloque 2 (Personas): la persona 1 no tiene nombre.',
            'Bloque 2 (Personas): no tiene ninguna persona.',
        ], $errores);

        $this->guardar($pagina, array_fill(0, 31, ['tipo' => 'destacado', 'rotulo' => 'R', 'texto' => 'T']))
            ->assertJsonValidationErrors('bloques');
    }

    public function test_sin_bloques_la_pagina_se_publicara_pronto(): void
    {
        $pagina = $this->pagina();

        $this->guardar($pagina, [])->assertOk()->assertJsonPath('data.bloques', []);
        $this->assertSame([], $this->enElPortal());
    }

    public function test_si_alguien_guardo_antes_no_se_pisa(): void
    {
        $pagina = $this->pagina();
        $version = $this->getJson(self::PAGINAS . "/{$pagina->id}")->json('data.version');

        $this->travel(5)->seconds();
        $this->putJson(self::PAGINAS . "/{$pagina->id}", ['titulo' => 'Otro título'])->assertOk();

        $this->putJson(self::PAGINAS . "/{$pagina->id}/blocks", ['bloques' => [], 'version' => $version])->assertStatus(409);
        $this->assertNotSame([], $pagina->refresh()->bloques);
    }

    public function test_el_portal_omite_lo_que_no_cumple_aunque_venga_de_otro_lado(): void
    {
        // La última red: algo cargado sin pasar por el panel (una importación, la base a mano).
        $pagina = $this->pagina();
        $pagina->update(['bloques' => [
            ['tipo' => 'audio', 'titulo' => 'Himno', 'url' => 'http://x.pe/h.mp3', 'formato' => 'audio/mpeg', 'descripcion' => null],
            ['tipo' => 'texto', 'titulo' => null, 'parrafos' => array_fill(0, 21, 'p'), 'firma' => null],
            ['tipo' => 'destacado', 'rotulo' => 'Misión', 'texto' => 'Formar.'],
        ]]);

        $this->assertSame(['destacado'], array_column($this->enElPortal(), 'tipo'));
    }

    public function test_rrhh_no_edita_bloques(): void
    {
        $pagina = $this->pagina();
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        $this->putJson(self::PAGINAS . "/{$pagina->id}/blocks", ['bloques' => []])->assertForbidden();
    }
}
