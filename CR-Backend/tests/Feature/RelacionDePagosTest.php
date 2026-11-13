<?php

namespace Tests\Feature;

use App\Models\CtsLiquidacion;
use App\Models\Planilla;
use App\Support\Pagos\RelacionDePagos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La relación de pagos: por banco o caja, con lo que no va por abono y lo
 * que hay que revisar antes de pagar. Sueldos del mes y depósito de CTS.
 */
class RelacionDePagosTest extends TestCase
{
    use RefreshDatabase;

    public function test_agrupa_por_entidad_y_separa_lo_que_no_se_abona(): void
    {
        $r = RelacionDePagos::armar([
            ['dni' => '1', 'nombre' => 'Bravo Ana', 'entidad' => 'Caja Arequipa', 'cuenta' => '111', 'monto' => 2500.555],
            ['dni' => '2', 'nombre' => 'Alfa Luis', 'entidad' => 'caja arequipa ', 'cuenta' => '222', 'monto' => 1800],
            ['dni' => '3', 'nombre' => 'Celis Eva', 'entidad' => 'BCP', 'cuenta' => '222', 'monto' => 1000],
            ['dni' => '4', 'nombre' => 'Dávila Rosa', 'entidad' => 'BCP', 'cuenta' => '', 'monto' => 900],
            ['dni' => '5', 'nombre' => 'Elías Juan', 'entidad' => 'BCP', 'cuenta' => '555', 'forma_pago' => 'efectivo', 'monto' => 700],
            ['dni' => '6', 'nombre' => 'Flores Ana', 'entidad' => 'BCP', 'cuenta' => '666', 'monto' => 0],
            ['dni' => '7', 'nombre' => 'Gómez Leo', 'entidad' => '', 'cuenta' => '777', 'monto' => 300],
            ['dni' => '8', 'nombre' => 'Huanca Pía', 'entidad' => 'BCP', 'cuenta' => '1-11', 'monto' => 100],
            ['dni' => '9', 'nombre' => 'Ibarra Sol', 'entidad' => 'BCP', 'cuenta' => '111', 'monto' => 100],
        ]);

        // La entidad se junta sin importar mayúsculas ni espacios; ordenadas por nombre.
        $this->assertSame(['(SIN ENTIDAD)', 'BCP', 'CAJA AREQUIPA'], array_keys($r['grupos']));
        $this->assertSame(['Alfa Luis', 'Bravo Ana'], array_column($r['grupos']['CAJA AREQUIPA']['filas'], 'nombre'));
        // Cada monto redondeado a céntimos y el total es la suma de esos.
        $this->assertSame(4300.56, $r['grupos']['CAJA AREQUIPA']['total']);
        $this->assertSame(4300.56 + 1200.0 + 300.0, $r['total_abonar']);

        $this->assertSame(['Dávila Rosa', 'Elías Juan', 'Flores Ana'], array_column($r['sin_abono'], 'nombre'));
        $this->assertSame(1600.0, $r['total_otro_medio']);
        // La misma cuenta en dos personas del mismo banco se avisa (con o sin guiones); en bancos distintos, no.
        $this->assertTrue(collect($r['alertas'])->contains(fn ($a) => str_contains($a, 'Huanca Pía, Ibarra Sol')));
        $this->assertFalse(collect($r['alertas'])->contains(fn ($a) => str_contains($a, 'Alfa Luis')));
        $this->assertTrue(collect($r['alertas'])->contains(fn ($a) => str_contains($a, 'Gómez Leo')));
    }

    public function test_la_relacion_de_abonos_del_mes_en_pdf_y_excel(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $e = $this->crearEmpleado(['entidad_financiera' => 'Caja Arequipa', 'numero_cuenta' => '00123456789', 'forma_pago' => 'banco']);
        Planilla::create(['empleado_id' => $e->id, 'mes' => 10, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 2611.4]);

        // Sin mes no: mezclar meses daría un total que nunca se paga junto.
        $this->actingAs($rrhh, 'sanctum')->getJson('/api/payrolls/payment-report')->assertStatus(422);

        $this->actingAs($rrhh, 'sanctum')->get('/api/payrolls/payment-report?mes=10&anio=2026')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($rrhh, 'sanctum')->get('/api/payrolls/payment-report?mes=10&anio=2026&formato=xlsx')
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Un trabajador no la baja.
        $trabajador = $this->crearUsuario('empleado');
        $this->actingAs($trabajador, 'sanctum')->get('/api/payrolls/payment-report?mes=10&anio=2026')->assertForbidden();
    }

    public function test_la_relacion_de_depositos_de_cts_va_a_la_cuenta_cts_y_no_al_cesado(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $con = $this->crearEmpleado(['apellido' => 'Activa', 'numero_cuenta' => '999', 'cts_entidad_financiera' => 'Caja Arequipa', 'cts_numero_cuenta' => '555666']);
        $sin = $this->crearEmpleado(['apellido' => 'Sincuenta', 'numero_cuenta' => '888']);
        $cesado = $this->crearEmpleado(['apellido' => 'Cesada', 'estado' => 'inactivo', 'fecha_cese' => '2026-09-30', 'cts_numero_cuenta' => '444']);
        foreach ([$con, $sin, $cesado] as $e) {
            CtsLiquidacion::create(['empleado_id' => $e->id, 'anio' => 2026, 'periodo' => 'noviembre', 'desde' => '2026-05-01', 'hasta' => '2026-10-31', 'monto' => 1500, 'meses' => 6, 'dias' => 0, 'estado' => 'calculada']);
        }

        $filas = fn () => RelacionDePagos::armar(CtsLiquidacion::with('empleado')->get()->map(fn ($l) => [
            'dni' => $l->empleado->dni, 'nombre' => $l->empleado->apellido, 'entidad' => $l->empleado->cts_entidad_financiera,
            'cuenta' => $l->empleado->cts_numero_cuenta, 'monto' => (float) $l->monto, 'cesado' => $l->empleado->estado === 'inactivo',
        ])->all(), esCts: true);
        $r = $filas();
        // Solo la que tiene cuenta CTS; nunca la cuenta del sueldo.
        $this->assertSame(['555666'], array_column($r['grupos']['CAJA AREQUIPA']['filas'], 'cuenta'));
        $motivos = array_column($r['sin_abono'], 'motivo', 'nombre');
        $this->assertStringContainsString('liquidación', $motivos['Cesada']);
        $this->assertStringContainsString('cuenta CTS', $motivos['Sincuenta']);

        $this->actingAs($rrhh, 'sanctum')->get('/api/cts/deposit-report?anio=2026&periodo=noviembre')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($rrhh, 'sanctum')->get('/api/cts/deposit-report?anio=2026&periodo=mayo')->assertStatus(422);
    }
}
