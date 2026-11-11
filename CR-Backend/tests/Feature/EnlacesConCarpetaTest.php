<?php

namespace Tests\Feature;

use App\Http\Middleware\CorsMiddleware;
use App\Mail\AccesoAlSistema;
use App\Mail\HojaLista;
use App\Mail\RestablecerPassword;
use Tests\TestCase;

/**
 * En el hosting el sistema vive en una carpeta (https://cata.edu.pe/rrhh).
 * Los enlaces de los correos se arman con FRONTEND_URL: sin la carpeta
 * llevaban a la página del colegio («Parece que esta página no existe»).
 * Con ella, CORS tiene que seguir mandando solo el dominio.
 */
class EnlacesConCarpetaTest extends TestCase
{
    public function test_los_enlaces_de_los_correos_llevan_la_carpeta_del_sistema(): void
    {
        config(['app.frontend_url' => 'https://cata.edu.pe/rrhh/']);
        $token = str_repeat('a', 64);

        $this->assertStringStartsWith('https://cata.edu.pe/rrhh/crear-contrasena#token=', (new AccesoAlSistema('Ana', 'ana@cata.edu.pe', $token))->enlace);
        $this->assertStringStartsWith('https://cata.edu.pe/rrhh/restablecer-password#token=', (new RestablecerPassword('Ana', 'ana@cata.edu.pe', $token))->enlace);
        $this->assertSame('https://cata.edu.pe/rrhh/inicio/mis-boletas', (new HojaLista('Ana', 'hoja de CTS', 'noviembre 2026'))->enlace);
    }

    public function test_cors_manda_solo_el_dominio_aunque_frontend_url_lleve_carpeta(): void
    {
        $this->assertSame('https://cata.edu.pe', CorsMiddleware::origenDe('https://cata.edu.pe/rrhh'));
        $this->assertSame('http://localhost:4200', CorsMiddleware::origenDe('http://localhost:4200/'));
        $this->assertSame('http://192.168.1.20:8080', CorsMiddleware::origenDe('http://192.168.1.20:8080/rrhh/'));

        config(['app.frontend_url' => 'https://cata.edu.pe/rrhh']);
        $respuesta = (new CorsMiddleware())->handle(\Illuminate\Http\Request::create('/api/status'), fn () => response('ok'));
        $this->assertSame('https://cata.edu.pe', $respuesta->headers->get('Access-Control-Allow-Origin'));
    }
}
