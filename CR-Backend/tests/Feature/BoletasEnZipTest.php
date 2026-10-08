<?php

namespace Tests\Feature;

use App\Models\Planilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Las boletas del mes en un .zip: solo las ya emitidas, y con los filtros de la pantalla. */
class BoletasEnZipTest extends TestCase
{
    use RefreshDatabase;

    public function test_baja_solo_las_emitidas_y_respeta_el_buscador(): void
    {
        Storage::fake('local');
        Mail::fake();

        $anio = (int) now()->year;
        $mes = (int) now()->month;
        $rrhh = $this->crearUsuario('rrhh');

        $emitida = $this->crearEmpleado(['dni' => '42083098', 'apellido' => 'Gatica Quispe', 'nombre' => 'Daniel', 'sueldo_base' => 2000]);
        $otraEmitida = $this->crearEmpleado(['dni' => '40000001', 'apellido' => 'Ñahui Pérez', 'nombre' => 'Rosa', 'sueldo_base' => 1800]);
        $sinEmitir = $this->crearEmpleado(['dni' => '40000002', 'sueldo_base' => 1500]);

        foreach ([$emitida, $otraEmitida, $sinEmitir] as $e) {
            Planilla::create(['empleado_id' => $e->id, 'mes' => $mes, 'anio' => $anio, 'sueldo_base' => $e->sueldo_base, 'total' => $e->sueldo_base]);
        }
        foreach ([$emitida, $otraEmitida] as $e) {
            $this->actingAs($rrhh, 'sanctum')->get("/api/payslips/{$e->id}/{$mes}/{$anio}")->assertOk();
        }

        $nombres = function ($respuesta) {
            $ruta = $respuesta->baseResponse->getFile()->getPathname();
            $zip = new \ZipArchive();
            $zip->open($ruta);
            $lista = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $lista[] = $zip->getNameIndex($i);
            }
            $zip->close();
            sort($lista);
            return $lista;
        };

        $todo = $this->actingAs($rrhh, 'sanctum')->get("/api/employees/payslips-zip?mes={$mes}&anio={$anio}")->assertOk();
        $this->assertSame([
            "BOL-{$anio}-0001_40000001_Nahui_Perez_Rosa.pdf",
            "BOL-{$anio}-0001_42083098_Gatica_Quispe_Daniel.pdf",
        ], $nombres($todo));

        $buscado = $this->actingAs($rrhh, 'sanctum')->get("/api/employees/payslips-zip?mes={$mes}&anio={$anio}&search=gatica")->assertOk();
        $this->assertSame(["BOL-{$anio}-0001_42083098_Gatica_Quispe_Daniel.pdf"], $nombres($buscado));

        // Ninguna emitida con ese filtro: se avisa, no se baja un zip vacío.
        $this->actingAs($rrhh, 'sanctum')
            ->getJson("/api/employees/payslips-zip?mes={$mes}&anio={$anio}&boleta=sin")
            ->assertNotFound();

        // Un trabajador no puede bajarse las de todos.
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')
            ->getJson("/api/employees/payslips-zip?mes={$mes}&anio={$anio}")
            ->assertForbidden();
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')
            ->getJson("/api/employees/payslips-zip/link?mes={$mes}&anio={$anio}")
            ->assertForbidden();

        // Por enlace (lo baja el navegador): lleva los filtros, y sin sesión
        // también vale, porque va firmado.
        $enlace = $this->actingAs($rrhh, 'sanctum')
            ->getJson("/api/employees/payslips-zip/link?mes={$mes}&anio={$anio}&search=gatica")
            ->assertOk()->assertJsonPath('data.cantidad', 1)->json('data.url');
        $this->assertStringStartsWith('/api/payslips-zip/download?', $enlace);
        $this->app['auth']->forgetGuards();
        $this->assertSame(["BOL-{$anio}-0001_42083098_Gatica_Quispe_Daniel.pdf"], $nombres($this->get($enlace)->assertOk()));

        // Tocado (otro filtro) o sin firma: no vale.
        $this->get(str_replace('search=gatica', 'search=perez', $enlace))->assertForbidden();
        $this->get('/api/payslips-zip/download?mes=' . $mes . '&anio=' . $anio . '&usuario=' . $rrhh->id)->assertForbidden();

        // Si quien lo pidió ya no está activo, el enlace deja de servir.
        $rrhh->forceFill(['estado_registro' => 'inactivo'])->save();
        $this->get($enlace)->assertForbidden();

        // Sin boletas con ese filtro, ni siquiera se arma el enlace.
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson("/api/employees/payslips-zip/link?mes={$mes}&anio={$anio}&boleta=sin")
            ->assertNotFound();
    }
}
