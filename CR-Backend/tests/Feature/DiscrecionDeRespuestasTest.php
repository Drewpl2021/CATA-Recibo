<?php

namespace Tests\Feature;

use App\Exceptions\AvisoParaElUsuario;
use App\Models\Modulo;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * El sistema dice lo justo: cada respuesta lleva solo lo que esa persona
 * necesita, y un error nunca cuenta cómo está hecho el sistema por dentro.
 */
class DiscrecionDeRespuestasTest extends TestCase
{
    use RefreshDatabase;

    private function soloConVacaciones()
    {
        $this->sembrarRolesYModulos();
        $rol = Rol::create(['nombre' => 'jefatura', 'descripcion' => 'jefatura']);
        $rol->modulos()->attach(Modulo::where('ruta', '/vacaciones')->pluck('id'));
        \App\Support\Permisos::refrescar();

        return $this->crearUsuario('jefatura');
    }

    public function test_quien_no_maneja_empleados_no_recibe_sueldos_ni_cuentas_aunque_pida_la_ficha_completa(): void
    {
        $empleado = $this->crearEmpleado([
            'sueldo_base' => 3500, 'numero_cuenta' => '123-4567890', 'cci' => '00212300456789012345', 'direccion' => 'Jr. Lima 123',
        ]);
        $jefe = $this->soloConVacaciones();

        $lista = $this->actingAs($jefe, 'sanctum')->getJson('/api/employees?page=0&size=10&formato=completo')->assertOk();
        $ficha = $this->actingAs($jefe, 'sanctum')->getJson("/api/employees/{$empleado->id}")->assertOk();

        foreach ([$lista->json('data.content.0') ?? $lista->json('data.0'), $ficha->json('data')] as $datos) {
            $this->assertSame($empleado->dni, $datos['dni']);
            foreach (['sueldo_base', 'numero_cuenta', 'cci', 'direccion', 'cuspp', 'fecha_nacimiento', 'usuario'] as $secreto) {
                $this->assertArrayNotHasKey($secreto, $datos, "No debería recibir «{$secreto}».");
            }
        }

        // RR.HH. sí la ve entera.
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->getJson("/api/employees/{$empleado->id}")
            ->assertOk()->assertJsonPath('data.numero_cuenta', '123-4567890');
    }

    public function test_un_error_interno_no_llega_a_la_pantalla_y_un_aviso_para_el_usuario_si(): void
    {
        config(['app.debug' => false]);
        Route::middleware('api')->get('/api/_prueba/interno', fn () => throw new \RuntimeException('El objeto 12 del PDF no es un diccionario.'));
        Route::middleware('api')->get('/api/_prueba/aviso', fn () => throw new AvisoParaElUsuario('La clave del certificado no es correcta.'));

        $interno = $this->getJson('/api/_prueba/interno')->assertStatus(500);
        $this->assertStringNotContainsString('PDF', $interno->getContent());
        $this->assertArrayNotHasKey('detalle', $interno->json());

        $this->getJson('/api/_prueba/aviso')->assertStatus(422)
            ->assertJsonPath('message', 'La clave del certificado no es correcta.');
    }
}
