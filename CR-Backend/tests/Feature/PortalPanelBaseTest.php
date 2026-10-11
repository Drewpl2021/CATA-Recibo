<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Portal\Banner;
use App\Models\Portal\RedSocial;
use App\Models\Portal\Seccion;
use App\Support\Portal\ImportadorDeEjemplos;
use App\Support\Portal\ValidadorDeContrato;
use Database\Seeders\ModuloSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\ListaDePruebaController;
use Tests\TestCase;

/**
 * La base del panel del portal (etapa 2, etapa 0): la puerta, el controlador
 * común de las listas, la validación contra el contrato antes de confirmar
 * y el módulo en el menú.
 */
class PortalPanelBaseTest extends TestCase
{
    use RefreshDatabase;

    private const PRUEBA = '/api/web-portal/prueba-redes';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), 'tipico'))->importar();

        // Una lista de prueba con la misma cadena que el panel de verdad.
        Route::middleware(['api', 'auth:sanctum', 'sesion', 'clave_nueva', 'terminos', 'can:editar-portal'])
            ->prefix(ltrim(self::PRUEBA, '/'))
            ->group(function () {
                Route::get('/', [ListaDePruebaController::class, 'index']);
                Route::post('/', [ListaDePruebaController::class, 'store']);
                Route::post('order', [ListaDePruebaController::class, 'ordenar']);
                Route::put('{id}', [ListaDePruebaController::class, 'update']);
                Route::patch('{id}/status', [ListaDePruebaController::class, 'estado']);
                Route::delete('{id}', [ListaDePruebaController::class, 'destroy']);
            });
    }

    private function comoAdmin(): static
    {
        return $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    // ───────── La puerta ─────────

    public function test_solo_el_administrador_pasa_la_puerta(): void
    {
        $this->getJson('/api/web-portal/summary')->assertUnauthorized();

        foreach (['rrhh', 'empleado'] as $rol) {
            $this->actingAs($this->crearUsuario($rol), 'sanctum')
                ->getJson('/api/web-portal/summary')
                ->assertForbidden()
                ->assertJsonPath('success', false);
        }

        $this->comoAdmin()->getJson('/api/web-portal/summary')->assertOk();
    }

    public function test_la_puerta_va_despues_de_la_cadena_de_siempre(): void
    {
        // Un admin que todavía debe cambiar la contraseña queda trabado como
        // en cualquier otra pantalla protegida: 423, como lo responde
        // ExigirCambioPassword en todo el sistema.
        $admin = $this->crearUsuario('admin', ['debe_cambiar_password' => true]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/web-portal/summary')->assertStatus(423);
    }

    public function test_el_resumen_cuenta_cada_lista_y_las_fichas(): void
    {
        Banner::first()->update(['estado' => Banner::BORRADOR]);

        $datos = $this->comoAdmin()->getJson('/api/web-portal/summary')->assertOk()->json('data');

        $this->assertSame(['publicados' => Banner::count() - 1, 'borradores' => 1], $datos['listas']['banners']);
        $this->assertSame(RedSocial::count(), $datos['listas']['redes']['publicados']);
        $this->assertArrayHasKey('cifras-logros', $datos['listas']);
        $this->assertContains('sitio', $datos['fichas']);
        $this->assertCount(14, $datos['fichas']);
    }

    // ───────── El controlador base ─────────

    public function test_lo_nuevo_entra_al_final_y_en_borrador(): void
    {
        $respuesta = $this->comoAdmin()->postJson(self::PRUEBA, [
            'red' => 'instagram', 'cuenta' => '@colegiocata', 'url' => 'https://www.instagram.com/colegiocata/',
        ])->assertCreated();

        $this->assertSame(RedSocial::BORRADOR, $respuesta->json('data.estado'));
        $this->assertSame(RedSocial::count(), $respuesta->json('data.orden'));
        // No sale en el portal hasta que alguien lo publique.
        $this->assertNotContains('instagram', array_column($this->getJson('/api/portal/v1/sitio')->json('data.redes'), 'red'));
    }

    public function test_editar_publicar_ordenar_y_eliminar(): void
    {
        $this->comoAdmin();
        $red = RedSocial::orderBy('orden')->first();

        $this->putJson(self::PRUEBA . "/{$red->id}", ['cuenta' => 'Colegio CATA'])
            ->assertOk()->assertJsonPath('data.cuenta', 'Colegio CATA');

        $this->patchJson(self::PRUEBA . "/{$red->id}/status", ['estado' => 'borrador'])
            ->assertOk()->assertJsonPath('data.estado', 'borrador');
        $this->patchJson(self::PRUEBA . "/{$red->id}/status", ['estado' => 'archivado'])->assertUnprocessable();

        $al_reves = RedSocial::orderByDesc('orden')->pluck('id')->all();
        $this->postJson(self::PRUEBA . '/order', ['ids' => $al_reves])->assertOk();
        $this->assertSame($al_reves, RedSocial::orderBy('orden')->pluck('id')->all());

        $this->deleteJson(self::PRUEBA . "/{$red->id}")->assertOk();
        $this->assertNull(RedSocial::find($red->id));
    }

    public function test_ordenar_exige_la_lista_entera(): void
    {
        $this->comoAdmin();
        $ids = RedSocial::orderBy('orden')->pluck('id')->all();

        // Falta uno (alguien agregó o borró mientras tanto).
        $this->postJson(self::PRUEBA . '/order', ['ids' => array_slice($ids, 1)])
            ->assertUnprocessable()->assertJsonValidationErrors('ids');
        // Uno que no es de la lista.
        $this->postJson(self::PRUEBA . '/order', ['ids' => [...array_slice($ids, 1), 'otro']])
            ->assertUnprocessable();

        $this->assertSame($ids, RedSocial::orderBy('orden')->pluck('id')->all());
    }

    public function test_cada_cambio_queda_en_la_auditoria(): void
    {
        $this->comoAdmin()->putJson(self::PRUEBA . '/' . RedSocial::first()->id, ['cuenta' => 'Otra cuenta'])->assertOk();

        $this->assertTrue(Auditoria::where('entidad', 'portal: red social')->where('accion', 'cambió')->exists());
    }

    // ───────── La validación contra el contrato ─────────

    public function test_no_se_guarda_nada_que_el_portal_descartaria(): void
    {
        $this->comoAdmin();
        $antes = RedSocial::count();

        // Las reglas (flojas, a propósito) dejan pasar un http://, pero el
        // portal descartaría /v1/sitio entero: no se guarda.
        $respuesta = $this->postJson(self::PRUEBA, [
            'red' => 'instagram', 'cuenta' => '@cata', 'url' => 'http://instagram.com/cata', 'estado' => 'publicado',
        ])->assertUnprocessable()->assertJsonValidationErrors('contrato');

        $this->assertStringContainsString('los datos del colegio', $respuesta->json('errors.contrato.0'));
        $this->assertSame($antes, RedSocial::count());
    }

    public function test_un_borrador_no_rompe_nada_pero_no_se_puede_publicar_si_romperia(): void
    {
        $this->comoAdmin();

        $id = $this->postJson(self::PRUEBA, [
            'red' => 'instagram', 'cuenta' => '@cata', 'url' => 'http://instagram.com/cata',
        ])->assertCreated()->json('data.id');

        $this->patchJson(self::PRUEBA . "/{$id}/status", ['estado' => 'publicado'])
            ->assertUnprocessable()->assertJsonValidationErrors('contrato');

        $this->assertSame(RedSocial::BORRADOR, RedSocial::find($id)->estado);
    }

    public function test_el_validador_reconoce_el_portal_sano_y_el_roto(): void
    {
        $validador = app(ValidadorDeContrato::class);
        $rutas = ['/v1/sitio', '/v1/portada/banners', '/v1/noticias', '/v1/proyectos/robotica', '/v1/nosotros/paginas/himno'];

        $this->assertSame([], $validador->problemas($rutas));

        // Una ficha obligatoria a medias: no hay respuesta que dar.
        $contenido = Seccion::contenido('sitio');
        unset($contenido['nombre']);
        Seccion::find('sitio')->update(['contenido' => $contenido]);

        $problemas = $validador->problemas($rutas);
        $this->assertSame(['/v1/sitio'], array_keys($problemas));
        $this->assertStringContainsString('nombre', $problemas['/v1/sitio']);

        // Y un detalle que no existe.
        $this->assertStringContainsString('404', $validador->problema('/v1/proyectos/no-existe'));
    }

    // ───────── El módulo en el menú ─────────

    public function test_la_migracion_lo_pone_al_final_del_menu_solo_para_el_administrador(): void
    {
        $this->rolesDelSistema();
        $this->seed(ModuloSeeder::class);
        DB::table('rol_modulo')->whereIn('modulo_id', DB::table('modulos')->where('ruta', '/portal-web')->pluck('id'))->delete();
        DB::table('modulos')->where('ruta', '/portal-web')->delete();
        DB::table('modulo_padre')->where('nombre', 'Portal Web')->delete();
        $ordenAntes = DB::table('modulo_padre')->orderBy('orden')->pluck('orden', 'nombre')->all();

        $migracion = require database_path('migrations/2026_10_10_000001_agregar_modulo_portal_web.php');
        ob_start();
        $migracion->up();
        $migracion->up(); // dos veces: no lo repite
        ob_end_clean();

        $this->assertMenuDelPortal();
        // Los grupos que ya existían quedan donde estaban.
        $this->assertSame($ordenAntes, DB::table('modulo_padre')->where('nombre', '!=', 'Portal Web')->orderBy('orden')->pluck('orden', 'nombre')->all());
        $this->assertSame(max($ordenAntes) + 1, DB::table('modulo_padre')->where('nombre', 'Portal Web')->value('orden'));

        ob_start();
        $migracion->down();
        ob_end_clean();
        $this->assertFalse(DB::table('modulos')->where('ruta', '/portal-web')->exists());
        $this->assertFalse(DB::table('modulo_padre')->where('nombre', 'Portal Web')->exists());
    }

    public function test_una_instalacion_nueva_lo_trae_desde_el_seeder(): void
    {
        $this->rolesDelSistema();
        $this->seed(ModuloSeeder::class);

        $this->assertMenuDelPortal();
    }

    /**
     * Los tres roles del sistema. No se usa RolSeeder: apaga las llaves
     * foráneas con SQL de MySQL, que la base de pruebas (SQLite) no entiende.
     */
    private function rolesDelSistema(): void
    {
        foreach (['admin', 'rrhh', 'empleado'] as $rol) {
            Rol::firstOrCreate(['nombre' => $rol], ['descripcion' => $rol]);
        }
    }

    private function assertMenuDelPortal(): void
    {
        $modulo = DB::table('modulos')->where('ruta', '/portal-web')->first();
        $this->assertNotNull($modulo);

        $roles = DB::table('rol_modulo')->join('roles', 'roles.id', '=', 'rol_modulo.rol_id')
            ->where('modulo_id', $modulo->id)->pluck('roles.nombre')->all();
        $this->assertSame(['admin'], $roles);
    }
}
