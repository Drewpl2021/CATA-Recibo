<?php

namespace Tests\Feature;

use App\Models\Planilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** El trigger de la base: deja rastro aunque el cambio no pase por Laravel. */
class HistorialDePlanillaTest extends TestCase
{
    use RefreshDatabase;

    private function planilla(float $total = 3000): Planilla
    {
        $e = $this->crearEmpleado();

        return Planilla::create([
            'empleado_id' => $e->id, 'mes' => 3, 'anio' => 2026,
            'sueldo_base' => $total, 'total' => $total,
        ]);
    }

    public function test_crear_una_planilla_no_escribe_historial(): void
    {
        $this->planilla();

        $this->assertSame(0, DB::table('planilla_historial')->count());
    }

    public function test_cambiar_el_neto_guarda_el_antes_y_el_despues(): void
    {
        $p = $this->planilla(3000);

        $p->update(['total' => 2610]);

        $fila = DB::table('planilla_historial')->where('planilla_id', $p->id)->first();
        $this->assertNotNull($fila);
        $this->assertEquals(3000, $fila->total_anterior);
        $this->assertEquals(2610, $fila->total_nuevo);
    }

    public function test_un_update_directo_a_la_base_tambien_deja_rastro(): void
    {
        // Justo lo que la auditoría de Laravel NO ve: SQL a mano, sin modelos.
        $p = $this->planilla(3000);

        DB::table('planilla')->where('id', $p->id)->update(['total' => 1]);

        $this->assertSame(1, DB::table('planilla_historial')->where('planilla_id', $p->id)->count());
    }

    public function test_cambiar_el_estado_tambien_se_registra(): void
    {
        $p = $this->planilla();

        $p->update(['estado_registro' => 'inactivo']);

        $fila = DB::table('planilla_historial')->where('planilla_id', $p->id)->first();
        $this->assertSame('activo', $fila->estado_anterior);
        $this->assertSame('inactivo', $fila->estado_nuevo);
    }

    public function test_un_cambio_que_no_toca_ni_neto_ni_estado_no_registra_nada(): void
    {
        $p = $this->planilla();

        $p->update(['sueldo_base' => 3500]);

        $this->assertSame(0, DB::table('planilla_historial')->count());
    }

    public function test_cada_cambio_suma_una_fila(): void
    {
        $p = $this->planilla(1000);

        $p->update(['total' => 1100]);
        $p->update(['total' => 1200]);
        $p->update(['total' => 1300]);

        $this->assertSame(3, DB::table('planilla_historial')->where('planilla_id', $p->id)->count());
    }

    public function test_el_rastro_sobrevive_al_borrado_de_la_planilla(): void
    {
        $p = $this->planilla();
        $p->update(['total' => 10]);

        $p->delete();

        $this->assertSame(1, DB::table('planilla_historial')->where('planilla_id', $p->id)->count());
    }
}
