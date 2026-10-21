<?php

namespace Tests\Feature;

use App\Models\Portal\Banner;
use App\Models\Portal\Imagen;
use App\Models\Portal\Seccion;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImagenesDePrueba;
use Tests\TestCase;

/**
 * Panel del portal, etapa 2: la subida de imágenes. Que solo entren imágenes
 * de verdad, derechas, sin metadatos, con nombre nuevo, de un tamaño y peso
 * razonables y con su texto alternativo; y que las sueltas se limpien.
 */
class PortalPanelImagenesTest extends TestCase
{
    use RefreshDatabase;

    private const SUBIR = '/api/web-portal/images';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('portal');
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    private function subir(array $datos)
    {
        return $this->post(self::SUBIR, $datos, ['Accept' => 'application/json']);
    }

    private function guardada(string $id): string
    {
        return Storage::disk('portal')->get(Imagen::findOrFail($id)->ruta);
    }

    // ───────── Lo que le pasa a la foto ─────────

    public function test_la_foto_del_celular_se_endereza_y_pierde_sus_metadatos(): void
    {
        $respuesta = $this->subir([
            'archivo' => ImagenesDePrueba::jpegDeCelular(300, 200, 6),
            'uso'     => 'sede',
            'alt'     => 'Fachada de la Sede Central en el jirón Moquegua',
        ])->assertCreated();

        $imagen = $respuesta->json('data.imagen');
        $bytes = $this->guardada($imagen['id']);

        // Venía 300 × 200 «de costado»: queda 200 × 300, derecha.
        $this->assertSame([200, 300], [$imagen['ancho'], $imagen['alto']]);
        $this->assertSame([200, 300], array_slice(getimagesizefromstring($bytes), 0, 2));
        $this->assertSame('image/webp', getimagesizefromstring($bytes)['mime']);

        // Ni el EXIF, ni el modelo del celular, ni la posición.
        $this->assertStringNotContainsString('Exif', $bytes);
        $this->assertStringNotContainsString(ImagenesDePrueba::MARCA_DEL_CELULAR, $bytes);
        $this->assertStringNotContainsString('EXIF', $bytes);
    }

    public function test_se_guarda_con_nombre_nuevo_y_se_sirve_por_la_ruta_de_medios(): void
    {
        $imagen = $this->subir([
            'archivo' => ImagenesDePrueba::jpegDeCelular(300, 200, 1),
            'uso'     => 'noticia',
            'alt'     => 'Estudiantes en el patio durante la formación',
        ])->assertCreated()->json('data.imagen');

        $ruta = Imagen::find($imagen['id'])->ruta;
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.webp$/', $ruta);
        $this->assertStringNotContainsString('IMG_2034', $ruta);
        $this->assertSame("https://rrhh.cata.edu.pe/api/portal/medios/{$ruta}", $imagen['url']);

        $this->get("/api/portal/medios/{$ruta}")->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_un_recorte_conserva_su_transparencia(): void
    {
        $respuesta = $this->subir([
            'archivo' => ImagenesDePrueba::pngTransparente(500, 1000),
            'uso'     => 'recorte',
            'alt'     => 'Estudiante con el uniforme del colegio y su mochila',
        ])->assertCreated();

        $guardada = imagecreatefromstring($this->guardada($respuesta->json('data.imagen.id')));
        $alfaDeLaEsquina = (imagecolorat($guardada, 2, 2) >> 24) & 0x7F;

        $this->assertSame(127, $alfaDeLaEsquina, 'La esquina tenía que seguir siendo transparente.');
        $this->assertSame([], $respuesta->json('data.avisos'));
    }

    public function test_avisa_lo_que_no_impide_guardar(): void
    {
        // Chica, de otra proporción y sin fondo transparente, para un recorte.
        $avisos = $this->subir([
            'archivo' => ImagenesDePrueba::jpegDeCelular(300, 200, 1),
            'uso'     => 'recorte',
            'alt'     => 'Estudiante de secundaria',
        ])->assertCreated()->json('data.avisos');

        $this->assertCount(3, $avisos);
        $this->assertStringContainsString('más chica', $avisos[0]);
        $this->assertStringContainsString('proporción', $avisos[1]);
        $this->assertStringContainsString('transparente', $avisos[2]);

        // En un banner, la proporción se resuelve con el punto focal.
        $avisos = $this->subir([
            'archivo' => ImagenesDePrueba::jpegDeCelular(300, 200, 1),
            'uso'     => 'banner',
            'alt'     => 'Afiche: Matrículas abiertas 2027',
        ])->json('data.avisos');
        $this->assertStringContainsString('punto que elijas', implode(' ', $avisos));
    }

    public function test_una_foto_enorme_se_achica_y_se_comprime(): void
    {
        // Enorme en píxeles y pesada, pero lejos de los 10 MB: si no, lo que se prueba es el límite de subida.
        $archivo = ImagenesDePrueba::jpegRuidoso(3600, 2400);
        $this->assertLessThan(8 * 1024 * 1024, $archivo->getSize(), 'La imagen de prueba se acercó al límite de 10 MB.');
        $this->assertGreaterThan(2 * 1024 * 1024, $archivo->getSize(), 'La imagen de prueba ya no pesa lo suficiente.');

        $imagen = $this->subir([
            'archivo' => $archivo,
            'uso'     => 'contenido',
            'alt'     => 'Ruido de colores de prueba',
        ])->assertCreated()->json('data');

        $this->assertLessThanOrEqual(2560, max($imagen['imagen']['ancho'], $imagen['imagen']['alto']));

        // O quedó en el peso recomendado, o el aviso lo dice.
        $this->assertTrue(
            $imagen['imagen']['bytes'] <= 400 * 1024 || str_contains(implode(' ', $imagen['avisos']), 'KB'),
            'Pesa ' . $imagen['imagen']['bytes'] . ' bytes y no avisó.',
        );
    }

    // ───────── Lo que no entra ─────────

    public function test_solo_entran_imagenes_de_verdad(): void
    {
        $disfrazados = [
            'un PHP con nombre de foto' => ImagenesDePrueba::archivo('foto.jpg', '<?php system($_GET["c"]); ?>'),
            'un SVG con JavaScript'     => ImagenesDePrueba::archivo('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'un GIF'                    => ImagenesDePrueba::archivo('animado.gif', base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==')),
            'un JPEG roto'              => ImagenesDePrueba::archivo('roto.jpg', "\xFF\xD8\xFF\xE0" . str_repeat('x', 200)),
        ];

        foreach ($disfrazados as $que => $archivo) {
            $this->subir(['archivo' => $archivo, 'uso' => 'sede', 'alt' => 'Fachada del colegio'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('archivo');
        }

        $this->assertSame(0, Imagen::count());
        $this->assertSame([], Storage::disk('portal')->files());
    }

    public function test_el_texto_alternativo(): void
    {
        $foto = fn () => ImagenesDePrueba::jpegDeCelular(120, 80, 1);

        // Obligatorio, y no vale el nombre del archivo ni «foto».
        foreach ([null, '   ', 'foto', 'Imagen', 'IMG_2034.JPG', 'banner-final.png'] as $alt) {
            $this->subir(['archivo' => $foto(), 'uso' => 'sede', 'alt' => $alt])
                ->assertUnprocessable()->assertJsonValidationErrors('alt');
        }

        // Decorativa: alt vacío a propósito (el portal la oculta a los lectores de pantalla).
        $id = $this->subir(['archivo' => $foto(), 'uso' => 'contenido', 'decorativa' => '1', 'alt' => 'se ignora'])
            ->assertCreated()->json('data.imagen.id');
        $this->assertSame('', Imagen::find($id)->alt);

        $this->subir(['archivo' => $foto(), 'uso' => 'no-existe', 'alt' => 'Patio'])
            ->assertUnprocessable()->assertJsonValidationErrors('uso');
    }

    public function test_cambiar_el_texto_alternativo(): void
    {
        $id = $this->subir(['archivo' => ImagenesDePrueba::jpegDeCelular(120, 80, 1), 'uso' => 'sede', 'alt' => 'Fachada'])
            ->json('data.imagen.id');

        $this->patchJson(self::SUBIR . "/{$id}", ['alt' => 'Fachada de la Sede Central con el letrero del colegio'])
            ->assertOk()->assertJsonPath('data.imagen.alt', 'Fachada de la Sede Central con el letrero del colegio');
        $this->patchJson(self::SUBIR . "/{$id}", ['alt' => 'foto'])->assertUnprocessable();
    }

    public function test_los_usos_los_da_el_backend(): void
    {
        $this->getJson(self::SUBIR . '/uses')
            ->assertOk()
            ->assertJsonPath('data.banner.proporcion', [1900, 700])
            ->assertJsonPath('data.banner.foco', true)
            ->assertJsonPath('data.recorte.transparente', true);
    }

    public function test_rrhh_no_sube_imagenes(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        $this->subir(['archivo' => ImagenesDePrueba::jpegDeCelular(120, 80, 1), 'uso' => 'sede', 'alt' => 'Fachada'])
            ->assertForbidden();
        $this->assertSame([], Storage::disk('portal')->files());
    }

    // ───────── La limpieza ─────────

    public function test_la_limpieza_solo_borra_lo_suelto_y_viejo(): void
    {
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), 'tipico'))->importar();
        $subir = fn (string $alt) => Imagen::find($this->subir([
            'archivo' => ImagenesDePrueba::jpegDeCelular(120, 80, 1), 'uso' => 'sede', 'alt' => $alt,
        ])->json('data.imagen.id'));

        $enUnBanner = $subir('En un banner');
        $enUnaFicha = $subir('En una ficha');
        $suelta = $subir('Suelta');
        Banner::first()->update(['imagen_id' => $enUnBanner->id]);
        $contacto = Seccion::contenido('contacto');
        $contacto['figura'] = ['imagenId' => $enUnaFicha->id];
        Seccion::find('contacto')->update(['contenido' => $contacto]);
        Storage::disk('portal')->put('perdido.webp', 'x');

        // Recién subida: todavía nada se borra.
        $this->artisan('portal:limpiar-imagenes')->assertSuccessful();
        $this->assertNotNull(Imagen::find($suelta->id));

        $this->travel(25)->hours();
        $this->artisan('portal:limpiar-imagenes', ['--simular' => true])->assertSuccessful();
        $this->assertNotNull(Imagen::find($suelta->id));

        $this->artisan('portal:limpiar-imagenes')->assertSuccessful();

        $this->assertNull(Imagen::find($suelta->id));
        $this->assertFalse(Storage::disk('portal')->exists($suelta->ruta));
        $this->assertFalse(Storage::disk('portal')->exists('perdido.webp'));
        // Las usadas siguen, en la base y en el disco.
        foreach ([$enUnBanner, $enUnaFicha] as $usada) {
            $this->assertNotNull(Imagen::find($usada->id));
            $this->assertTrue(Storage::disk('portal')->exists($usada->ruta));
        }
        // Y las del contenido importado que siguen en uso, también.
        $this->getJson('/api/portal/v1/portada/banners')->assertOk();
        $this->getJson('/api/portal/v1/nosotros/paginas/emblema')->assertOk();
    }
}
