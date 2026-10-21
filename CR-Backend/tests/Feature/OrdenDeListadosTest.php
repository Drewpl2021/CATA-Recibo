<?php

namespace Tests\Feature;

use App\Models\Planilla;
use App\Models\PlanillaCorrida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El botón «Ordenar» de Empleados y Planillas: el orden lo pone el servidor,
 * sobre todo lo filtrado, así la página 2 sigue el mismo orden que la 1.
 */
class OrdenDeListadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_empleados_por_apellido_de_la_a_a_la_z_y_al_reves(): void
    {
        foreach (['Mamani' => [2000, 'Beatriz'], 'Apaza' => [3500, 'Carlos'], 'Quispe' => [1800, 'Ana']] as $apellido => [$sueldo, $nombre]) {
            $this->crearEmpleado(['apellido' => $apellido, 'nombre' => $nombre, 'sueldo_base' => $sueldo]);
        }
        $rrhh = $this->crearUsuario('rrhh');
        $apellidos = fn (string $orden) => collect($this->actingAs($rrhh, 'sanctum')
            ->getJson("/api/employees?page=0&size=10&orden={$orden}")->assertOk()->json('data.content'))->pluck('apellido')->all();

        $this->assertSame(['Apaza', 'Mamani', 'Quispe'], $apellidos('apellido'));
        $this->assertSame(['Quispe', 'Mamani', 'Apaza'], $apellidos('apellido_desc'));
        $this->assertSame(['Apaza', 'Mamani', 'Quispe'], $apellidos('sueldo_desc'));
        // Por nombres: Ana Quispe, Beatriz Mamani, Carlos Apaza.
        $this->assertSame(['Quispe', 'Mamani', 'Apaza'], $apellidos('nombre'));
        $this->assertSame(['Apaza', 'Mamani', 'Quispe'], $apellidos('nombre_desc'));
    }

    public function test_el_orden_vale_tambien_en_la_segunda_pagina(): void
    {
        foreach (['Ccama', 'Apaza', 'Bustinza', 'Flores', 'Daza', 'Espinoza'] as $apellido) {
            $this->crearEmpleado(['apellido' => $apellido]);
        }
        $pagina2 = $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson('/api/employees?page=1&size=3&orden=apellido')->assertOk()->json('data.content');

        $this->assertSame(['Daza', 'Espinoza', 'Flores'], collect($pagina2)->pluck('apellido')->all());
    }

    public function test_un_orden_que_no_existe_se_ignora(): void
    {
        $this->crearEmpleado();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson('/api/employees?page=0&size=10&orden=password')
            ->assertOk();
    }

    public function test_los_trabajadores_de_una_planilla_por_apellido_y_por_neto(): void
    {
        $corrida = PlanillaCorrida::create(['nombre' => 'Planilla General', 'mes' => 9, 'anio' => 2026, 'estado' => 'abierta']);
        foreach (['Quispe' => 1500, 'Apaza' => 3000, 'Mamani' => 2200] as $apellido => $neto) {
            $e = $this->crearEmpleado(['apellido' => $apellido]);
            Planilla::create(['empleado_id' => $e->id, 'mes' => 9, 'anio' => 2026, 'corrida_id' => $corrida->id, 'sueldo_base' => $neto, 'total' => $neto]);
        }
        $rrhh = $this->crearUsuario('rrhh');
        $apellidos = fn (string $orden) => collect($this->actingAs($rrhh, 'sanctum')
            ->getJson("/api/payrolls?page=0&size=10&corrida_id={$corrida->id}&orden={$orden}")->assertOk()->json('data.content'))
            ->pluck('empleado.apellido')->all();

        $this->assertSame(['Apaza', 'Mamani', 'Quispe'], $apellidos('apellido'));
        $this->assertSame(['Apaza', 'Mamani', 'Quispe'], $apellidos('neto_desc'));
        $this->assertSame(['Quispe', 'Mamani', 'Apaza'], $apellidos('neto'));
    }

    public function test_las_planillas_del_mes_por_nombre(): void
    {
        foreach (['Planilla TIC', 'Planilla Docentes', 'Planilla Administrativos'] as $nombre) {
            PlanillaCorrida::create(['nombre' => $nombre, 'mes' => 9, 'anio' => 2026, 'estado' => 'abierta']);
        }

        $nombres = collect($this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson('/api/payroll-runs?page=0&size=10&mes=9&anio=2026&orden=nombre_desc')->assertOk()->json('data.content'))
            ->pluck('nombre')->all();

        $this->assertSame(['Planilla TIC', 'Planilla Docentes', 'Planilla Administrativos'], $nombres);
    }
}
