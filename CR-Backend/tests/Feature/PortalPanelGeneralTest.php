<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Portal\RedSocial;
use App\Models\Portal\Seccion;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Panel del portal, etapa 1: General. Los datos del colegio (la ficha
 * `sitio`) y las redes sociales, editados desde el panel y vistos por el
 * portal tal como los dejó quien edita.
 */
class PortalPanelGeneralTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const SITIO = '/api/web-portal/sections/sitio';
    private const REDES = '/api/web-portal/social-networks';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(ImportadorDeEjemplos::carpeta(), 'tipico'))->importar();
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    /** Lo que manda la pantalla: los datos del ejemplo, sin las redes, con la versión leída. */
    private function datosDelSitio(array $cambios = []): array
    {
        $datos = $this->ejemplo('tipico', 'sitio.json')['data'];
        unset($datos['redes']);

        return array_replace_recursive($datos, ['version' => $this->getJson(self::SITIO)->json('data.version')], $cambios);
    }

    // ───────── Datos del colegio ─────────

    public function test_lee_la_ficha_con_su_version(): void
    {
        $this->getJson(self::SITIO)
            ->assertOk()
            ->assertJsonPath('data.clave', 'sitio')
            ->assertJsonPath('data.contenido.nombre', 'Colegio Adventista Túpac Amaru')
            ->assertJsonPath('data.version', Seccion::find('sitio')->updated_at->toJSON());
    }

    public function test_guardar_cambia_lo_que_ve_el_portal(): void
    {
        $this->putJson(self::SITIO, $this->datosDelSitio([
            'subtitulo' => '  Inicial, primaria y secundaria  ',
            'whatsapp'  => ['numero' => '+51987654321', 'visible' => '987 654 321'],
        ]))->assertOk();

        $portal = $this->getJson('/api/portal/v1/sitio')->assertOk();
        $portal->assertJsonPath('data.subtitulo', 'Inicial, primaria y secundaria')
            ->assertJsonPath('data.whatsapp.numero', '+51987654321');
        $this->assertCumpleElContrato('/v1/sitio', $portal->getContent());
        $this->assertTrue(Auditoria::where('entidad', 'portal: sección')->where('accion', 'cambió')->exists());
    }

    public function test_guardar_lo_mismo_deja_al_portal_identico_al_ejemplo(): void
    {
        $this->putJson(self::SITIO, $this->datosDelSitio())->assertOk();

        $this->assertIgualAlEjemplo($this->ejemplo('tipico', 'sitio.json'), $this->getJson('/api/portal/v1/sitio')->json());
    }

    public function test_sin_plataforma_academica_el_portal_no_muestra_el_boton(): void
    {
        $this->putJson(self::SITIO, $this->datosDelSitio(['portalAcademico' => ['nombre' => '', 'url' => '']]))->assertOk();
        $this->assertNull($this->getJson('/api/portal/v1/sitio')->json('data.portalAcademico'));

        // Con solo uno de los dos datos, falta el otro.
        $this->putJson(self::SITIO, $this->datosDelSitio(['portalAcademico' => ['nombre' => 'Cubicol', 'url' => '']]))
            ->assertUnprocessable()->assertJsonValidationErrors('portalAcademico.url');
    }

    public function test_las_reglas_del_contrato(): void
    {
        $casos = [
            'nombre'              => ['nombre' => ''],
            'nombreCorto'         => ['nombreCorto' => str_repeat('C', 21)],
            'anioFundacion'       => ['anioFundacion' => now()->year + 1],
            'telefono.numero'     => ['telefono' => ['numero' => '051 325096']],
            'whatsapp.numero'     => ['whatsapp' => ['numero' => '986322833']],
            'direccion.linea'     => ['direccion' => ['linea' => '']],
            'portalAcademico.url' => ['portalAcademico' => ['url' => 'http://cata.cubicol.pe']],
        ];

        foreach ($casos as $campo => $cambio) {
            $this->putJson(self::SITIO, $this->datosDelSitio($cambio))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($campo);
        }

        $this->assertSame('Colegio Adventista Túpac Amaru', Seccion::contenido('sitio')['nombre']);
    }

    public function test_no_pisa_lo_que_otro_guardo_mientras_tanto(): void
    {
        $leida = $this->datosDelSitio(['ciudad' => 'Puno']);

        // Alguien más guarda entre medio.
        $this->travel(2)->seconds();
        $this->putJson(self::SITIO, $this->datosDelSitio(['ciudad' => 'Juliaca, Puno']))->assertOk();

        $this->putJson(self::SITIO, $leida)->assertStatus(409)->assertJsonPath('success', false);
        $this->assertSame('Juliaca, Puno', Seccion::contenido('sitio')['ciudad']);
    }

    public function test_en_una_base_nueva_la_ficha_se_crea(): void
    {
        Seccion::whereKey('sitio')->delete();

        $this->getJson(self::SITIO)->assertOk()->assertJsonPath('data.contenido', null);
        $this->putJson(self::SITIO, $this->datosDelSitio(['version' => null]))->assertOk();

        $this->getJson('/api/portal/v1/sitio')->assertOk();
    }

    public function test_una_ficha_que_no_existe_es_404(): void
    {
        $this->getJson('/api/web-portal/sections/no-existe')->assertNotFound();
    }

    // ───────── Redes sociales ─────────

    public function test_agregar_una_red_y_publicarla(): void
    {
        $id = $this->postJson(self::REDES, [
            'red' => 'instagram', 'cuenta' => '@colegiocata', 'url' => 'https://www.instagram.com/colegiocata/',
        ])->assertCreated()->assertJsonPath('data.estado', 'borrador')->json('data.id');

        $this->assertNotContains('instagram', array_column($this->getJson('/api/portal/v1/sitio')->json('data.redes'), 'red'));

        $this->patchJson(self::REDES . "/{$id}/status", ['estado' => 'publicado'])->assertOk();

        $redes = $this->getJson('/api/portal/v1/sitio')->json('data.redes');
        $this->assertSame('instagram', end($redes)['red']);
    }

    public function test_las_reglas_de_las_redes(): void
    {
        $base = ['red' => 'instagram', 'cuenta' => '@cata', 'url' => 'https://www.instagram.com/cata/'];

        $this->postJson(self::REDES, ['red' => 'facebook'] + $base)->assertUnprocessable()->assertJsonValidationErrors('red'); // ya existe
        $this->postJson(self::REDES, ['red' => 'myspace'] + $base)->assertUnprocessable()->assertJsonValidationErrors('red');
        $this->postJson(self::REDES, ['url' => 'http://instagram.com/cata'] + $base)->assertUnprocessable()->assertJsonValidationErrors('url');
        $this->postJson(self::REDES, ['cuenta' => str_repeat('a', 61)] + $base)->assertUnprocessable()->assertJsonValidationErrors('cuenta');

        // WhatsApp: siempre un enlace wa.me, también al editar la que ya existe.
        $whatsapp = RedSocial::where('red', 'whatsapp')->first();
        $this->putJson(self::REDES . "/{$whatsapp->id}", ['url' => 'https://api.whatsapp.com/send?phone=51986322833'])
            ->assertUnprocessable()->assertJsonValidationErrors('url');
        $this->putJson(self::REDES . "/{$whatsapp->id}", ['url' => 'https://wa.me/51987654321'])->assertOk();
    }

    public function test_ordenar_y_eliminar_se_ven_en_el_portal(): void
    {
        $ids = RedSocial::orderBy('orden')->pluck('id')->all();
        $this->postJson(self::REDES . '/order', ['ids' => array_reverse($ids)])->assertOk();

        $antes = RedSocial::orderBy('orden')->pluck('red')->all();
        $this->assertSame($antes, array_column($this->getJson('/api/portal/v1/sitio')->json('data.redes'), 'red'));

        $this->deleteJson(self::REDES . '/' . $ids[0])->assertOk();
        $this->assertCount(count($ids) - 1, $this->getJson('/api/portal/v1/sitio')->json('data.redes'));
    }

    public function test_quitar_la_aclaracion_la_deja_en_null(): void
    {
        $red = RedSocial::whereNotNull('aclaracion')->first();

        $this->putJson(self::REDES . "/{$red->id}", ['aclaracion' => ''])->assertOk()->assertJsonPath('data.aclaracion', null);

        $enElPortal = collect($this->getJson('/api/portal/v1/sitio')->json('data.redes'))->firstWhere('red', $red->red);
        $this->assertNull($enElPortal['aclaracion']);
    }

    public function test_rrhh_no_edita_nada(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        $this->getJson(self::SITIO)->assertForbidden();
        $this->putJson(self::SITIO, ['nombre' => 'Otro'])->assertForbidden();
        $this->postJson(self::REDES, ['red' => 'x'])->assertForbidden();
    }
}
