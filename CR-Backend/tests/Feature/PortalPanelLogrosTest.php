<?php

namespace Tests\Feature;

use App\Models\Portal\Cifra;
use App\Models\Portal\Imagen;
use App\Models\Portal\Logro;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Panel del portal, etapa 4: Logros. La cabecera, las cifras (que son una
 * tabla con las de tres páginas) y los logros con su fuente.
 */
class PortalPanelLogrosTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const CABECERA = '/api/web-portal/sections/logros';
    private const CIFRAS = '/api/web-portal/stats';
    private const LOGROS = '/api/web-portal/achievements';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(ImportadorDeEjemplos::carpeta(), 'tipico'))->importar();
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    private function portal(): array
    {
        $respuesta = $this->getJson('/api/portal/v1/logros')->assertOk();
        $this->assertCumpleElContrato('/v1/logros', $respuesta->getContent());

        return $respuesta->json('data');
    }

    // ───────── Cabecera ─────────

    public function test_la_cabecera(): void
    {
        $version = $this->getJson(self::CABECERA)->json('data.version');

        $this->putJson(self::CABECERA, ['titulo' => 'Logros del colegio', 'bajada' => '', 'version' => $version])->assertOk();

        $this->assertSame('Logros del colegio', $this->portal()['titulo']);
        $this->assertNull($this->portal()['bajada']);

        $this->putJson(self::CABECERA, ['titulo' => str_repeat('L', 61), 'version' => $this->getJson(self::CABECERA)->json('data.version')])
            ->assertUnprocessable()->assertJsonValidationErrors('titulo');
    }

    // ───────── Cifras ─────────

    public function test_cada_pagina_ve_solo_sus_cifras(): void
    {
        $logros = $this->getJson(self::CIFRAS . '?ambito=logros')->assertOk()->json('data');

        $this->assertSame(Cifra::de('logros')->orderBy('orden')->pluck('clave')->all(), array_column($logros, 'clave'));
        $this->assertSame(['logros'], array_values(array_unique(array_column($logros, 'ambito'))));
    }

    public function test_agregar_una_cifra_y_el_tope_de_cuatro_por_pagina(): void
    {
        $this->assertSame(4, Cifra::de('logros')->where('estado', 'publicado')->count());

        // Logros ya tiene 4: la quinta entra en borrador, y no se puede publicar.
        $id = $this->postJson(self::CIFRAS, ['ambito' => 'logros', 'valor' => 12, 'sufijo' => '+', 'etiqueta' => 'concursos de bandas'])
            ->assertCreated()->assertJsonPath('data.estado', 'borrador')->json('data.id');
        $this->patchJson(self::CIFRAS . "/{$id}/status", ['estado' => 'publicado'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');

        // El tope es por página: Inicio tiene 3 y admite una más.
        $this->postJson(self::CIFRAS, ['ambito' => 'portada', 'valor' => 12, 'etiqueta' => 'concursos de bandas', 'estado' => 'publicado'])
            ->assertCreated()->assertJsonPath('data.clave', 'concursos-de-bandas');
        $this->assertCount(4, $this->getJson('/api/portal/v1/portada/cifras')->json('data'));
    }

    public function test_las_reglas_de_una_cifra(): void
    {
        $base = ['ambito' => 'logros', 'valor' => 3, 'etiqueta' => 'veces campeones'];

        $this->postJson(self::CIFRAS, ['valor' => -1] + $base)->assertUnprocessable()->assertJsonValidationErrors('valor');
        $this->postJson(self::CIFRAS, ['valor' => 2.5] + $base)->assertUnprocessable()->assertJsonValidationErrors('valor');
        $this->postJson(self::CIFRAS, ['sufijo' => 'veces'] + $base)->assertUnprocessable()->assertJsonValidationErrors('sufijo');
        $this->postJson(self::CIFRAS, ['ambito' => 'contacto'] + $base)->assertUnprocessable()->assertJsonValidationErrors('ambito');

        // Una cifra no se cambia de página.
        $cifra = Cifra::de('logros')->first();
        $this->putJson(self::CIFRAS . "/{$cifra->id}", ['ambito' => 'portada'])->assertUnprocessable()->assertJsonValidationErrors('ambito');
    }

    public function test_ordenar_las_cifras_de_una_pagina(): void
    {
        $ids = Cifra::de('logros')->orderBy('orden')->pluck('id')->all();

        // Sin página no se ordena: mezclaría las tres listas.
        $this->postJson(self::CIFRAS . '/order', ['ids' => array_reverse($ids)])->assertUnprocessable()->assertJsonValidationErrors('ambito');

        $this->postJson(self::CIFRAS . '/order?ambito=logros', ['ids' => array_reverse($ids)])->assertOk();
        $this->assertSame(Cifra::de('logros')->orderBy('orden')->pluck('clave')->all(), array_column($this->portal()['cifras'], 'id'));
        $this->assertSame(Cifra::de('portada')->orderBy('orden')->pluck('clave')->all(), array_column($this->getJson('/api/portal/v1/portada/cifras')->json('data'), 'id'));
    }

    // ───────── Logros ─────────

    private function logro(array $cambios = []): array
    {
        return array_replace([
            'titulo'      => '1.er puesto en el Concurso Regional de Matemática',
            'categoria'   => 'academico',
            'anio'        => null,
            'nivel'       => 'Secundaria',
            'descripcion' => 'Organizado por la DRE Puno.',
            'imagen_id'   => null,
            'destacado'   => false,
            'fuente'      => ['etiqueta' => 'Resolución de la DRE Puno', 'url' => null],
            'aclaracion'  => 'Año por confirmar',
        ], $cambios);
    }

    public function test_agregar_un_logro_con_su_fuente_y_publicarlo(): void
    {
        $id = $this->postJson(self::LOGROS, $this->logro())->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonPath('data.clave', '1er-puesto-en-el-concurso-regional-de-matematica')
            ->json('data.id');

        $this->assertNotContains('1er-puesto-en-el-concurso-regional-de-matematica', array_column($this->portal()['logros'], 'id'));

        $this->patchJson(self::LOGROS . "/{$id}/status", ['estado' => 'publicado'])->assertOk();

        $nuevo = collect($this->portal()['logros'])->firstWhere('id', '1er-puesto-en-el-concurso-regional-de-matematica');
        $this->assertNull($nuevo['anio']);
        $this->assertSame('Año por confirmar', $nuevo['aclaracion']);
        $this->assertSame(['etiqueta' => 'Resolución de la DRE Puno', 'url' => null], $nuevo['fuente']);
    }

    public function test_las_reglas_de_un_logro(): void
    {
        $casos = [
            'titulo'          => ['titulo' => str_repeat('T', 141)],
            'categoria'       => ['categoria' => 'religioso'],
            'anio'            => ['anio' => 1850],
            'fuente.url'      => ['fuente' => ['etiqueta' => 'Diario Correo', 'url' => 'http://diariocorreo.pe/nota']],
            'fuente.etiqueta' => ['fuente' => ['etiqueta' => '', 'url' => 'https://diariocorreo.pe/nota']],
            'aclaracion'      => ['aclaracion' => str_repeat('a', 61)],
        ];

        foreach ($casos as $campo => $cambio) {
            $this->postJson(self::LOGROS, $this->logro($cambio))->assertUnprocessable()->assertJsonValidationErrors($campo);
        }

        // Sin fuente también vale: queda en null.
        $this->postJson(self::LOGROS, $this->logro(['fuente' => null]))->assertCreated()->assertJsonPath('data.fuente', null);
    }

    public function test_destacar_y_poner_foto(): void
    {
        $foto = Imagen::create(['ruta' => 'banda.webp', 'alt' => 'La banda sinfónica en Concepción', 'ancho' => 1200, 'alto' => 800]);
        $logro = Logro::where('destacado', false)->where('estado', 'publicado')->first();

        $this->putJson(self::LOGROS . "/{$logro->id}", ['destacado' => true, 'imagen_id' => $foto->id])->assertOk()
            ->assertJsonPath('data.imagen.url', $foto->url());

        $enElPortal = collect($this->portal()['logros'])->firstWhere('id', $logro->clave);
        $this->assertTrue($enElPortal['destacado']);
        $this->assertSame($foto->url(), $enElPortal['foto']['url']);
    }

    public function test_quitar_un_logro(): void
    {
        $logro = Logro::where('estado', 'publicado')->first();
        $total = count($this->portal()['logros']);

        $this->patchJson(self::LOGROS . "/{$logro->id}/status", ['estado' => 'borrador'])->assertOk();
        $this->assertCount($total - 1, $this->portal()['logros']);

        $this->deleteJson(self::LOGROS . "/{$logro->id}")->assertOk();
        $this->assertNull(Logro::find($logro->id));
    }

    public function test_rrhh_no_edita_logros(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        $this->getJson(self::LOGROS)->assertForbidden();
        $this->getJson(self::CIFRAS . '?ambito=logros')->assertForbidden();
        $this->putJson(self::CABECERA, [])->assertForbidden();
    }
}
