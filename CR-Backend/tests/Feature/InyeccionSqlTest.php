<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Inyección SQL: los ataques de siempre contra el buscador, los filtros y el
 * inicio de sesión de las listas del sistema.
 *
 * El sistema no arma SQL con lo que escribe el usuario: todo pasa por
 * Eloquent con parámetros enlazados, y las columnas por las que se filtra
 * salen de listas fijas en el código. Esto lo deja probado: ningún ataque
 * trae datos de más, ninguno revienta (500) y las tablas siguen ahí.
 */
class InyeccionSqlTest extends TestCase
{
    use RefreshDatabase;

    private const ATAQUES = [
        "' OR '1'='1",
        "' OR 1=1 --",
        '" OR "1"="1',
        "%' OR '%'='",
        "'; DROP TABLE empleados; --",
        "1; DELETE FROM users; --",
        "' UNION SELECT id, email, password FROM users --",
        "') OR ('a'='a",
        "\\'; SELECT SLEEP(5); --",
        "1' AND (SELECT COUNT(*) FROM users) > 0 --",
    ];

    /** Las listas con buscador que usan las pantallas. */
    private const LISTAS = [
        'employees', 'payrolls', 'payroll-runs', 'contracts', 'documents', 'users',
        'areas', 'positions', 'campuses', 'payment-concepts', 'contract-types', 'periods', 'audit-log',
    ];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->crearUsuario('admin');
        foreach (['42083098', '40000001', '40000002'] as $dni) {
            $e = $this->crearEmpleado(['dni' => $dni, 'sueldo_base' => 2000]);
            Planilla::create(['empleado_id' => $e->id, 'mes' => (int) now()->month, 'anio' => (int) now()->year, 'sueldo_base' => 2000, 'total' => 2000]);
        }
    }

    private function intacto(): void
    {
        foreach (['empleados', 'users', 'planilla', 'documentos'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "La tabla {$tabla} desapareció.");
        }
        $this->assertSame(3, Empleado::count());
    }

    public function test_el_buscador_de_cada_lista_no_se_deja_inyectar(): void
    {
        foreach (self::LISTAS as $lista) {
            foreach (self::ATAQUES as $ataque) {
                $respuesta = $this->actingAs($this->admin, 'sanctum')
                    ->getJson("/api/{$lista}?page=0&size=50&search=" . urlencode($ataque));

                $this->assertNotSame(500, $respuesta->status(), "{$lista} reventó con: {$ataque}");
                if ($respuesta->status() === 200 && is_array($respuesta->json('data.content'))) {
                    // «' OR '1'='1» buscado como texto no coincide con nada.
                    $this->assertSame(0, $respuesta->json('data.totalElements'), "{$lista} devolvió filas con: {$ataque}");
                }
            }
        }
        $this->intacto();
    }

    public function test_los_filtros_no_se_dejan_inyectar(): void
    {
        foreach (self::ATAQUES as $ataque) {
            foreach (['estado', 'area_id', 'cargo_id', 'sede_id', 'tipo_contrato_id', 'forma_pago', 'planilla', 'boleta', 'mes', 'anio'] as $filtro) {
                $respuesta = $this->actingAs($this->admin, 'sanctum')
                    ->getJson("/api/employees?page=0&size=50&{$filtro}=" . urlencode($ataque));
                $this->assertNotSame(500, $respuesta->status(), "employees?{$filtro} reventó con: {$ataque}");
                if ($respuesta->status() === 200) {
                    $this->assertSame(0, $respuesta->json('data.totalElements'), "employees?{$filtro} devolvió filas con: {$ataque}");
                }
            }
        }

        // Un filtro mandado como arreglo (?estado[]=…) tampoco revienta.
        $this->assertNotSame(500, $this->actingAs($this->admin, 'sanctum')->getJson('/api/employees?page=0&size=50&estado[]=activo')->status());
        $this->intacto();
    }

    public function test_el_inicio_de_sesion_no_se_deja_inyectar(): void
    {
        foreach (self::ATAQUES as $ataque) {
            foreach ([['email' => $ataque, 'password' => 'x'], ['email' => $this->admin->email, 'password' => $ataque]] as $datos) {
                $respuesta = $this->postJson('/api/login', $datos);
                $this->assertContains($respuesta->status(), [401, 422, 429], 'El login respondió ' . $respuesta->status() . " con: {$ataque}");
                $this->assertNull($respuesta->json('data.token'));
            }
        }
        $this->intacto();
    }
}
