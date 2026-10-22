<?php

namespace Tests\Feature;

use App\Models\Modulo;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Los permisos son los módulos de cada rol (App\Support\Permisos): lo que se
 * marca en Roles manda en el menú Y en el servidor. Antes las rutas pedían
 * el NOMBRE del rol, y un rol nuevo quedaba fuera aunque tuviera el módulo.
 */
class PermisosPorModuloTest extends TestCase
{
    use RefreshDatabase;

    /** Un rol nuevo con esos módulos, como se arma desde la pantalla de Roles. */
    private function rolCon(string $nombre, array $rutas): Rol
    {
        $this->sembrarRolesYModulos();
        $rol = Rol::create(['nombre' => $nombre, 'descripcion' => $nombre]);
        $rol->modulos()->attach(Modulo::whereIn('ruta', $rutas)->pluck('id'));
        \App\Support\Permisos::refrescar();

        return $rol;
    }

    public function test_un_rol_nuevo_entra_a_los_modulos_que_tiene_y_a_ninguno_mas(): void
    {
        $this->rolCon('contabilidad', ['/planillas', '/cts']);
        $contador = $this->crearUsuario('contabilidad');

        $this->actingAs($contador, 'sanctum')->getJson('/api/payroll-runs')->assertOk();
        $this->actingAs($contador, 'sanctum')->getJson('/api/cts')->assertOk();
        // Los catálogos que leen sus pantallas, también.
        $this->actingAs($contador, 'sanctum')->getJson('/api/areas')->assertOk();

        $this->actingAs($contador, 'sanctum')->getJson('/api/bonuses')->assertForbidden();
        $this->actingAs($contador, 'sanctum')->getJson('/api/audit-log')->assertForbidden();
        // Leer catálogos no es cambiarlos.
        $this->actingAs($contador, 'sanctum')->postJson('/api/areas', ['nombre' => 'Contabilidad'])->assertForbidden();
    }

    public function test_quitarle_un_modulo_a_un_rol_le_cierra_esa_puerta_en_el_acto(): void
    {
        $admin = $this->crearUsuario('admin');
        $rrhh = $this->crearUsuario('rrhh');
        $this->actingAs($rrhh, 'sanctum')->getJson('/api/cts')->assertOk();

        // Administración le quita CTS a RR.HH. desde Módulos.
        $cts = Modulo::where('ruta', '/cts')->firstOrFail();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/modules/{$cts->id}/roles", ['roles' => [Rol::where('nombre', 'admin')->value('id')]])
            ->assertOk();

        $this->actingAs($rrhh->fresh(), 'sanctum')->getJson('/api/cts')->assertForbidden();
    }

    public function test_el_superadministrador_entra_aunque_le_quiten_el_modulo(): void
    {
        $admin = $this->crearUsuario('admin');
        Modulo::where('ruta', '/auditoria')->firstOrFail()->roles()->detach();
        \App\Support\Permisos::refrescar();

        $this->actingAs($admin, 'sanctum')->getJson('/api/audit-log')->assertOk();
    }

    public function test_el_superadministrador_lo_es_por_su_marca_no_por_su_nombre(): void
    {
        $this->sembrarRolesYModulos();
        Rol::create(['nombre' => 'direccion', 'descripcion' => 'Dirección', 'es_superadmin' => true]);

        $this->actingAs($this->crearUsuario('direccion'), 'sanctum')->getJson('/api/audit-log')->assertOk();
    }

    public function test_un_trabajador_solo_entra_a_lo_suyo(): void
    {
        $trabajador = $this->crearUsuario('empleado');

        foreach (['/api/employees', '/api/payroll-runs', '/api/areas', '/api/settings', '/api/cts', '/api/dashboard'] as $ruta) {
            $this->actingAs($trabajador, 'sanctum')->getJson($ruta)->assertForbidden();
        }
        $this->actingAs($trabajador, 'sanctum')->getJson('/api/my-modules')->assertOk();
    }

    public function test_rrhh_entra_a_lo_de_siempre_y_no_a_lo_de_administracion(): void
    {
        $rrhh = $this->crearUsuario('rrhh');

        foreach (['/api/dashboard', '/api/employees', '/api/payroll-runs', '/api/employee-files', '/api/contracts',
            '/api/income-tax', '/api/cts', '/api/bonuses', '/api/settings', '/api/payment-concepts', '/api/roles'] as $ruta) {
            $this->assertNotSame(403, $this->actingAs($rrhh, 'sanctum')->getJson($ruta)->status(), $ruta);
        }
        $this->actingAs($rrhh, 'sanctum')->getJson('/api/audit-log')->assertForbidden();
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/roles', ['nombre' => 'nuevo'])->assertForbidden();
        $this->actingAs($rrhh, 'sanctum')->getJson('/api/modules')->assertForbidden();
    }

    public function test_me_trae_los_permisos_para_la_pantalla(): void
    {
        $rrhh = $this->crearUsuario('rrhh');

        $permisos = $this->actingAs($rrhh, 'sanctum')->getJson('/api/me')->assertOk()->json('data.permisos');

        $this->assertFalse($permisos['es_superadmin']);
        $this->assertContains('/planillas', $permisos['modulos']);
        $this->assertNotContains('/auditoria', $permisos['modulos']);
    }

    /**
     * Ninguna ruta de la sesión queda sin decir qué módulo pide, salvo las
     * de cada uno (lo suyo: su perfil, sus boletas, sus documentos…), que se
     * cuidan dentro del controlador. Si mañana se agrega una ruta sin
     * «permiso:», esta prueba lo avisa.
     */
    public function test_ninguna_ruta_de_la_sesion_queda_sin_permiso(): void
    {
        $propias = [
            'api/logout', 'api/me', 'api/my-', 'api/terms', 'api/progress/', 'api/change-password', 'api/verify-password',
            'api/users/{id}/photo', 'api/vacations', 'api/documents/{id}/view', 'api/documents/{id}/download',
        ];

        $sinPermiso = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('auth:sanctum', $r->gatherMiddleware(), true))
            ->reject(fn ($r) => collect($r->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'permiso:')))
            ->reject(fn ($r) => collect($propias)->contains(fn ($p) => str_starts_with($r->uri(), $p)))
            // Aprobar vacaciones sí pide permiso; pedirlas y verlas es de cada uno.
            ->map(fn ($r) => implode('|', $r->methods()) . ' ' . $r->uri())
            ->values()->all();

        $this->assertSame([], $sinPermiso, 'Rutas sin «permiso:»: ' . implode(', ', $sinPermiso));
    }
}
