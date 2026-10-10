<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Portal\Banner;
use App\Models\Portal\Ciclo;
use App\Models\Portal\Cifra;
use App\Models\Portal\Docente;
use App\Models\Portal\DocenteNivel;
use App\Models\Portal\Imagen;
use App\Models\Portal\Logro;
use App\Models\Portal\Nivel;
use App\Models\Portal\NivelPropuesta;
use App\Models\Portal\Noticia;
use App\Models\Portal\Pagina;
use App\Models\Portal\Proyecto;
use App\Models\Portal\RedSocial;
use App\Models\Portal\Seccion;
use App\Models\Portal\Sede;
use App\Models\Portal\Vacante;
use App\Support\Portal\ImagenesEnJson;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Las tablas del portal y el importador de los ejemplos del contrato: que
 * cada escenario entra completo y sin perder nada, que se puede repetir,
 * que una carga que falla no deja nada a medias, y que el comando tiene
 * freno.
 *
 * Lo que sale por la API (y su ida y vuelta exacta contra los ejemplos) lo
 * prueban los tests de cada endpoint.
 */
class PortalImportadorTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    public static function escenarios(): array
    {
        return array_combine(ImportadorDeEjemplos::ESCENARIOS, array_map(fn ($e) => [$e], ImportadorDeEjemplos::ESCENARIOS));
    }

    private function importar(string $escenario = 'tipico', ?string $ruta = null): array
    {
        return (new ImportadorDeEjemplos($ruta ?? base_path('tests/Fixtures/portal/mock'), $escenario))->importar();
    }

    #[DataProvider('escenarios')]
    public function test_cada_escenario_entra_completo(string $escenario): void
    {
        $this->importar($escenario);
        $e = fn (string $archivo) => $this->ejemplo($escenario, "{$archivo}.json")['data'];

        $this->assertSame(count($e('sitio')['redes']), RedSocial::count());
        $this->assertSame(count($e('banners')), Banner::count());
        $this->assertSame(count($e('cifras')), Cifra::de('portada')->count());
        $this->assertSame(count($e('logros')['cifras']), Cifra::de('logros')->count());
        $this->assertSame(count($e('propuesta-pilares')['cifras']), Cifra::de('propuesta')->count());
        $this->assertSame(count($e('niveles')['items']), Nivel::count());
        $this->assertSame(count($e('propuesta-niveles')), NivelPropuesta::count());
        $this->assertSame(count($e('sedes')), Sede::count());
        $this->assertSame(count($e('noticias')), Noticia::count());
        $this->assertSame(count($e('matricula-vacantes')['niveles']), Vacante::count());
        $this->assertSame(count($e('proyectos')['proyectos']), Proyecto::count());
        $this->assertSame(count($e('docentes')['niveles']), DocenteNivel::count());
        $this->assertSame(count($e('docentes')['docentes']), Docente::count());
        $this->assertSame(count($e('logros')['logros']), Logro::count());
        $this->assertSame(
            collect($e('propuesta-academia')['universidades'])->sum(fn ($u) => count($u['ciclos'])),
            Ciclo::count(),
        );

        // Las 14 fichas de una sola vez, en todos los escenarios.
        $this->assertSame(14, Seccion::count());

        // Las subpáginas de Nosotros que el portal puede abrir; las del índice, con su frase.
        $indice = array_column($e('nosotros')['paginas'], 'resumen', 'slug');
        foreach ($indice as $slug => $resumen) {
            $this->assertSame($resumen, Pagina::where('slug', $slug)->value('resumen'), $slug);
        }
        $this->assertSame(count($indice), Pagina::whereNotNull('resumen')->count());
    }

    #[DataProvider('escenarios')]
    public function test_las_fichas_y_los_bloques_vuelven_tal_cual_con_sus_imagenes(string $escenario): void
    {
        $this->importar($escenario);

        // Lo que se guarda como ficha completa vuelve idéntico, imágenes incluidas.
        foreach (['quienes-somos', 'propuesta-cabecera', 'matricula-cabecera', 'contacto', 'solicitud'] as $archivo) {
            $this->assertSame(
                $this->ejemplo($escenario, "{$archivo}.json")['data'],
                ImagenesEnJson::resolver(Seccion::contenido($archivo)),
                $archivo,
            );
        }

        foreach (Pagina::all() as $pagina) {
            $this->assertSame(
                $this->ejemplo($escenario, "nosotros-pagina--{$pagina->slug}.json")['data']['bloques'],
                ImagenesEnJson::resolver($pagina->bloques),
                $pagina->slug,
            );
        }
    }

    public function test_cada_imagen_conserva_su_alt_y_su_tamano(): void
    {
        $this->importar('alt-vacio');
        $banner = $this->ejemplo('alt-vacio', 'banners.json')['data'][0];

        $this->assertSame($banner['imagen'], Banner::where('clave', $banner['id'])->first()->imagen->aContrato());

        // El alt vacío (imagen decorativa) se guarda vacío, no como null.
        $this->assertTrue(Imagen::where('alt', '')->exists());
    }

    public function test_repetirlo_reemplaza_y_no_duplica(): void
    {
        $primera = $this->importar('largo');
        $segunda = $this->importar('largo');

        $this->assertSame($primera, $segunda);

        $this->importar('vacio');
        $this->assertSame(0, Banner::count());
        $this->assertSame(0, Noticia::count());
    }

    public function test_si_algo_falla_no_deja_nada_a_medias(): void
    {
        $this->importar('tipico');
        $antes = Banner::pluck('clave')->all();

        // Un escenario roto: un proyecto que apunta a una categoría que no existe.
        $ruta = storage_path('framework/testing/portal-roto');
        File::deleteDirectory($ruta);
        File::copyDirectory(base_path('tests/Fixtures/portal/mock/tipico'), "{$ruta}/tipico");
        $proyectos = json_decode(File::get("{$ruta}/tipico/proyectos.json"), true);
        $proyectos['data']['categorias'] = [];
        File::put("{$ruta}/tipico/proyectos.json", json_encode($proyectos));

        try {
            $this->importar('tipico', $ruta);
            $this->fail('Tenía que fallar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('categoría', $e->getMessage());
        } finally {
            File::deleteDirectory($ruta);
        }

        $this->assertSame($antes, Banner::pluck('clave')->all());
        $this->assertSame(14, Seccion::count());
    }

    public function test_el_comando_tiene_freno(): void
    {
        $this->artisan('portal:importar', ['escenario' => 'corto'])->assertSuccessful();

        // Con contenido ya cargado, no lo pisa sin --force.
        $this->artisan('portal:importar', ['escenario' => 'tipico'])->assertFailed();
        $this->assertSame(count($this->ejemplo('corto', 'banners.json')['data']), Banner::count());

        $this->artisan('portal:importar', ['escenario' => 'tipico', '--force' => true])->assertSuccessful();
        $this->assertSame(count($this->ejemplo('tipico', 'banners.json')['data']), Banner::count());

        $this->artisan('portal:importar', ['escenario' => 'no-existe', '--force' => true])->assertFailed();
    }

    public function test_en_produccion_no_corre_sin_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('portal:importar')->assertFailed();
        $this->assertSame(0, Banner::count());
    }

    public function test_los_cambios_desde_el_panel_quedan_en_la_auditoria(): void
    {
        $this->importar('tipico');
        $this->actingAs($this->crearUsuario('admin'));

        Banner::first()->update(['titulo' => 'Matrículas 2027 abiertas']);
        Seccion::find('contacto')->update(['contenido' => ['titulo' => 'Escríbenos'] + Seccion::contenido('contacto')]);

        $this->assertTrue(Auditoria::where('entidad', 'portal: banner')->where('accion', 'cambió')->exists());
        $this->assertSame(
            'Cambió contenido de la cabecera de Contacto',
            Auditoria::where('entidad', 'portal: sección')->value('descripcion'),
        );

        // La carga del importador no tiene a nadie detrás: no llena la auditoría.
        $this->assertSame(2, Auditoria::count());
    }
}
