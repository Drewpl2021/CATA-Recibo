<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\User;
use App\Traits\CalculaConceptosPlanilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorParaCuentas
{
    use CalculaConceptosPlanilla;

    public function __call(string $metodo, array $args)
    {
        return $this->$metodo(...$args);
    }
}

/**
 * RR.HH. lleva al personal, pero no puede tocar la cuenta de un
 * Administrador: cambiarle el correo y pedir "olvidé mi contraseña" era
 * entrar como Administrador, y darlo de baja era dejarlo fuera.
 */
class CuentasProtegidasTest extends TestCase
{
    use RefreshDatabase;

    /** Un trabajador que además es el Administrador del sistema. */
    private function empleadoAdministrador(): Empleado
    {
        $empleado = $this->crearEmpleado();
        $cuenta = $this->crearUsuario('admin', ['email' => 'jefe@colegio.test']);
        $cuenta->forceFill(['empleado_id' => $empleado->id])->save();

        return $empleado->fresh();
    }

    public function test_rrhh_no_le_cambia_el_correo_a_un_administrador(): void
    {
        $jefe = $this->empleadoAdministrador();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->putJson("/api/employees/{$jefe->id}", ['email' => 'intruso@colegio.test'])
            ->assertForbidden();

        $this->assertSame('jefe@colegio.test', $jefe->usuario->fresh()->email);
    }

    public function test_rrhh_no_da_de_baja_a_un_administrador(): void
    {
        $jefe = $this->empleadoAdministrador();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->deleteJson("/api/employees/{$jefe->id}")
            ->assertForbidden();

        $this->assertSame('activo', $jefe->fresh()->estado);
    }

    public function test_el_administrador_si_puede(): void
    {
        $jefe = $this->empleadoAdministrador();

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->putJson("/api/employees/{$jefe->id}", ['email' => 'nuevo@colegio.test'])
            ->assertOk();

        $this->assertSame('nuevo@colegio.test', $jefe->usuario->fresh()->email);
    }

    public function test_rrhh_si_le_cambia_el_correo_a_un_trabajador(): void
    {
        $empleado = $this->crearEmpleado();
        $cuenta = $this->crearUsuario('empleado');
        $cuenta->forceFill(['empleado_id' => $empleado->id])->save();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->putJson("/api/employees/{$empleado->id}", ['email' => 'correo.nuevo@colegio.test'])
            ->assertOk();

        $this->assertSame('correo.nuevo@colegio.test', $cuenta->fresh()->email);
    }

    /**
     * LIMACHI MAMANI: activa, cobrando en setiembre, pero con "fin de
     * contrato" 13/04 en su ficha. La 5ta no puede darla por terminada.
     */
    public function test_la_5ta_no_corta_a_quien_sigue_activo_con_una_fecha_de_fin_vencida(): void
    {
        $motor = new MotorParaCuentas();
        $vencida = $this->crearEmpleado(['fecha_ingreso' => '2026-03-02', 'fecha_cese' => '2026-04-13', 'sueldo_base' => 8000,
            'tipo_contrato_id' => $this->idTipoContrato('Contratado')]);
        $sinFecha = $this->crearEmpleado(['fecha_ingreso' => '2026-03-02', 'fecha_cese' => '2026-12-31', 'sueldo_base' => 8000,
            'tipo_contrato_id' => $this->idTipoContrato('Contratado')]);

        $this->assertGreaterThan(0, $motor->calcularRenta5taCategoria($vencida, 8000, 0, 9, 2026));
        $this->assertEqualsWithDelta(
            $motor->calcularRenta5taCategoria($sinFecha, 8000, 0, 9, 2026),
            $motor->calcularRenta5taCategoria($vencida, 8000, 0, 9, 2026),
            0.000001
        );
    }
}
