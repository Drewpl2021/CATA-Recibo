<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** `usuario:nueva-contrasena`: recuperar la cuenta del admin desde el servidor. */
class NuevaContrasenaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pone_una_contrasena_nueva_la_muestra_y_obliga_a_cambiarla(): void
    {
        $admin = $this->crearUsuario('admin');
        $antes = $admin->password;
        $admin->createToken('sesion-abierta');

        $this->assertSame(0, Artisan::call('usuario:nueva-contrasena', ['correo' => $admin->email]));

        preg_match('/Contraseña nueva: (\S+)/', Artisan::output(), $m);
        $admin->refresh();

        $this->assertNotSame($antes, $admin->password);
        $this->assertTrue(Hash::check($m[1], $admin->password));
        $this->assertTrue($admin->debe_cambiar_password);
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_un_correo_que_no_existe_no_toca_nada(): void
    {
        $this->assertSame(1, Artisan::call('usuario:nueva-contrasena', ['correo' => 'nadie@ejemplo.com']));
    }
}
