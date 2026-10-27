<?php

namespace Tests\Feature;

use App\Models\Portal\Imagen;
use App\Models\Portal\Seccion;
use App\Models\Portal\Sede;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Panel del portal, etapa 3: Contacto. La cabecera con su recorte, las sedes
 * y los textos del formulario «Solicitar información».
 */
class PortalPanelContactoTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const CONTACTO = '/api/web-portal/sections/contacto';
    private const SOLICITUD = '/api/web-portal/sections/solicitud';
    private const SEDES = '/api/web-portal/campuses';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), 'tipico'))->importar();
        // Como en producción: las imágenes subidas se sirven por https.
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    public function test_si_las_imagenes_salieran_por_http_el_panel_no_las_publica(): void
    {
        // APP_URL sin https: el portal descartaría la página entera.
        config(['portal.url_medios' => 'http://rrhh.cata.edu.pe/api/portal/medios']);

        $respuesta = $this->putJson(self::CONTACTO, [
            'titulo' => 'Contáctanos', 'figura' => $this->imagenNueva()->id, 'version' => $this->version(self::CONTACTO),
        ])->assertUnprocessable()->assertJsonValidationErrors('contrato');

        $this->assertStringContainsString('https', $respuesta->json('errors.contrato.0'));
        // Y el portal sigue como estaba.
        $this->getJson('/api/portal/v1/contacto')->assertOk()->assertJsonPath('data.titulo', 'Contáctanos');
    }

    private function version(string $ruta): ?string
    {
        return $this->getJson($ruta)->json('data.version');
    }

    private function imagenNueva(): Imagen
    {
        return Imagen::create(['ruta' => 'recorte-nuevo.webp', 'alt' => 'Estudiante de primaria con su mochila', 'ancho' => 500, 'alto' => 1000]);
    }

    // ───────── Cabecera de Contacto ─────────

    public function test_la_ficha_trae_su_figura_para_la_vista_previa(): void
    {
        $datos = $this->getJson(self::CONTACTO)->assertOk()->json('data');
        $figura = $datos['contenido']['figura']['imagenId'];

        $this->assertSame($figura, $datos['imagenes'][$figura]['id']);
        $this->assertStringStartsWith('https://', $datos['imagenes'][$figura]['url']);
    }

    public function test_guardar_lo_mismo_deja_al_portal_identico_al_ejemplo(): void
    {
        $actual = $this->getJson(self::CONTACTO)->json('data');
        $contenido = $actual['contenido'];

        $this->putJson(self::CONTACTO, [
            'titulo'  => $contenido['titulo'],
            'bajada'  => $contenido['bajada'],
            'figura'  => $contenido['figura']['imagenId'],
            'correo'  => $contenido['correo'],
            'version' => $actual['version'],
        ])->assertOk();

        $this->assertIgualAlEjemplo($this->ejemplo('tipico', 'contacto.json'), $this->getJson('/api/portal/v1/contacto')->json());
    }

    public function test_cambiar_la_figura_y_el_correo(): void
    {
        $nueva = $this->imagenNueva();

        $this->putJson(self::CONTACTO, [
            'titulo' => 'Escríbenos', 'bajada' => null, 'figura' => $nueva->id,
            'correo' => 'informes@cata.edu.pe', 'version' => $this->version(self::CONTACTO),
        ])->assertOk()->assertJsonPath("data.imagenes.{$nueva->id}.alt", 'Estudiante de primaria con su mochila');

        $portal = $this->getJson('/api/portal/v1/contacto')->assertOk();
        $portal->assertJsonPath('data.figura.url', $nueva->url())
            ->assertJsonPath('data.correo', 'informes@cata.edu.pe')
            ->assertJsonPath('data.bajada', null);
        $this->assertCumpleElContrato('/v1/contacto', $portal->getContent());

        // Sin figura: el portal no la muestra.
        $this->putJson(self::CONTACTO, ['titulo' => 'Escríbenos', 'figura' => null, 'version' => $this->version(self::CONTACTO)])->assertOk();
        $this->assertNull($this->getJson('/api/portal/v1/contacto')->json('data.figura'));
    }

    public function test_las_reglas_de_la_cabecera(): void
    {
        $version = $this->version(self::CONTACTO);

        $this->putJson(self::CONTACTO, ['titulo' => '', 'version' => $version])->assertUnprocessable()->assertJsonValidationErrors('titulo');
        $this->putJson(self::CONTACTO, ['titulo' => 'Contacto', 'correo' => 'no-es-un-correo', 'version' => $version])
            ->assertUnprocessable()->assertJsonValidationErrors('correo');
        $this->putJson(self::CONTACTO, ['titulo' => 'Contacto', 'figura' => '01999999-9999-7999-8999-999999999999', 'version' => $version])
            ->assertUnprocessable()->assertJsonValidationErrors('figura');
    }

    // ───────── Formulario «Solicitar información» ─────────

    public function test_guardar_los_textos_del_formulario(): void
    {
        $ejemplo = $this->ejemplo('tipico', 'solicitud.json')['data'];
        // Llegan en otro orden: se guardan en el del contrato.
        $ejemplo['mensajesPorPagina'] = array_reverse($ejemplo['mensajesPorPagina']);

        $this->putJson(self::SOLICITUD, $ejemplo + ['version' => $this->version(self::SOLICITUD)])->assertOk();

        $this->assertIgualAlEjemplo($this->ejemplo('tipico', 'solicitud.json'), $this->getJson('/api/portal/v1/solicitud')->json());
    }

    public function test_las_reglas_del_formulario(): void
    {
        $base = $this->ejemplo('tipico', 'solicitud.json')['data'] + ['version' => $this->version(self::SOLICITUD)];

        // Una marca que el portal no reemplaza llegaría tal cual al WhatsApp.
        $this->putJson(self::SOLICITUD, ['mensajeWhatsapp' => 'Hola, soy {nombre}, mi teléfono es {telefono}.'] + $base)
            ->assertUnprocessable()->assertJsonValidationErrors('mensajeWhatsapp');

        // Una sola plantilla por página, y solo de las dos que existen.
        $this->putJson(self::SOLICITUD, ['mensajesPorPagina' => [
            ['pagina' => 'contacto', 'mensaje' => 'Uno'], ['pagina' => 'contacto', 'mensaje' => 'Dos'],
        ]] + $base)->assertUnprocessable()->assertJsonValidationErrors('mensajesPorPagina.1.pagina');
        $this->putJson(self::SOLICITUD, ['mensajesPorPagina' => [['pagina' => 'inicio', 'mensaje' => 'Hola']]] + $base)
            ->assertUnprocessable()->assertJsonValidationErrors('mensajesPorPagina.0.pagina');

        // Sin plantillas propias: todas usan la general.
        $this->putJson(self::SOLICITUD, ['mensajesPorPagina' => []] + $base)->assertOk();
        $this->assertSame([], $this->getJson('/api/portal/v1/solicitud')->json('data.mensajesPorPagina'));
    }

    // ───────── Sedes ─────────

    private function sede(array $cambios = []): array
    {
        return array_replace([
            'nombre'         => 'Sede CATA Oasis 2',
            'direccion'      => 'Av. Modesto Borda s/n',
            'referencia'     => null,
            'distrito'       => 'Juliaca, San Román, Puno',
            'telefono'       => ['numero' => '+51937489399', 'visible' => '937 489 399'],
            'nota'           => 'Talleres.',
            'imagen_id'      => null,
            'mapa_url'       => null,
            'correo'         => null,
            'horario'        => [['dias' => 'Lunes a viernes', 'horas' => '8:00 a 13:00']],
            'mapa_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18',
        ], $cambios);
    }

    public function test_agregar_una_sede_y_publicarla(): void
    {
        $respuesta = $this->postJson(self::SEDES, $this->sede())->assertCreated()
            ->assertJsonPath('data.clave', 'sede-cata-oasis-2')
            ->assertJsonPath('data.estado', 'borrador');

        $this->patchJson(self::SEDES . '/' . $respuesta->json('data.id') . '/status', ['estado' => 'publicado'])->assertOk();

        $sedes = $this->getJson('/api/portal/v1/sedes')->json('data');
        $nueva = end($sedes);
        $this->assertSame('sede-cata-oasis-2', $nueva['id']);
        $this->assertSame([['dias' => 'Lunes a viernes', 'horas' => '8:00 a 13:00']], $nueva['horario']);
        $this->assertCumpleElContrato('/v1/sedes', $this->getJson('/api/portal/v1/sedes')->getContent());
    }

    public function test_la_clave_no_se_repite_ni_cambia_al_editar(): void
    {
        $a = $this->postJson(self::SEDES, $this->sede())->json('data');
        $b = $this->postJson(self::SEDES, $this->sede())->json('data');
        $this->assertSame('sede-cata-oasis-2-2', $b['clave']);

        $this->putJson(self::SEDES . "/{$a['id']}", ['nombre' => 'Sede Oasis renovada'])->assertOk()
            ->assertJsonPath('data.clave', 'sede-cata-oasis-2');
    }

    public function test_las_reglas_de_una_sede(): void
    {
        $casos = [
            'nombre'           => ['nombre' => str_repeat('S', 41)],
            'telefono.numero'  => ['telefono' => ['numero' => '937 489 399', 'visible' => '937 489 399']],
            'telefono.visible' => ['telefono' => ['numero' => '+51937489399', 'visible' => null]],
            'mapa_embed_url'   => ['mapa_embed_url' => 'https://maps.app.goo.gl/abc123'],
            'mapa_url'         => ['mapa_url' => 'http://maps.google.com/?q=Juliaca'],
            'horario'          => ['horario' => array_fill(0, 5, ['dias' => 'Lunes', 'horas' => '8:00'])],
            'horario.0.horas'  => ['horario' => [['dias' => 'Lunes', 'horas' => '']]],
            'correo'           => ['correo' => 'sede@'],
            'imagen_id'        => ['imagen_id' => '01999999-9999-7999-8999-999999999999'],
        ];

        foreach ($casos as $campo => $cambio) {
            $this->postJson(self::SEDES, $this->sede($cambio))->assertUnprocessable()->assertJsonValidationErrors($campo);
        }

        // Sin teléfono y con el mapa de OpenStreetMap: vale.
        $this->postJson(self::SEDES, $this->sede([
            'telefono' => null, 'mapa_embed_url' => 'https://www.openstreetmap.org/export/embed.html?bbox=-70.1,-15.5',
        ]))->assertCreated()->assertJsonPath('data.telefono', null);
    }

    public function test_hasta_ocho_sedes_publicadas(): void
    {
        $publicadas = Sede::where('estado', 'publicado')->count();
        for ($i = $publicadas; $i < 8; $i++) {
            $this->postJson(self::SEDES, $this->sede(['nombre' => "Sede {$i}", 'estado' => 'publicado']))->assertCreated();
        }

        // La novena: publicada no; en borrador sí, y no se puede publicar.
        $this->postJson(self::SEDES, $this->sede(['nombre' => 'Sede 9', 'estado' => 'publicado']))
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
        $id = $this->postJson(self::SEDES, $this->sede(['nombre' => 'Sede 9']))->assertCreated()->json('data.id');
        $this->patchJson(self::SEDES . "/{$id}/status", ['estado' => 'publicado'])
            ->assertUnprocessable()->assertJsonPath('errors.estado.0', 'El portal muestra hasta 8 sedes publicadas. Para publicar más, primero pasa a borrador lo que ya no deba verse.');

        $this->assertCount(8, $this->getJson('/api/portal/v1/sedes')->json('data'));
    }

    public function test_la_foto_de_una_sede(): void
    {
        $foto = Imagen::create(['ruta' => 'sede-nueva.webp', 'alt' => 'Fachada de la sede', 'ancho' => 1200, 'alto' => 900]);
        $sede = Sede::orderBy('orden')->first();

        $this->putJson(self::SEDES . "/{$sede->id}", ['imagen_id' => $foto->id])->assertOk()
            ->assertJsonPath('data.imagen.url', $foto->url());

        $this->assertSame($foto->url(), $this->getJson('/api/portal/v1/sedes')->json('data.0.foto.url'));
    }

    public function test_ordenar_y_eliminar(): void
    {
        $ids = Sede::orderBy('orden')->pluck('id')->all();
        $this->postJson(self::SEDES . '/order', ['ids' => array_reverse($ids)])->assertOk();
        $this->assertSame(Sede::orderBy('orden')->pluck('clave')->all(), array_column($this->getJson('/api/portal/v1/sedes')->json('data'), 'id'));

        $this->deleteJson(self::SEDES . "/{$ids[0]}")->assertOk();
        $this->assertCount(count($ids) - 1, $this->getJson('/api/portal/v1/sedes')->json('data'));
    }

    public function test_rrhh_no_edita_contacto(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        $this->getJson(self::CONTACTO)->assertForbidden();
        $this->putJson(self::SOLICITUD, [])->assertForbidden();
        $this->getJson(self::SEDES)->assertForbidden();
    }
}
