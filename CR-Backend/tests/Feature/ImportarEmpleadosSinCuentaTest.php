<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * El PLAME registra al personal sin cuenta (no trae correos); después, el
 * Excel de empleados trae el correo de cada uno. Antes eso daba un error
 * por fila («el correo se le asigna desde Usuarios»); ahora le crea su
 * cuenta, como en un alta.
 */
class ImportarEmpleadosSinCuentaTest extends TestCase
{
    use RefreshDatabase;

    private function importar(string $paso, array $filas)
    {
        return $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->postJson("/api/employee-import/{$paso}", [
            'archivo'  => 'empleados.xlsx',
            'columnas' => [
                ['indice' => 0, 'titulo' => 'DNI', 'campo' => 'dni'],
                ['indice' => 1, 'titulo' => 'Correo', 'campo' => 'email'],
            ],
            'filas' => array_map(fn ($f, $i) => ['numero' => $i + 2, 'celdas' => $f], $filas, array_keys($filas)),
        ]);
    }

    public function test_el_correo_del_excel_le_crea_la_cuenta_a_quien_no_tiene(): void
    {
        Mail::fake();
        $this->crearUsuario('empleado');
        $activo = $this->crearEmpleado(['dni' => '40095537']);
        $cesado = $this->crearEmpleado(['dni' => '46117036', 'estado' => 'inactivo', 'fecha_cese' => '2025-12-31']);

        $revision = $this->importar('preview', [['40095537', 'rosa.mamani@ejemplo.edu.pe'], ['46117036', 'luis.condori@ejemplo.edu.pe']])->assertOk();
        $this->assertSame([], $revision->json('data.errores'));
        $this->assertStringContainsString('se crea su cuenta', $revision->json('data.filas.0.cambios.0.titulo'));

        $this->importar('apply', [['40095537', 'rosa.mamani@ejemplo.edu.pe'], ['46117036', 'luis.condori@ejemplo.edu.pe']])->assertOk();

        $cuenta = User::where('empleado_id', $activo->id)->firstOrFail();
        $this->assertSame('rosa.mamani@ejemplo.edu.pe', $cuenta->email);
        $this->assertSame('empleado', $cuenta->rol->nombre);
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertSame('activo', $cuenta->estado_registro);
        // A quien ya cesó se le guarda el correo, pero su cuenta no entra.
        $this->assertSame('inactivo', User::where('empleado_id', $cesado->id)->value('estado_registro'));
        // Nadie recibe nada solo por importar: el acceso lo envía RR.HH. cuando decida.
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_un_correo_que_ya_usa_otra_cuenta_se_rechaza(): void
    {
        $this->crearUsuario('empleado', ['email' => 'ocupado@ejemplo.edu.pe']);
        $this->crearEmpleado(['dni' => '40095537']);

        $this->importar('preview', [['40095537', 'ocupado@ejemplo.edu.pe']])->assertStatus(200)
            ->assertJsonPath('data.errores.0.mensaje', 'El correo ocupado@ejemplo.edu.pe ya lo usa otra cuenta.');
        $this->assertSame(0, User::whereNotNull('empleado_id')->count());
        $this->assertSame(1, Empleado::count());
    }
}
