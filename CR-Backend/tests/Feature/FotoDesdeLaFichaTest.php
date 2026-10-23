<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** RR.HH. y Admin le ponen (o le quitan) la foto al trabajador desde su ficha. */
class FotoDesdeLaFichaTest extends TestCase
{
    use RefreshDatabase;

    public function test_rrhh_le_pone_y_le_quita_la_foto_a_un_trabajador(): void
    {
        Storage::fake('local');

        $empleado = $this->crearEmpleado();
        $cuenta = $this->crearUsuario('empleado');
        $cuenta->forceFill(['empleado_id' => (string) $empleado->id])->save();
        $rrhh = $this->crearUsuario('rrhh');

        $this->actingAs($rrhh, 'sanctum')
            ->post("/api/employees/{$empleado->id}/photo", ['foto' => UploadedFile::fake()->image('cara.jpg', 300, 300)])
            ->assertOk();

        $ruta = $cuenta->fresh()->foto;
        $this->assertNotNull($ruta);
        Storage::disk('local')->assertExists($ruta);

        // La ve igual que siempre, por la ruta de la cuenta.
        $this->actingAs($rrhh, 'sanctum')->get("/api/users/{$cuenta->id}/photo")->assertOk();

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->deleteJson("/api/employees/{$empleado->id}/photo")
            ->assertOk();
        $this->assertNull($cuenta->fresh()->foto);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_las_tablas_reciben_la_version_de_la_foto_y_nunca_la_ruta_del_archivo(): void
    {
        Storage::fake('local');

        $empleado = $this->crearEmpleado();
        $cuenta = $this->crearUsuario('empleado');
        $cuenta->forceFill(['empleado_id' => (string) $empleado->id])->save();
        $rrhh = $this->crearUsuario('rrhh');

        $version = $this->actingAs($rrhh, 'sanctum')
            ->post("/api/employees/{$empleado->id}/photo", ['foto' => UploadedFile::fake()->image('cara.jpg', 300, 300)])
            ->assertOk()->json('data.foto');
        $ruta = $cuenta->fresh()->foto;
        $this->assertNotSame($ruta, $version);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $version);

        $esperada = ['usuario_id' => $cuenta->id, 'version' => $version];
        $lista = $this->actingAs($rrhh, 'sanctum')->getJson('/api/employees?page=0&size=10')->assertOk();
        $this->assertSame($esperada, collect($lista->json('data.content'))->firstWhere('id', $empleado->id)['foto']);

        $expedientes = $this->actingAs($rrhh, 'sanctum')->getJson('/api/employee-files?page=0&size=10')->assertOk();
        $this->assertSame($esperada, collect($expedientes->json('data.content'))->firstWhere('id', $empleado->id)['foto']);

        $this->actingAs($rrhh, 'sanctum')->getJson("/api/employee-files/{$empleado->id}")
            ->assertOk()->assertJsonPath('data.empleado.foto', $esperada);

        // La ruta del disco no sale en ninguna de las tres respuestas.
        foreach ([$lista, $expedientes] as $respuesta) {
            $this->assertStringNotContainsString('fotos-perfil', $respuesta->getContent());
        }

        // Otra foto, otra versión: así la pantalla sabe que su copia quedó vieja.
        $nueva = $this->actingAs($rrhh, 'sanctum')
            ->post("/api/employees/{$empleado->id}/photo", ['foto' => UploadedFile::fake()->image('otra.jpg', 300, 300)])
            ->assertOk()->json('data.foto');
        $this->assertNotSame($version, $nueva);
    }

    public function test_un_trabajador_no_cambia_la_foto_de_otro_y_sin_cuenta_se_avisa(): void
    {
        Storage::fake('local');
        $empleado = $this->crearEmpleado();

        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')
            ->post("/api/employees/{$empleado->id}/photo", ['foto' => UploadedFile::fake()->image('cara.jpg')], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->post("/api/employees/{$empleado->id}/photo", ['foto' => UploadedFile::fake()->image('cara.jpg')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
