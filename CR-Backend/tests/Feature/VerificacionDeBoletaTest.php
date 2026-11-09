<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Planilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** El QR de la boleta dice «auténtica» solo si la dirección es legítima y la boleta sigue vigente. */
class VerificacionDeBoletaTest extends TestCase
{
    use RefreshDatabase;

    private function boleta(array $datos = []): Documento
    {
        $empleado = $this->crearEmpleado(['nombre' => 'Rosa', 'apellido' => 'Quispe', 'dni' => '45678912']);
        $planilla = Planilla::create(['empleado_id' => $empleado->id, 'mes' => 10, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 2610]);

        return Documento::create(array_merge([
            'empleado_id' => $empleado->id, 'planilla_id' => $planilla->id, 'tipo' => 'boleta',
            'archivo' => 'documentos/b.pdf', 'estado_firma' => 'pendiente', 'estado_registro' => 'activo',
        ], $datos));
    }

    public function test_el_qr_de_una_boleta_vigente_dice_que_es_autentica(): void
    {
        $this->get($this->boleta()->urlDeVerificacion())->assertOk()
            ->assertSee('Boleta auténtica')->assertSee('2,610.00')->assertSee('••••8912')->assertDontSee('45678912');
    }

    public function test_el_qr_vale_aunque_se_entre_por_https_u_otro_dominio(): void
    {
        $url = $this->boleta()->urlDeVerificacion();
        $url = preg_replace('#^https?://[^/]+#', 'https://otro.test', $url);

        $this->get($url)->assertOk()->assertSee('Boleta auténtica');
    }

    public function test_los_qr_ya_impresos_siguen_valiendo(): void
    {
        $url = URL::signedRoute('boleta.verificar', ['documento' => $this->boleta()->id]);

        $this->get($url)->assertOk()->assertSee('Boleta auténtica');
    }

    public function test_cambiar_el_codigo_o_la_firma_no_verifica_nada(): void
    {
        $url = $this->boleta()->urlDeVerificacion();
        $otra = $this->crearEmpleado();
        $ajena = Documento::create(['empleado_id' => $otra->id, 'tipo' => 'boleta', 'archivo' => 'x.pdf', 'estado_firma' => 'pendiente', 'estado_registro' => 'activo']);

        $this->get(preg_replace('#(signature=).{8}#', '$1abcdefgh', $url))->assertNotFound()->assertSee('No se pudo verificar')->assertDontSee('Quispe');
        $this->get("/api/verificar-boleta/{$ajena->id}")->assertNotFound();
    }

    public function test_una_boleta_dada_de_baja_ya_no_es_autentica(): void
    {
        $this->get($this->boleta(['estado_registro' => 'inactivo'])->urlDeVerificacion())
            ->assertStatus(410)->assertSee('sin validez')->assertDontSee('Boleta auténtica')->assertDontSee('2,610.00');
    }

    public function test_sin_la_firma_digital_del_colegio_todavia_no_vale(): void
    {
        $this->get($this->boleta(['firma_colegio' => 'parcial'])->urlDeVerificacion())
            ->assertOk()->assertSee('todavía sin validez')->assertDontSee('Boleta auténtica')->assertDontSee('2,610.00');
    }

    public function test_con_la_firma_digital_completa_es_autentica(): void
    {
        $this->get($this->boleta(['firma_colegio' => 'completa'])->urlDeVerificacion())
            ->assertOk()->assertSee('Boleta auténtica');
    }
}
