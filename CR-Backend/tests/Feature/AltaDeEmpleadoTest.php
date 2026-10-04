<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Rol;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dar de alta a alguien desde Nuevo Empleado: siempre entra como empleado.
 * El rol de RR.HH. o Administrador lo da después un Administrador.
 */
class AltaDeEmpleadoTest extends TestCase
{
    use RefreshDatabase;

    private function datosDeAlta(array $cambios = []): array
    {
        return array_merge([
            'dni'              => '45678912',
            'nombre'           => 'Rosa',
            'apellido'         => 'Quispe',
            'cargo_id'         => Cargo::create(['nombre' => 'Docente'])->id,
            'area_id'          => Area::create(['nombre' => 'Secundaria'])->id,
            'sede_id'          => Sede::create(['nombre' => 'CATA Central'])->id,
            'telefono'         => '987654321',
            'direccion'        => 'Jr. Lima 123',
            'fecha_ingreso'    => '2026-03-01',
            'fecha_nacimiento' => '1990-05-10',
            'sueldo_base'      => 2500,
            'tipo_contrato_id' => $this->idTipoContrato('Plazo indeterminado'),
            'email'            => 'rosa.quispe@prueba.test',
        ], $cambios);
    }

    public function test_aunque_pidan_el_rol_de_administrador_entra_como_empleado(): void
    {
        $admin = $this->crearUsuario('admin');
        $rolEmpleado = Rol::firstOrCreate(['nombre' => 'empleado'], ['descripcion' => 'empleado']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/employees', $this->datosDeAlta(['rol_id' => $admin->rol_id]))
            ->assertCreated();

        $cuenta = User::where('email', 'rosa.quispe@prueba.test')->first();
        $this->assertSame((string) $rolEmpleado->id, (string) $cuenta->rol_id);
    }

    public function test_sin_mandar_rol_tambien_entra_como_empleado(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $rolEmpleado = Rol::firstOrCreate(['nombre' => 'empleado'], ['descripcion' => 'empleado']);

        $this->actingAs($rrhh, 'sanctum')
            ->postJson('/api/employees', $this->datosDeAlta())
            ->assertCreated();

        $this->assertSame((string) $rolEmpleado->id, (string) User::where('email', 'rosa.quispe@prueba.test')->value('rol_id'));
    }
}
