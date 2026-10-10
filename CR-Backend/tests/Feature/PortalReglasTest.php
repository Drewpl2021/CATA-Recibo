<?php

namespace Tests\Feature;

use App\Models\Portal\Banner;
use App\Models\Portal\Ciclo;
use App\Models\Portal\Cifra;
use App\Models\Portal\DocenteNivel;
use App\Models\Portal\FechaMatricula;
use App\Models\Portal\Imagen;
use App\Models\Portal\Logro;
use App\Models\Portal\Noticia;
use App\Models\Portal\Seccion;
use App\Models\Portal\Universidad;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Lo que la ida y vuelta contra los ejemplos no cubre: paginación y
 * parámetros, borradores, el orden que fija el contrato (no el del panel),
 * secciones sin cargar y lo que el panel podría guardar de más.
 */
class PortalReglasTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private function importar(string $escenario = 'tipico'): void
    {
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), $escenario))->importar();
    }

    // ───────── Noticias ─────────

    public function test_noticias_por_paginas(): void
    {
        $this->importar('largo');
        $todas = $this->getJson('/api/portal/v1/noticias?limite=24')->json('data');

        $respuesta = $this->getJson('/api/portal/v1/noticias?limite=2&pagina=2')->assertOk();

        $this->assertSame(array_slice(array_column($todas, 'id'), 2, 2), array_column($respuesta->json('data'), 'id'));
        $this->assertSame(['pagina' => 2, 'limite' => 2, 'total' => 6], $respuesta->json('meta'));
        $this->assertCumpleElContrato('/v1/noticias', $respuesta->getContent());

        // Pasada la última página no es un error: la lista viene vacía.
        $this->getJson('/api/portal/v1/noticias?pagina=99')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 6);
    }

    public function test_noticias_con_parametros_que_no_valen_responden_400(): void
    {
        $this->importar();

        foreach (['limite=abc', 'limite=0', 'limite=25', 'limite=1.5', 'pagina=0', 'pagina=-1', 'pagina=x'] as $consulta) {
            $this->getJson("/api/portal/v1/noticias?{$consulta}")
                ->assertStatus(400)
                ->assertJsonPath('error.codigo', 'PARAMETRO_INVALIDO');
        }
    }

    public function test_noticias_por_fecha_aunque_el_orden_manual_diga_otra_cosa(): void
    {
        $this->importar();
        // La más antigua, puesta primera a mano: igual sale última.
        Noticia::orderBy('fecha')->first()->update(['orden' => 0]);

        $fechas = array_column($this->getJson('/api/portal/v1/noticias')->json('data'), 'fecha');

        $this->assertSame(collect($fechas)->sortDesc()->values()->all(), $fechas);
    }

    public function test_una_noticia_en_borrador_no_sale_ni_cuenta(): void
    {
        $this->importar();
        $total = Noticia::count();
        Noticia::first()->update(['estado' => Noticia::BORRADOR]);

        $respuesta = $this->getJson('/api/portal/v1/noticias')->assertOk();

        $this->assertCount($total - 1, $respuesta->json('data'));
        $this->assertSame($total - 1, $respuesta->json('meta.total'));
    }

    // ───────── Orden y borradores en las listas ─────────

    public function test_las_listas_salen_en_su_orden_y_sin_borradores(): void
    {
        $this->importar();
        [$primero, $segundo] = Banner::orderBy('orden')->take(2)->get();
        $primero->update(['orden' => 99]);
        $segundo->update(['estado' => Banner::BORRADOR]);

        $ids = array_column($this->getJson('/api/portal/v1/portada/banners')->json('data'), 'id');

        $this->assertNotContains($segundo->clave, $ids);
        $this->assertSame($primero->clave, end($ids));
    }

    public function test_el_cronograma_va_por_fecha_de_inicio(): void
    {
        $this->importar();
        FechaMatricula::orderBy('inicio')->first()->update(['orden' => 99]);

        $inicios = array_column($this->getJson('/api/portal/v1/matricula/fechas')->json('data.items'), 'inicio');

        $this->assertSame(collect($inicios)->sort()->values()->all(), $inicios);
    }

    public function test_cada_ambito_tiene_sus_cifras(): void
    {
        $this->importar();

        $ids = array_column($this->getJson('/api/portal/v1/portada/cifras')->json('data'), 'id');

        $this->assertSame(Cifra::de('portada')->orderBy('orden')->pluck('clave')->all(), $ids);
    }

    // ───────── Docentes y logros ─────────

    public function test_un_docente_de_un_nivel_en_borrador_no_sale(): void
    {
        $this->importar();
        $nivel = DocenteNivel::orderBy('orden')->first();
        $nivel->update(['estado' => DocenteNivel::BORRADOR]);

        $respuesta = $this->getJson('/api/portal/v1/docentes')->assertOk();

        $this->assertNotContains($nivel->clave, array_column($respuesta->json('data.niveles'), 'id'));
        $this->assertNotContains($nivel->clave, array_column($respuesta->json('data.docentes'), 'nivel'));
        $this->assertCumpleElContrato('/v1/docentes', $respuesta->getContent());
    }

    public function test_un_logro_en_borrador_no_sale(): void
    {
        $this->importar();
        $logro = Logro::first();
        $logro->update(['estado' => Logro::BORRADOR]);

        $ids = array_column($this->getJson('/api/portal/v1/logros')->json('data.logros'), 'id');

        $this->assertNotContains($logro->clave, $ids);
    }

    // ───────── Academia ─────────

    public function test_los_precios_salen_como_numero_con_o_sin_centimos(): void
    {
        $this->importar('largo');
        [$entero, $conCentimos] = Ciclo::whereNotNull('precio')->orderBy('clave')->take(2)->get();
        $entero->update(['precio' => 440]);
        $conCentimos->update(['precio' => 172.5, 'precio_virtual' => null]);

        $respuesta = $this->getJson('/api/portal/v1/propuesta-educativa/academia')->assertOk();
        $ciclos = collect($respuesta->json('data.universidades'))->flatMap(fn ($u) => $u['ciclos'])->keyBy('id');

        $this->assertSame(440, $ciclos[$entero->clave]['precio']);
        $this->assertSame(172.5, $ciclos[$conCentimos->clave]['precio']);
        $this->assertNull($ciclos[$conCentimos->clave]['precioVirtual']);
        // En el JSON mismo, sin comillas ni ".00".
        $this->assertStringContainsString('"precio":440,', $respuesta->getContent());
        $this->assertStringContainsString('"precio":172.5,', $respuesta->getContent());
        $this->assertCumpleElContrato('/v1/propuesta-educativa/academia', $respuesta->getContent());
    }

    public function test_una_universidad_en_borrador_no_sale_con_sus_ciclos(): void
    {
        $this->importar();
        $universidad = Universidad::first();
        $universidad->update(['estado' => Universidad::BORRADOR]);

        $ids = array_column($this->getJson('/api/portal/v1/propuesta-educativa/academia')->json('data.universidades'), 'id');

        $this->assertNotContains($universidad->clave, $ids);
    }

    // ───────── Secciones sin cargar ─────────

    public function test_una_seccion_sin_cargar_responde_503_solo_en_su_endpoint(): void
    {
        $this->importar();
        Seccion::whereKey('quienes-somos')->delete();

        $this->getJson('/api/portal/v1/quienes-somos')
            ->assertStatus(503)
            ->assertJsonPath('error.codigo', 'NO_DISPONIBLE');

        // El resto de Inicio sigue en pie.
        $this->getJson('/api/portal/v1/niveles')->assertOk();
        $this->getJson('/api/portal/v1/portada/banners')->assertOk();
    }

    public function test_un_obligatorio_vacio_dentro_de_una_ficha_tambien_es_503(): void
    {
        $this->importar();
        $contenido = Seccion::contenido('quienes-somos');
        $contenido['parrafos'] = ['   '];
        Seccion::find('quienes-somos')->update(['contenido' => $contenido]);

        $this->getJson('/api/portal/v1/quienes-somos')->assertStatus(503);
    }

    // ───────── Lo que el panel podría guardar de más ─────────

    public function test_una_clave_de_mas_o_espacios_no_llegan_al_portal(): void
    {
        $this->importar();
        $banner = Banner::whereNotNull('accion')->first();
        $banner->update([
            'titulo' => "  {$banner->titulo}  ",
            'accion' => $banner->accion + ['colorDeFondo' => 'rojo'],
        ]);

        $respuesta = $this->getJson('/api/portal/v1/portada/banners')->assertOk();

        $this->assertCumpleElContrato('/v1/portada/banners', $respuesta->getContent());
        $this->assertSame(trim($banner->titulo), collect($respuesta->json('data'))->firstWhere('id', $banner->clave)['titulo']);
    }

    public function test_una_imagen_subida_sale_con_su_direccion_https(): void
    {
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->importar();
        Imagen::find(Banner::first()->imagen_id)->update(['url_externa' => null, 'ruta' => 'banners/0199-afiche.webp']);

        $url = $this->getJson('/api/portal/v1/portada/banners')->json('data.0.imagen.url');

        $this->assertSame('https://rrhh.cata.edu.pe/api/portal/medios/banners/0199-afiche.webp', $url);
    }
}
