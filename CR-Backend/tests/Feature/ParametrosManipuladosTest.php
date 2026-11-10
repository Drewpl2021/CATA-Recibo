<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lo que alguien puede escribir a mano en la dirección o mandar directo a la
 * API (?anio=2026' OR '1'='1, ?periodo=<script>…). La pantalla filtra lo
 * que lee de la dirección, pero la que manda es la API: con valores
 * manipulados tiene que contestar «dato no válido» (422) o ignorarlos, nunca
 * reventar (500) ni devolver datos de más. Y sin el módulo, ni entra.
 */
class ParametrosManipuladosTest extends TestCase
{
    use RefreshDatabase;

    /** Los listados que reciben mes, año, periodo, búsqueda u orden por la dirección. */
    private const RUTAS = [
        '/api/cts', '/api/bonuses', '/api/payrolls', '/api/payroll-runs', '/api/income-tax',
        '/api/employees', '/api/documents', '/api/employee-files', '/api/contracts', '/api/vacations',
        '/api/payslips/signed/summary', '/api/payslips/delivery-record', '/api/audit-log', '/api/users',
        '/api/dashboard', '/api/payrolls/renta-5ta', '/api/my-documents', '/api/my-payroll',
    ];

    private const MALICIOSOS = [
        ['anio' => "2026' OR '1'='1", 'mes' => '11; DROP TABLE empleados', 'periodo' => '<script>alert(1)</script>'],
        ['anio' => '99999999999999999999', 'mes' => '-1', 'periodo' => 'noviembre\' --'],
        ['anio' => ['2026'], 'mes' => ['x' => 1], 'periodo' => ['mayo']],
        ['search' => "' OR 1=1 --", 'sort' => 'id;DROP TABLE users', 'orden' => 'apellido desc, (select 1)', 'page' => '-5', 'size' => '1000000'],
        ['search' => '%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%', 'estado' => "activo' OR '1'='1", 'filtro' => '../../.env'],
    ];

    public function test_con_valores_manipulados_la_api_no_revienta(): void
    {
        Storage::fake('local');
        $admin = $this->crearUsuario('admin');

        foreach (self::RUTAS as $ruta) {
            foreach (self::MALICIOSOS as $parametros) {
                $respuesta = $this->actingAs($admin, 'sanctum')->getJson($ruta . '?' . http_build_query($parametros));
                $this->assertLessThan(500, $respuesta->status(), "{$ruta} reventó con " . json_encode($parametros) . ': ' . mb_substr($respuesta->getContent(), 0, 300));
                // Lo escrito no vuelve tal cual en la respuesta (no se puede inyectar HTML por ahí).
                $this->assertStringNotContainsString('<script>', $respuesta->getContent(), "{$ruta} devolvió el <script> tal cual");
            }
        }
    }

    public function test_un_trabajador_no_entra_a_los_modulos_de_rrhh_escribiendo_la_direccion(): void
    {
        $trabajador = $this->crearUsuario('empleado');
        $deRrhh = ['/api/cts?anio=2026&periodo=noviembre', '/api/bonuses?anio=2026&periodo=diciembre', '/api/payrolls',
            '/api/income-tax?anio=2026', '/api/employees', '/api/payslips/signed/summary?mes=11&anio=2026',
            '/api/payslips/delivery-record?mes=11&anio=2026', '/api/audit-log', '/api/users', '/api/employee-files'];

        foreach ($deRrhh as $ruta) {
            $this->actingAs($trabajador, 'sanctum')->getJson($ruta)->assertForbidden();
        }
        foreach (['/api/cts/calculate', '/api/cts/deposit', '/api/payslips/void', '/api/payslips/signed/sign-here'] as $ruta) {
            $this->actingAs($trabajador, 'sanctum')->postJson($ruta, ['anio' => 2026, 'periodo' => 'noviembre', 'mes' => 11, 'ids' => ['x']])
                ->assertForbidden();
        }
    }

    public function test_sin_iniciar_sesion_no_entra_nadie(): void
    {
        foreach (['/api/cts?anio=2026&periodo=noviembre', '/api/employees', '/api/payrolls', '/api/my-documents'] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
    }
}
