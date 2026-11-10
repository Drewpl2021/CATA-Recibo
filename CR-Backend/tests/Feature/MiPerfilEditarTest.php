<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Configuración» → Mi perfil: cada quien corrige cómo ubicarlo y sus
 * estudios. Lo laboral lo lleva RR.HH., y el correo es el institucional.
 */
class MiPerfilEditarTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrige_su_contacto_pero_no_su_correo_ni_lo_laboral(): void
    {
        $empleado = $this->crearEmpleado(['dni' => '41234567', 'telefono' => '900000000', 'sueldo_base' => 2500]);
        $usuario = $this->crearUsuario('empleado', ['email' => 'ana.prueba@cata.edu.pe']);
        $usuario->forceFill(['empleado_id' => (string) $empleado->id])->save();

        $this->actingAs($usuario, 'sanctum')->patchJson('/api/my-profile', [
            'telefono'                     => '987654321',
            'direccion'                    => 'Jr. Moquegua N° 852, Juliaca',
            'contacto_emergencia_nombre'   => 'María Quispe (madre)',
            'contacto_emergencia_telefono' => '951000111',
            'nivel_estudios'               => 'universitario',
            // Lo que no le toca: se ignora.
            'email'       => 'otro@gmail.com',
            'dni'         => '99999999',
            'sueldo_base' => 9000,
        ])->assertOk();

        $empleado->refresh();
        $this->assertSame('987654321', $empleado->telefono);
        $this->assertSame('María Quispe (madre)', $empleado->contacto_emergencia_nombre);
        $this->assertSame('41234567', $empleado->dni);
        $this->assertEquals(2500, $empleado->sueldo_base);
        $this->assertSame('ana.prueba@cata.edu.pe', $usuario->fresh()->email);

        // Y lo ve en su perfil.
        $this->actingAs($usuario, 'sanctum')->getJson('/api/my-profile')
            ->assertJsonPath('data.empleado.contacto_emergencia_telefono', '951000111');
    }

    public function test_valida_como_la_ficha_de_rrhh(): void
    {
        $empleado = $this->crearEmpleado();
        $usuario = $this->crearUsuario('empleado');
        $usuario->forceFill(['empleado_id' => (string) $empleado->id])->save();

        $this->actingAs($usuario, 'sanctum')->patchJson('/api/my-profile', ['telefono' => '98-765 abc', 'nivel_estudios' => 'kinder'])
            ->assertStatus(422)->assertJsonValidationErrors(['telefono', 'nivel_estudios']);
    }

    public function test_una_cuenta_sin_ficha_no_tiene_datos_que_editar(): void
    {
        $this->actingAs($this->crearUsuario('admin'), 'sanctum')->patchJson('/api/my-profile', ['telefono' => '987654321'])
            ->assertStatus(422);
    }

    public function test_sin_sesion_no_se_edita_nada(): void
    {
        $this->patchJson('/api/my-profile', ['telefono' => '987654321'])->assertUnauthorized();
    }
}
