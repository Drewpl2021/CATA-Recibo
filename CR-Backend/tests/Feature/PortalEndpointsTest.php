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
    ];

    public static function casos(): iterable
    {
        foreach (self::ENDPOINTS as $ruta => $archivo) {
            foreach (ImportadorDeEjemplos::ESCENARIOS as $escenario) {
                yield "{$ruta} · {$escenario}" => [$ruta, $archivo, $escenario];
            }
        }
    }

    #[DataProvider('casos')]
    public function test_cumple_el_contrato_y_sale_igual_al_ejemplo(string $ruta, string $archivo, string $escenario): void
    {
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), $escenario))->importar();

        $respuesta = $this->getJson('/api/portal' . $ruta)->assertOk();

        $this->assertCumpleElContrato($ruta, $respuesta->getContent());

        $esperado = $this->ejemplo($escenario, "{$archivo}.json");

        if ($archivo === 'noticias' && $escenario === 'largo') {
            // El ejemplo largo es la primera página de 23 noticias, pero trae
            // solo esas 6: el importador no puede inventar las otras 17.
            $this->assertSame(23, $esperado['meta']['total']);
            $esperado['meta']['total'] = count($esperado['data']);
        }

        $this->assertIgualAlEjemplo($esperado, $respuesta->json(), "GET {$ruta} en «{$escenario}»");
    }
}
