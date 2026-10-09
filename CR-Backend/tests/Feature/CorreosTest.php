<?php

namespace Tests\Feature;

use App\Mail\AccesoAlSistema;
use App\Mail\BoletaGenerada;
use App\Mail\RestablecerPassword;
use Tests\TestCase;

/**
 * Los correos del sistema se arman bien: su título, su botón con el enlace
 * correcto y ninguna directiva de Blade suelta en el texto (pasó: un
 * «@endsection» pegado a una palabra salía impreso en el correo de la boleta).
 */
class CorreosTest extends TestCase
{
    private function revisar(string $html, string $titulo, string $enlace): void
    {
        $this->assertStringContainsString($titulo, $html);
        $this->assertStringContainsString(e($enlace), $html);
        $this->assertDoesNotMatchRegularExpression('/@(section|endsection|include|extends|yield)\b/', $html);
        $this->assertStringContainsString('Colegio Adventista Túpac Amaru', $html);
    }

    public function test_los_cuatro_correos_se_arman_bien(): void
    {
        config(['app.frontend_url' => 'https://cata.test']);

        $bienvenida = new AccesoAlSistema('Juan Zapana', 'juan@correo.test', 'TOKEN1');
        $this->revisar($bienvenida->render(), 'Te damos la bienvenida a CATA-Recibo', 'https://cata.test/crear-contrasena?token=TOKEN1&email=juan%40correo.test');
        $this->assertSame('Tu acceso a CATA-Recibo — Colegio Adventista Túpac Amaru', $bienvenida->envelope()->subject);

        $restablecer = new AccesoAlSistema('Juan Zapana', 'juan@correo.test', 'TOKEN2', true);
        $this->revisar($restablecer->render(), 'Crea una contraseña nueva', 'https://cata.test/crear-contrasena?token=TOKEN2');
        $this->assertStringContainsString('Recursos Humanos restableció', $restablecer->render());

        $this->revisar((new RestablecerPassword('Juan Zapana', 'juan@correo.test', 'TOKEN3'))->render(),
            '¿Olvidaste tu contraseña?', 'https://cata.test/restablecer-password?token=TOKEN3');

        $boleta = (new BoletaGenerada('Juan Zapana', 'Octubre', 2026, 'BOL-2026-0001'))->render();
        $this->revisar($boleta, 'Tu boleta de Octubre ya está lista', 'https://cata.test/inicio/mis-boletas');
        $this->assertStringContainsString('BOL-2026-0001', $boleta);
        $this->assertStringContainsString('da tu conformidad', $boleta);
    }
}
