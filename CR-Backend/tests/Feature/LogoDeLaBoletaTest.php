<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El escudo de la boleta, en el PDF y en la página que abre el QR.
 *
 * En el hosting (cata.edu.pe/rrhh) la boleta salía sin escudo, sin marca de
 * agua y con el QR roto: dompdf usaba la carpeta temporal del sistema, donde
 * PHP no podía escribir. Y la página de verificación buscaba el escudo en la
 * raíz del dominio (/logo-colegio.png), fuera de /rrhh/.
 */
class LogoDeLaBoletaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dompdf_usa_una_carpeta_temporal_dentro_de_storage(): void
    {
        $this->assertSame(storage_path('app/dompdf'), config('dompdf.options.temp_dir'));
    }

    public function test_la_boleta_lleva_el_escudo_la_marca_de_agua_y_el_qr(): void
    {
        Storage::fake('local');
        Mail::fake();
        $anio = (int) now()->year;
        $empleado = $this->crearEmpleado(['sueldo_base' => 2000]);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->postJson('/api/payroll-runs', ['nombre' => 'Planilla General', 'mes' => 3, 'anio' => $anio, 'generar' => true])
            ->assertCreated();

        $pdf = $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->get("/api/payslips/{$empleado->id}/3/{$anio}")
            ->assertOk()
            ->getContent();

        $this->assertDirectoryExists(storage_path('app/dompdf'));
        // El escudo (225 × 225), la marca de agua (795 × 181) y el QR.
        $this->assertMatchesRegularExpression('/\/Width 225\s*\/Height 225/', $pdf, 'La boleta salió sin el escudo.');
        $this->assertMatchesRegularExpression('/\/Width 795\s*\/Height 181/', $pdf, 'La boleta salió sin la marca de agua.');
        $this->assertGreaterThanOrEqual(3, preg_match_all('/\/Subtype\s*\/Image/', $pdf), 'Faltan imágenes en la boleta (¿el QR?).');
    }

    public function test_la_pagina_del_qr_trae_el_escudo_sin_depender_de_la_carpeta_del_sistema(): void
    {
        $html = $this->get('/api/verificar-boleta/no-existe')->getContent();

        $this->assertStringContainsString('src="data:image/png;base64,', $html);
        $this->assertStringNotContainsString('src="/logo-colegio.png"', $html);
    }
}
