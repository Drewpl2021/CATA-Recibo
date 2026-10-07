<?php

namespace Tests\Feature;

use App\Support\ConceptosDePago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** El modelo de conceptos trae «Detalle» al lado de cada «Otros Conceptos», y se reconoce al subirlo. */
class ModeloConceptosDetalleTest extends TestCase
{
    use RefreshDatabase;

    public function test_otros_conceptos_traen_su_columna_detalle(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        \App\Models\PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::OTROS_INGRESOS], ['tipo' => 'bonificacion']);
        \App\Models\PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::OTROS_DESCUENTOS], ['tipo' => 'descuento']);

        $respuesta = $this->actingAs($rrhh, 'sanctum')
            ->get('/api/concept-import/template?mes=' . now()->month . '&anio=' . now()->year)
            ->assertOk();

        $ruta = tempnam(sys_get_temp_dir(), 'modelo') . '.xlsx';
        file_put_contents($ruta, $respuesta->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? file_get_contents($respuesta->baseResponse->getFile()->getPathname())
            : $respuesta->streamedContent());
        $titulos = IOFactory::load($ruta)->getSheetByName('Conceptos')->rangeToArray('A1:ZZ1')[0];
        $titulos = array_values(array_filter($titulos, fn ($t) => $t !== null));

        foreach ([ConceptosDePago::OTROS_INGRESOS, ConceptosDePago::OTROS_DESCUENTOS] as $otros) {
            $i = array_search($otros, $titulos, true);
            $this->assertNotFalse($i, "Falta la columna {$otros}");
            $this->assertSame('Detalle', $titulos[$i + 1], "Al lado de {$otros} va «Detalle»");
        }

        // Y al subirlo, cada «Detalle» se reconoce como el de la columna de su izquierda.
        $reconocidas = $this->actingAs($rrhh, 'sanctum')
            ->postJson('/api/concept-import/recognize', ['columnas' => $titulos])
            ->assertOk()->json('data.columnas');
        foreach ($reconocidas as $i => $c) {
            if ($titulos[$i] === 'Detalle') {
                $this->assertSame('detalle', $c['accion']);
                $this->assertSame($i - 1, $c['de_columna']);
            }
        }
    }
}
