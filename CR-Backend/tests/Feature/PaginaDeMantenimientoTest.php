<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cuando el sistema está de baja se ve la página de CATA-Recibo (con el
 * diseño de las del colegio), no la de fábrica en inglés; y el API lo dice
 * con un 503 que la app reconoce.
 */
class PaginaDeMantenimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_estado_responde_sin_sesion(): void
    {
        $this->getJson('/api/status')->assertOk()->assertJsonPath('success', true);
    }

    public function test_en_mantenimiento_el_api_dice_503_y_la_web_muestra_la_pagina(): void
    {
        $this->app->maintenanceMode()->activate([]);
        try {
            $this->getJson('/api/status')->assertStatus(503)
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', fn ($m) => str_contains($m, 'mantenimiento'));

            $pagina = $this->get('/cualquier-pagina')->assertStatus(503);
            $pagina->assertSee('En mantenimiento');
            $pagina->assertSee('CATA-Recibo');
            $pagina->assertSee('http-equiv="refresh"', false);
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }
    }

    public function test_una_pagina_que_no_existe_se_ve_en_castellano(): void
    {
        $this->get('/esta-pagina-no-existe')->assertNotFound()->assertSee('No encontramos esta página')->assertSee('Ir al inicio');
    }

    public function test_la_pagina_fija_del_nginx_esta_al_dia_con_la_plantilla(): void
    {
        $fija = base_path('../docker/web/mantenimiento.html');
        $this->assertFileExists($fija);
        $html = file_get_contents($fija);
        $this->assertStringContainsString('En mantenimiento', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html, 'El logo va dentro: se tiene que ver sin PHP.');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
    }
}
