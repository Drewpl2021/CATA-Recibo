<?php

namespace Tests\Feature;

use App\Models\Portal\Banner;
use App\Models\Portal\Imagen;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GET /api/portal/medios/{archivo}: las imágenes subidas desde el panel. Que
 * se sirvan con su tipo y guardables un año, y que no se sirva nada más: ni
 * un archivo sin registrar, ni un SVG, ni nada fuera del disco del portal.
 */
class PortalMediosTest extends TestCase
{
    use RefreshDatabase;

    /** Un PNG de 1 × 1 px, de verdad. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('portal');
    }

    private function subir(string $archivo, string $contenido = ''): Imagen
    {
        Storage::disk('portal')->put($archivo, $contenido !== '' ? $contenido : base64_decode(self::PNG));

        return Imagen::create(['ruta' => $archivo, 'alt' => 'Foto de prueba', 'ancho' => 1, 'alto' => 1]);
    }

    public function test_sirve_la_imagen_con_su_tipo_y_guardable_un_anio(): void
    {
        $this->subir('0199b3a2-afiche.png');

        $respuesta = $this->get('/api/portal/medios/0199b3a2-afiche.png')->assertOk();

        $this->assertSame('image/png', $respuesta->headers->get('Content-Type'));
        $this->assertSame(base64_decode(self::PNG), $respuesta->streamedContent());
        foreach (['public', 'max-age=31536000', 'immutable'] as $parte) {
            $this->assertStringContainsString($parte, $respuesta->headers->get('Cache-Control'));
        }
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');
    }

    public function test_cada_extension_con_su_tipo_y_no_el_que_diga_el_archivo(): void
    {
        // Un .webp cuyo contenido es HTML: igual sale como imagen, y con
        // nosniff el navegador no lo interpreta como página.
        $this->subir('trampa.webp', '<html><script>alert(1)</script></html>');
        $this->subir('foto.jpeg');

        $this->get('/api/portal/medios/trampa.webp')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get('/api/portal/medios/foto.jpeg')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_un_archivo_que_no_esta_registrado_no_se_sirve(): void
    {
        Storage::disk('portal')->put('suelto.png', base64_decode(self::PNG));

        $this->getJson('/api/portal/medios/suelto.png')
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
    }

    public function test_registrado_pero_sin_el_archivo_es_404(): void
    {
        Imagen::create(['ruta' => 'perdida.png', 'alt' => '']);

        $this->getJson('/api/portal/medios/perdida.png')->assertNotFound()->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
    }

    public function test_no_sirve_svg_ni_nada_fuera_del_disco_del_portal(): void
    {
        // Aunque estén registrados y existan.
        $this->subir('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->subir('carpeta/foto.png');

        foreach ([
            'logo.svg',
            'carpeta/foto.png',
            '..%2F..%2F.env',
            '../../.env',
            '%2e%2e%2f.env',
            'foto.php',
            'foto.png.php',
            '.htaccess',
            'Foto.PNG',
        ] as $archivo) {
            $this->getJson("/api/portal/medios/{$archivo}")->assertNotFound();
        }
    }

    public function test_la_url_que_da_la_api_lleva_a_la_imagen(): void
    {
        config(['portal.url_medios' => 'http://localhost/api/portal/medios']);
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), 'tipico'))->importar();
        Storage::disk('portal')->put('0199-matriculas.png', base64_decode(self::PNG));
        Imagen::find(Banner::first()->imagen_id)->update(['url_externa' => null, 'ruta' => '0199-matriculas.png']);

        $url = $this->getJson('/api/portal/v1/portada/banners')->json('data.0.imagen.url');

        $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_las_imagenes_no_gastan_el_freno_de_la_api(): void
    {
        config(['portal.limite_por_minuto' => 2]);
        $this->subir('foto.png');

        for ($i = 0; $i < 5; $i++) {
            $this->get('/api/portal/medios/foto.png')->assertOk();
        }

        // Y tienen el suyo.
        config(['portal.limite_medios_por_minuto' => 5]);
        $this->getJson('/api/portal/medios/foto.png')
            ->assertStatus(429)
            ->assertJsonPath('error.codigo', 'DEMASIADAS_PETICIONES');
    }
}
