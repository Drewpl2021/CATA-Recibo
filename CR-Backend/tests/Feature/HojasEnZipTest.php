<?php

namespace Tests\Feature;

use App\Models\PaymentConcept;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use App\Support\ConceptosDePago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Las hojas de CTS y de gratificación de muchos trabajadores, en un .zip, como las boletas. */
class HojasEnZipTest extends TestCase
{
    use RefreshDatabase;

    private function nombresDelZip($respuesta): array
    {
        $zip = new \ZipArchive();
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $lista = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $lista[] = $zip->getNameIndex($i);
        }
        $zip->close();
        sort($lista);

        return $lista;
    }

    /** Pide el enlace y baja el .zip sin sesión, como el navegador. */
    private function bajar(string $rutaEnlace, array $datos, $usuario)
    {
        $url = $this->actingAs($usuario, 'sanctum')->postJson($rutaEnlace, $datos)->assertOk()->json('data.url');
        $this->app['auth']->forgetGuards();

        return $this->get($url)->assertOk();
    }

    public function test_las_hojas_de_cts_del_deposito_en_un_zip_de_todos_o_de_los_marcados(): void
    {
        Storage::fake('local');
        $rrhh = $this->crearUsuario('rrhh');
        $ana = $this->crearEmpleado(['dni' => '40000011', 'apellido' => 'Quispe', 'nombre' => 'Ana', 'sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0]);
        $beto = $this->crearEmpleado(['dni' => '40000012', 'apellido' => 'Apaza', 'nombre' => 'Beto', 'sueldo_base' => 2500, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0]);

        // Sin calcular no hay hojas: se avisa, no se baja un .zip vacío.
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/zip/link', ['anio' => 2026, 'periodo' => 'mayo'])->assertNotFound();

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'mayo'])->assertOk();

        $this->assertSame(
            ['CTS_mayo_2026_40000011_Quispe_Ana.pdf', 'CTS_mayo_2026_40000012_Apaza_Beto.pdf'],
            $this->nombresDelZip($this->bajar('/api/cts/zip/link', ['anio' => 2026, 'periodo' => 'mayo'], $rrhh))
        );
        $this->assertSame(
            ['CTS_mayo_2026_40000012_Apaza_Beto.pdf'],
            $this->nombresDelZip($this->bajar('/api/cts/zip/link', ['anio' => 2026, 'periodo' => 'mayo', 'ids' => [$beto->id]], $rrhh))
        );

        // Un marcado sin hoja (practicante, sin CTS) no se pierde en silencio: lo dice NO_INCLUIDAS.txt.
        $practicante = $this->crearEmpleado(['dni' => '40000013', 'apellido' => 'Ramos', 'nombre' => 'Eva', 'tipo_contrato_id' => $this->idTipoContrato('Prácticas')]);
        $respuesta = $this->bajar('/api/cts/zip/link', ['anio' => 2026, 'periodo' => 'mayo', 'ids' => [$beto->id, $practicante->id]], $rrhh);
        $this->assertSame(['CTS_mayo_2026_40000012_Apaza_Beto.pdf', 'NO_INCLUIDAS.txt'], $this->nombresDelZip($respuesta));
        $zip = new \ZipArchive();
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Ramos Eva (DNI 40000013)', $zip->getFromName('NO_INCLUIDAS.txt'));
        $zip->close();
    }

    public function test_las_hojas_de_gratificacion_solo_de_quienes_les_corresponde(): void
    {
        Storage::fake('local');
        PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::GRATIFICACION], ['tipo' => 'bonificacion']);
        PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::BONIF_EXTRAORDINARIA], ['tipo' => 'bonificacion']);
        $rrhh = $this->crearUsuario('rrhh');
        $pagada = $this->crearEmpleado(['dni' => '40000021', 'apellido' => 'Mamani', 'nombre' => 'Rosa', 'sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0]);
        $porPagar = $this->crearEmpleado(['dni' => '40000022', 'apellido' => 'Condori', 'nombre' => 'Luis', 'sueldo_base' => 2000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0]);
        $this->crearEmpleado(['dni' => '40000023', 'tipo_contrato_id' => $this->idTipoContrato('Prácticas')]);

        $planilla = Planilla::create(['empleado_id' => $pagada->id, 'mes' => 7, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 0]);
        foreach ([ConceptosDePago::GRATIFICACION => 3000, ConceptosDePago::BONIF_EXTRAORDINARIA => 270] as $nombre => $monto) {
            PayrollDetalle::create(['planilla_id' => $planilla->id, 'payment_concept_id' => PaymentConcept::where('nombre', $nombre)->value('id'), 'monto_calculado' => $monto]);
        }
        // La de Rosa ya se entregó: va la del expediente.
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/bonuses/deliver', ['anio' => 2026, 'periodo' => 'julio', 'ids' => [$pagada->id]])->assertOk();

        // Las prácticas no tienen gratificación: no entran.
        $this->assertSame(
            ['Gratificacion_julio_2026_40000021_Mamani_Rosa.pdf', 'Gratificacion_julio_2026_40000022_Condori_Luis.pdf'],
            $this->nombresDelZip($this->bajar('/api/bonuses/zip/link', ['anio' => 2026, 'periodo' => 'julio'], $rrhh))
        );
    }

    public function test_el_enlace_solo_vale_firmado_y_para_quien_tiene_el_modulo(): void
    {
        Storage::fake('local');
        $rrhh = $this->crearUsuario('rrhh');
        $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0]);
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'mayo'])->assertOk();

        // Un trabajador no pide el enlace de las hojas de todos.
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')
            ->postJson('/api/cts/zip/link', ['anio' => 2026, 'periodo' => 'mayo'])->assertForbidden();
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')
            ->postJson('/api/bonuses/zip/link', ['anio' => 2026, 'periodo' => 'julio'])->assertForbidden();

        $url = $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/zip/link', ['anio' => 2026, 'periodo' => 'mayo'])->json('data.url');
        $this->app['auth']->forgetGuards();

        // Tocado (otro periodo) no vale.
        $this->get(str_replace('periodo=mayo', 'periodo=noviembre', $url))->assertForbidden();

        // Si a quien lo pidió le quitan la cuenta, el enlace deja de valer.
        $rrhh->update(['estado_registro' => 'inactivo']);
        $this->getJson($url)->assertForbidden();
    }
}
