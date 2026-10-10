<?php

namespace Tests\Feature;

use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Cada endpoint del portal, en cada escenario de los ejemplos del contrato:
 * se carga el escenario con el importador, se pide el endpoint y la
 * respuesta tiene que (1) cumplir el JSON Schema y (2) ser igual al ejemplo.
 *
 * Lo propio de cada endpoint (404, filtros, paginación, borradores) va en
 * PortalReglasTest.
 */
class PortalEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    /** Ruta del contrato → archivo del ejemplo. */
    private const ENDPOINTS = [
        '/v1/sitio'                         => 'sitio',
        '/v1/portada/banners'               => 'banners',
        '/v1/quienes-somos'                 => 'quienes-somos',
        '/v1/niveles'                       => 'niveles',
        '/v1/sedes'                         => 'sedes',
        '/v1/noticias'                      => 'noticias',
        '/v1/grados'                        => 'grados',
        '/v1/propuesta-educativa/cabecera'  => 'propuesta-cabecera',
        '/v1/portada/cifras'                => 'cifras',
        '/v1/matricula/cabecera'            => 'matricula-cabecera',
        '/v1/matricula/procesos'            => 'matricula-procesos',
        '/v1/matricula/vacantes'            => 'matricula-vacantes',
        '/v1/matricula/fechas'              => 'matricula-fechas',
        '/v1/matricula/preguntas'           => 'matricula-preguntas',
        '/v1/solicitud'                     => 'solicitud',
        '/v1/contacto'                      => 'contacto',
        '/v1/docentes'                      => 'docentes',
        '/v1/logros'                        => 'logros',
        '/v1/propuesta-educativa/pilares'     => 'propuesta-pilares',
        '/v1/propuesta-educativa/niveles'     => 'propuesta-niveles',
        '/v1/propuesta-educativa/academia'    => 'propuesta-academia',
        '/v1/propuesta-educativa/plataformas' => 'propuesta-plataformas',
        '/v1/proyectos'                     => 'proyectos',
        '/v1/nosotros'                      => 'nosotros',
    ];

    /**
     * Cada caso: [ruta que se pide, ruta del esquema, archivo del ejemplo, escenario].
     * Los detalles se piden uno por uno: cada proyecto de la lista del escenario.
     */
    public static function casos(): iterable
    {
        foreach (ImportadorDeEjemplos::ESCENARIOS as $escenario) {
            foreach (self::ENDPOINTS as $ruta => $archivo) {
                yield "{$ruta} · {$escenario}" => [$ruta, $ruta, $archivo, $escenario];
            }

            foreach (self::datosDelEjemplo($escenario, 'proyectos')['proyectos'] as $proyecto) {
                yield "/v1/proyectos/{$proyecto['slug']} · {$escenario}" => [
                    "/v1/proyectos/{$proyecto['slug']}", '/v1/proyectos/{slug}', "proyecto--{$proyecto['slug']}", $escenario,
                ];
            }

            // Toda subpágina de Nosotros que el portal puede abrir en el escenario.
            foreach (self::subpaginas($escenario) as $slug) {
                yield "/v1/nosotros/paginas/{$slug} · {$escenario}" => [
                    "/v1/nosotros/paginas/{$slug}", '/v1/nosotros/paginas/{slug}', "nosotros-pagina--{$slug}", $escenario,
                ];
            }
        }
    }

    /** Los slugs con archivo en el escenario o en `tipico`, como los resuelve el portal. */
    private static function subpaginas(string $escenario): array
    {
        $raiz = dirname(__DIR__) . '/Fixtures/portal/mock';
        $archivos = array_merge(glob("{$raiz}/{$escenario}/nosotros-pagina--*.json"), glob("{$raiz}/tipico/nosotros-pagina--*.json"));
        $slugs = array_unique(array_map(fn ($a) => substr(basename($a, '.json'), strlen('nosotros-pagina--')), $archivos));
        sort($slugs);

        return $slugs;
    }

    /** Para el proveedor de casos, que corre antes de que exista la aplicación. */
    private static function datosDelEjemplo(string $escenario, string $archivo): array
    {
        $raiz = dirname(__DIR__) . '/Fixtures/portal/mock';
        $ruta = is_file("{$raiz}/{$escenario}/{$archivo}.json") ? "{$raiz}/{$escenario}/{$archivo}.json" : "{$raiz}/tipico/{$archivo}.json";

        return json_decode(file_get_contents($ruta), true)['data'];
    }

    #[DataProvider('casos')]
    public function test_cumple_el_contrato_y_sale_igual_al_ejemplo(string $ruta, string $esquema, string $archivo, string $escenario): void
    {
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), $escenario))->importar();

        $respuesta = $this->getJson('/api/portal' . $ruta)->assertOk();

        $this->assertCumpleElContrato($esquema, $respuesta->getContent());

        $this->assertIgualAlEjemplo(
            $this->ejemplo($escenario, "{$archivo}.json"),
            $respuesta->json(),
            "GET {$ruta} en «{$escenario}»",
        );
    }
}
