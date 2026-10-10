<?php

namespace Tests\Feature;

use App\Models\Portal\RedSocial;
use App\Models\Portal\Seccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * GET /api/portal/v1/sitio de punta a punta, y con él la infraestructura de
 * toda la API del portal: sin token, solo GET, CORS con lista propia, las
 * cabeceras y los errores del contrato, y el resto del sistema intacto.
 */
class PortalSitioTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const RUTA = '/api/portal/v1/sitio';

    public static function escenarios(): array
    {
        return array_map(fn ($e) => [$e], [
            'tipico' => 'tipico', 'corto' => 'corto', 'largo' => 'largo',
            'sin-foto' => 'sin-foto', 'alt-vacio' => 'alt-vacio', 'vacio' => 'vacio',
        ]);
    }

    #[DataProvider('escenarios')]
    public function test_cada_escenario_cumple_el_contrato_y_sale_igual_al_mock(string $escenario): void
    {
        $mock = $this->ejemplo($escenario, 'sitio.json');
        $this->cargarSitio($mock['data']);

        $respuesta = $this->getJson(self::RUTA)->assertOk();

        $this->assertCumpleElContrato('/v1/sitio', $respuesta->getContent());
        // Idéntico, no solo «parecido»: mismas claves, mismo orden, mismos null.
        $this->assertSame($mock, $respuesta->json());
    }

    public function test_responde_sin_token_con_las_cabeceras_del_contrato(): void
    {
        $this->cargarSitio($this->ejemplo('tipico', 'sitio.json')['data']);

        $respuesta = $this->get(self::RUTA)->assertOk();

        $this->assertSame('application/json; charset=utf-8', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('public', $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=60', $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('Origin', $respuesta->headers->get('Vary'));
        // Acentos y URL tal cual, no ú ni https:\/\/.
        $this->assertStringContainsString('Túpac', $respuesta->getContent());
        $this->assertStringContainsString('"https://', $respuesta->getContent());
    }

    public function test_cors_solo_para_los_origenes_del_portal(): void
    {
        config(['portal.origenes' => ['https://cata.edu.pe', 'http://localhost:5173']]);
        $this->cargarSitio($this->ejemplo('tipico', 'sitio.json')['data']);

        $this->get(self::RUTA, ['Origin' => 'https://cata.edu.pe'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://cata.edu.pe')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');

        // Ni otra página de internet, ni la pantalla de RR.HH.: el permiso
        // de una no pasa a la otra.
        foreach (['https://otro-sitio.com', config('app.frontend_url')] as $ajeno) {
            $this->get(self::RUTA, ['Origin' => $ajeno])
                ->assertOk()
                ->assertHeaderMissing('Access-Control-Allow-Origin');
        }
    }

    public function test_el_preflight_lo_contesta_el_portal(): void
    {
        config(['portal.origenes' => ['https://cata.edu.pe']]);

        $this->call('OPTIONS', self::RUTA, server: [
            'HTTP_ORIGIN' => 'https://cata.edu.pe',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://cata.edu.pe')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
    }

    public function test_lo_que_no_existe_y_lo_que_no_es_get_responden_404_del_portal(): void
    {
        config(['portal.origenes' => ['https://cata.edu.pe']]);
        $this->cargarSitio($this->ejemplo('tipico', 'sitio.json')['data']);

        $peticiones = [
            ['GET', '/api/portal/v1/no-existe'],
            ['GET', '/api/portal/v1/proyectos/no-existe'],
            ['POST', self::RUTA],
            ['PUT', self::RUTA],
            ['DELETE', self::RUTA],
        ];

        foreach ($peticiones as [$metodo, $ruta]) {
            $respuesta = $this->json($metodo, $ruta, [], ['Origin' => 'https://cata.edu.pe']);

            $respuesta->assertNotFound()
                ->assertJsonPath('error.codigo', 'NO_ENCONTRADO')
                ->assertJsonMissingPath('success')
                // Con CORS también en el 404: si no, el portal no lo puede leer.
                ->assertHeader('Access-Control-Allow-Origin', 'https://cata.edu.pe')
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame(['error'], array_keys($respuesta->json()), "{$metodo} {$ruta}");
        }

        // Y nada cambió.
        $this->assertSame(1, Seccion::count());
    }

    public function test_sin_los_datos_del_colegio_responde_503_y_no_inventa_nada(): void
    {
        $this->getJson(self::RUTA)
            ->assertStatus(503)
            ->assertExactJson(['error' => [
                'codigo'  => 'NO_DISPONIBLE',
                'mensaje' => 'Esta sección del portal todavía no está disponible.',
            ]]);

        // Cargado a medias: falta un obligatorio.
        $sitio = $this->ejemplo('tipico', 'sitio.json')['data'];
        $sitio['whatsapp']['numero'] = '   ';
        $this->cargarSitio($sitio);

        $this->getJson(self::RUTA)->assertStatus(503)->assertJsonPath('error.codigo', 'NO_DISPONIBLE');
    }

    public function test_textos_limpios_y_los_opcionales_vacios_como_null(): void
    {
        $sitio = $this->ejemplo('tipico', 'sitio.json')['data'];
        $sitio['nombre'] = '  Colegio Adventista Túpac Amaru ';
        $sitio['subtitulo'] = '   ';
        unset($sitio['promotora'], $sitio['direccion']['distrito']);
        $sitio['portalAcademico'] = null;
        $this->cargarSitio($sitio);

        $respuesta = $this->getJson(self::RUTA)->assertOk();

        $respuesta->assertJsonPath('data.nombre', 'Colegio Adventista Túpac Amaru');
        // Los que faltan salen igual, en null: el portal descarta una respuesta con claves de menos.
        $this->assertNull($respuesta->json('data.subtitulo'));
        $this->assertArrayHasKey('promotora', $respuesta->json('data'));
        $this->assertNull($respuesta->json('data.promotora'));
        $this->assertNull($respuesta->json('data.direccion.distrito'));
        $this->assertNull($respuesta->json('data.portalAcademico'));
        $this->assertCumpleElContrato('/v1/sitio', $respuesta->getContent());
    }

    public function test_las_redes_en_borrador_no_salen_y_van_en_su_orden(): void
    {
        $this->cargarSitio($this->ejemplo('tipico', 'sitio.json')['data']);
        RedSocial::where('red', 'facebook')->update(['orden' => 99]);
        RedSocial::where('red', 'tiktok')->update(['estado' => 'borrador']);

        $redes = collect($this->getJson(self::RUTA)->assertOk()->json('data.redes'))->pluck('red');

        $this->assertNotContains('tiktok', $redes);
        $this->assertSame('facebook', $redes->last());
    }

    public function test_el_freno_responde_429_con_el_formato_del_portal(): void
    {
        config(['portal.limite_por_minuto' => 2]);
        $this->cargarSitio($this->ejemplo('tipico', 'sitio.json')['data']);

        $this->getJson(self::RUTA)->assertOk();
        $this->getJson(self::RUTA)->assertOk();
        $this->getJson(self::RUTA)
            ->assertStatus(429)
            ->assertJsonPath('error.codigo', 'DEMASIADAS_PETICIONES')
            ->assertHeader('Retry-After');
    }

    public function test_el_resto_del_sistema_sigue_igual(): void
    {
        config(['portal.origenes' => ['https://cata.edu.pe']]);

        // Las rutas protegidas siguen pidiendo sesión, con el formato de siempre.
        $this->getJson('/api/me')->assertUnauthorized()->assertJsonPath('success', false);

        // Y el origen del portal no gana permiso sobre ellas.
        $this->getJson('/api/me', ['Origin' => 'https://cata.edu.pe'])
            ->assertHeader('Access-Control-Allow-Origin', config('app.frontend_url'));
    }
}
