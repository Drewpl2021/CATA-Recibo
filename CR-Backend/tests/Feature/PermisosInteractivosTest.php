<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Modulo;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dar y quitar módulos uno por uno, y ver las cuentas por rol y por estado. */
class PermisosInteractivosTest extends TestCase
{
    use RefreshDatabase;

    private function modulo(string $ruta): Modulo
    {
        $this->sembrarRolesYModulos();

        return Modulo::where('ruta', $ruta)->firstOrFail();
    }

    private function rolNuevo(string $nombre, array $rutas = []): Rol
    {
        $this->sembrarRolesYModulos();
        $rol = Rol::create(['nombre' => $nombre, 'descripcion' => $nombre]);
        $rol->modulos()->sync(Modulo::whereIn('ruta', $rutas)->pluck('id'));
        \App\Support\Permisos::refrescar();

        return $rol;
    }

    public function test_el_permiso_cambia_al_momento_y_queda_en_auditoria(): void
    {
        $rol = $this->rolNuevo('contabilidad');
        $persona = $this->crearUsuario('contabilidad');
        $cts = $this->modulo('/cts');
        $admin = $this->crearUsuario('admin');

        $this->actingAs($persona, 'sanctum')->getJson('/api/cts')->assertForbidden();

        $this->actingAs($admin, 'sanctum')->putJson("/api/roles/{$rol->id}/modules/{$cts->id}", ['activo' => true])->assertOk();
        $this->actingAs($persona, 'sanctum')->getJson('/api/cts')->assertOk();

        $this->actingAs($admin, 'sanctum')->putJson("/api/roles/{$rol->id}/modules/{$cts->id}", ['activo' => false])->assertOk();
        $this->actingAs($persona, 'sanctum')->getJson('/api/cts')->assertForbidden();

        $this->assertSame(2, Auditoria::where('entidad', 'rol')->count());
    }

    public function test_dar_un_modulo_no_le_quita_los_otros_al_rol(): void
    {
        $rol = $this->rolNuevo('contabilidad', ['/planillas']);

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->putJson("/api/roles/{$rol->id}/modules/{$this->modulo('/cts')->id}", ['activo' => true])->assertOk();

        $this->assertEqualsCanonicalizing(['/planillas', '/cts'], $rol->modulos()->pluck('ruta')->all());
    }

    public function test_quien_maneja_roles_no_se_da_permisos_ni_da_lo_que_no_tiene(): void
    {
        $encargado = $this->rolNuevo('encargado', ['/roles']);
        $otro = $this->rolNuevo('otro');
        $this->actingAs($this->crearUsuario('encargado'), 'sanctum');

        $this->putJson("/api/roles/{$encargado->id}/modules/{$this->modulo('/cts')->id}", ['activo' => true])->assertForbidden();
        $this->putJson("/api/roles/{$otro->id}/modules/{$this->modulo('/cts')->id}", ['activo' => true])->assertForbidden();
        $admin = Rol::where('nombre', 'admin')->firstOrFail();
        $this->putJson("/api/roles/{$admin->id}/modules/{$this->modulo('/cts')->id}", ['activo' => false])->assertForbidden();

        $this->assertSame(0, $otro->modulos()->count());
    }

    public function test_asignar_roles_desde_modulos_pasa_por_las_mismas_reglas(): void
    {
        $this->rolNuevo('encargado', ['/modulos']);
        $encargado = Rol::where('nombre', 'encargado')->firstOrFail();
        $modulos = $this->modulo('/modulos');
        $cts = $this->modulo('/cts');
        $this->actingAs($this->crearUsuario('encargado'), 'sanctum');

        $this->postJson("/api/modules/{$cts->id}/roles", ['roles' => [$encargado->id]])->assertForbidden();
        $this->postJson("/api/modules/{$modulos->id}/roles", ['roles' => []])->assertForbidden();

        $this->assertFalse($encargado->modulos()->where('ruta', '/cts')->exists());
    }

    public function test_el_administrador_no_pierde_su_camino_a_roles_y_modulos(): void
    {
        $this->sembrarRolesYModulos();
        $admin = Rol::where('nombre', 'admin')->firstOrFail();
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');

        $this->putJson("/api/roles/{$admin->id}/modules/{$this->modulo('/roles')->id}", ['activo' => false])->assertForbidden();
        $this->putJson("/api/roles/{$admin->id}/modules/{$this->modulo('/cts')->id}", ['activo' => false])->assertOk();
    }

    public function test_los_roles_dicen_cuantas_cuentas_activas_tienen(): void
    {
        $this->crearUsuario('contabilidad');
        $this->crearUsuario('contabilidad', ['estado_registro' => 'inactivo']);

        $filas = $this->actingAs($this->crearUsuario('admin'), 'sanctum')->getJson('/api/roles')->assertOk()->json('data');

        $this->assertSame(1, collect($filas)->firstWhere('nombre', 'contabilidad')['usuarios_count']);
    }

    public function test_las_cuentas_se_filtran_por_rol_y_por_estado(): void
    {
        $this->sembrarRolesYModulos();
        $persona = $this->crearUsuario('empleado', ['name' => 'Activa']);
        $this->crearUsuario('empleado', ['name' => 'De baja', 'estado_registro' => 'inactivo']);
        $admin = $this->crearUsuario('admin', ['name' => 'Jefe']);

        $this->actingAs($admin, 'sanctum');
        $nombres = fn (string $filtro) => array_column($this->getJson("/api/users?page=0&size=10{$filtro}")->assertOk()->json('data.content'), 'name');

        $this->assertSame(['Activa', 'De baja'], $nombres("&rol_id={$persona->rol_id}"));
        $this->assertSame(['De baja'], $nombres('&estado_registro=inactivo'));
        $this->getJson('/api/users?rol_id=nada')->assertStatus(422);
    }
}
