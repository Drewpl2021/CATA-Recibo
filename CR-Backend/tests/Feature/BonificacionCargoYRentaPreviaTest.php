<?php

namespace Tests\Feature;

use App\Models\PaymentConcept;
use App\Models\Planilla;
use App\Models\RentaQuintaPrevia;
use App\Support\ConceptosDePago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * La Bonificación por Cargo sale de la ficha (como el sueldo), y enero y
 * febrero de antes del sistema se suben con el Excel de 5ta de RR.HH.
 */
class BonificacionCargoYRentaPreviaTest extends TestCase
{
    use RefreshDatabase;

    private function linea(Planilla $planilla, string $concepto): ?float
    {
        $monto = $planilla->payrollDetalles()->whereHas('paymentConcept', fn ($q) => $q->where('nombre', $concepto))->value('monto_calculado');

        return $monto === null ? null : (float) $monto;
    }

    public function test_la_planilla_trae_la_bonificacion_por_cargo_de_la_ficha(): void
    {
        foreach ([ConceptosDePago::BONIFICACION_CARGO => 'bonificacion', ConceptosDePago::ONP => 'descuento'] as $nombre => $tipo) {
            PaymentConcept::firstOrCreate(['nombre' => $nombre], ['tipo' => $tipo]);
        }
        $empleado = $this->crearEmpleado(['sueldo_base' => 2000, 'bonificacion_cargo' => 300]);

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->postJson('/api/payrolls', ['empleado_id' => $empleado->id, 'mes' => 10, 'anio' => 2026])
            ->assertSuccessful();

        $planilla = Planilla::where('empleado_id', $empleado->id)->firstOrFail();
        $this->assertSame(300.0, $this->linea($planilla, ConceptosDePago::BONIFICACION_CARGO));
        // La ONP va sobre sueldo + bonificación: 13% de 2 300.
        $this->assertSame(299.0, $this->linea($planilla, ConceptosDePago::ONP));
    }

    public function test_quien_entra_a_mitad_de_mes_cobra_la_bonificacion_por_sus_dias(): void
    {
        PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::BONIFICACION_CARGO], ['tipo' => 'bonificacion']);
        // Jueves 29 de octubre: 2 de 22 días hábiles.
        $empleado = $this->crearEmpleado(['fecha_ingreso' => '2026-10-29', 'sueldo_base' => 2200, 'bonificacion_cargo' => 440]);

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->postJson('/api/payrolls', ['empleado_id' => $empleado->id, 'mes' => 10, 'anio' => 2026])
            ->assertSuccessful();

        $planilla = Planilla::where('empleado_id', $empleado->id)->firstOrFail();
        $this->assertSame(40.0, $this->linea($planilla, ConceptosDePago::BONIFICACION_CARGO));
    }

    public function test_el_admin_sube_el_excel_de_5ta_y_quedan_enero_y_febrero(): void
    {
        $apaza = $this->crearEmpleado(['dni' => '46052135', 'sueldo_base' => 4046.50]);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet()->setTitle('Hoja1');
        $hoja->fromArray([
            ['MODULAR', 'SECUENCIAL', 'PATERNO', 'Enero', 'Febrero', 'IRQ DESCONTADO ENERO', 'IRQ DESCONTADO FEBRERO'],
            ['46052135', 'Plazo indeterminado', 'APAZA SOSA, Dino Alexis', 3852.5, 3852.5, 137.59, 137.59],
            ['99999999', 'CONTRATADO', 'ALGUIEN QUE NO ESTA', 1000, null, null, null],
        ]);
        $ruta = tempnam(sys_get_temp_dir(), 'renta') . '.xlsx';
        (new Xlsx($libro))->save($ruta);

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->post('/api/renta-5ta/previous', [
                'anio'    => 2026,
                'archivo' => new UploadedFile($ruta, 'Calculo 5ta.xlsx', null, null, true),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.con_datos', 1)
            ->assertJsonPath('data.no_encontrados.0', 'ALGUIEN QUE NO ESTA (DNI 99999999)');

        $this->assertSame(2, RentaQuintaPrevia::where('empleado_id', $apaza->id)->count());
        $this->assertSame(275.18, round((float) RentaQuintaPrevia::sum('retencion'), 2));

        // RR.HH. también lo ve; un trabajador no.
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson('/api/renta-5ta/previous?anio=2026')->assertOk();
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')
            ->getJson('/api/renta-5ta/previous?anio=2026')->assertForbidden();
    }
}
