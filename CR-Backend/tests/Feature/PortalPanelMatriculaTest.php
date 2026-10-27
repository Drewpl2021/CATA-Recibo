<?php

namespace Tests\Feature;

use App\Models\Portal\FechaMatricula;
use App\Models\Portal\GrupoGrados;
use App\Models\Portal\Pregunta;
use App\Models\Portal\ProcesoMatricula;
use App\Models\Portal\Vacante;
use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContratoDelPortal;
use Tests\TestCase;

/**
 * Panel del portal, etapa 5: Matrícula. La cabecera, los procesos con sus
 * pasos y listas, las vacantes, el cronograma, las preguntas y las opciones
 * de grado del formulario.
 */
class PortalPanelMatriculaTest extends TestCase
{
    use RefreshDatabase;
    use ContratoDelPortal;

    private const FICHAS = '/api/web-portal/sections/';
    private const PROCESOS = '/api/web-portal/enrollment-processes';
    private const VACANTES = '/api/web-portal/vacancies';
    private const FECHAS = '/api/web-portal/enrollment-dates';
    private const PREGUNTAS = '/api/web-portal/faqs';
    private const GRADOS = '/api/web-portal/grade-options';

    protected function setUp(): void
    {
        parent::setUp();
        (new ImportadorDeEjemplos(base_path('tests/Fixtures/portal/mock'), 'tipico'))->importar();
        config(['portal.url_medios' => 'https://rrhh.cata.edu.pe/api/portal/medios']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');
    }

    /** Lo que ve el portal en esa ruta, comprobando que cumple el contrato. */
    private function portal(string $ruta): array
    {
        $respuesta = $this->getJson("/api/portal{$ruta}")->assertOk();
        $this->assertCumpleElContrato($ruta, $respuesta->getContent());

        return $respuesta->json('data');
    }

    private function guardarFicha(string $clave, array $datos)
    {
        $version = $this->getJson(self::FICHAS . $clave)->assertOk()->json('data.version');

        return $this->putJson(self::FICHAS . $clave, $datos + ['version' => $version]);
    }

    // ───────── Fichas ─────────

    public function test_la_cabecera_con_su_anio(): void
    {
        $figura = $this->getJson(self::FICHAS . 'matricula-cabecera')->json('data.contenido.figura.imagenId');

        $this->guardarFicha('matricula-cabecera', ['titulo' => 'Matrícula 2027', 'bajada' => null, 'anio' => 2027, 'figura' => $figura])->assertOk();
        $cabecera = $this->portal('/v1/matricula/cabecera');
        $this->assertSame(2027, $cabecera['anio']);
        $this->assertNotNull($cabecera['figura']);

        // Sin año, no hay placa: el portal no lo calcula.
        $this->guardarFicha('matricula-cabecera', ['titulo' => 'Matrícula', 'anio' => null, 'figura' => null])->assertOk();
        $this->assertNull($this->portal('/v1/matricula/cabecera')['anio']);
        $this->assertNull($this->portal('/v1/matricula/cabecera')['figura']);

        $this->guardarFicha('matricula-cabecera', ['titulo' => 'Matrícula', 'anio' => 1999])
            ->assertUnprocessable()->assertJsonValidationErrors('anio');
    }

    public function test_la_nota_de_vacantes_y_la_del_cronograma(): void
    {
        $this->guardarFicha('matricula-vacantes', ['actualizado' => '2026-10-11', 'nota' => 'Por grado, en secretaría.'])->assertOk();
        $vacantes = $this->portal('/v1/matricula/vacantes');
        $this->assertSame('2026-10-11', $vacantes['actualizado']);
        $this->assertSame('Por grado, en secretaría.', $vacantes['nota']);

        $this->guardarFicha('matricula-vacantes', ['actualizado' => '11/10/2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('actualizado');

        $this->guardarFicha('matricula-fechas', ['nota' => null])->assertOk();
        $this->assertNull($this->portal('/v1/matricula/fechas')['nota']);
    }

    // ───────── Procesos ─────────

    private function proceso(array $cambios = []): array
    {
        return array_replace([
            'nombre'  => 'Estudiantes por traslado',
            'resumen' => 'Para quienes llegan de otro colegio a mitad de año.',
            'pasos'   => [
                ['titulo' => 'Entrevista', 'detalle' => 'Con la dirección del nivel.'],
                ['titulo' => 'Pago de la matrícula', 'detalle' => null],
            ],
            'listas'  => [
                ['titulo' => 'Documentos', 'items' => ['Ficha única de matrícula.', 'Constancia de traslado.']],
            ],
            'estado'  => 'publicado',
        ], $cambios);
    }

    public function test_un_proceso_con_sus_pasos_y_listas(): void
    {
        $id = $this->postJson(self::PROCESOS, $this->proceso())->assertCreated()
            ->assertJsonPath('data.clave', 'estudiantes-por-traslado')->json('data.id');

        $publicado = collect($this->portal('/v1/matricula/procesos'))->firstWhere('id', 'estudiantes-por-traslado');
        $this->assertSame(['titulo' => 'Entrevista', 'detalle' => 'Con la dirección del nivel.'], $publicado['pasos'][0]);
        $this->assertNull($publicado['pasos'][1]['detalle']);
        $this->assertSame(['Ficha única de matrícula.', 'Constancia de traslado.'], $publicado['listas'][0]['items']);

        // Se guardan enteros: los pasos que se mandan son los que quedan.
        $this->putJson(self::PROCESOS . "/{$id}", ['pasos' => [['titulo' => 'Solo un paso']]])->assertOk();
        $this->assertSame([['titulo' => 'Solo un paso', 'detalle' => null]], ProcesoMatricula::find($id)->pasos);

        // Sin pasos ni listas también vale.
        $this->putJson(self::PROCESOS . "/{$id}", ['pasos' => [], 'listas' => []])->assertOk();
        $this->portal('/v1/matricula/procesos');
    }

    public function test_las_reglas_de_un_proceso(): void
    {
        $this->postJson(self::PROCESOS, $this->proceso(['nombre' => str_repeat('N', 41)]))->assertUnprocessable()->assertJsonValidationErrors('nombre');

        $nueve = array_fill(0, 9, ['titulo' => 'Paso', 'detalle' => null]);
        $this->postJson(self::PROCESOS, $this->proceso(['pasos' => $nueve]))->assertUnprocessable()->assertJsonValidationErrors('pasos');

        $sinTitulo = $this->postJson(self::PROCESOS, $this->proceso(['pasos' => [['titulo' => '', 'detalle' => 'Algo']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('pasos.0.titulo');
        // El mensaje habla en el idioma de quien edita, no con «pasos.0.titulo».
        $this->assertStringContainsString('título del paso', $sinTitulo->json('errors')['pasos.0.titulo'][0]);

        $this->postJson(self::PROCESOS, $this->proceso(['listas' => [['titulo' => 'Requisitos', 'items' => []]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('listas.0.items');
        $this->postJson(self::PROCESOS, $this->proceso(['listas' => array_fill(0, 5, ['titulo' => 'L', 'items' => ['x']])]))
            ->assertUnprocessable()->assertJsonValidationErrors('listas');
        $this->postJson(self::PROCESOS, $this->proceso(['listas' => [['titulo' => 'L', 'items' => array_fill(0, 16, 'x')]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('listas.0.items');
    }

    public function test_el_tope_de_cuatro_procesos(): void
    {
        $this->assertSame(2, ProcesoMatricula::where('estado', 'publicado')->count());

        $this->postJson(self::PROCESOS, $this->proceso(['nombre' => 'Tercero']))->assertCreated();
        $this->postJson(self::PROCESOS, $this->proceso(['nombre' => 'Cuarto']))->assertCreated();
        $this->postJson(self::PROCESOS, $this->proceso(['nombre' => 'Quinto']))
            ->assertUnprocessable()->assertJsonValidationErrors('estado');

        $this->assertCount(4, $this->portal('/v1/matricula/procesos'));
    }

    // ───────── Vacantes ─────────

    public function test_las_vacantes_y_solo_cifras_reales(): void
    {
        $inicial = Vacante::where('clave', 'inicial')->first();

        $this->putJson(self::VACANTES . "/{$inicial->id}", ['disponibilidad' => 'pocas', 'vacantes' => 3])->assertOk();
        $nivel = collect($this->portal('/v1/matricula/vacantes')['niveles'])->firstWhere('id', 'inicial');
        $this->assertSame(['estado' => 'pocas', 'vacantes' => 3], ['estado' => $nivel['estado'], 'vacantes' => $nivel['vacantes']]);

        // Agotadas o «consultar» no llevan número, aunque se mande.
        $this->putJson(self::VACANTES . "/{$inicial->id}", ['disponibilidad' => 'agotadas', 'vacantes' => 3])
            ->assertOk()->assertJsonPath('data.vacantes', null);

        $this->postJson(self::VACANTES, ['nivel' => 'Inicial 2', 'disponibilidad' => 'muchas'])
            ->assertUnprocessable()->assertJsonValidationErrors('disponibilidad');
        $this->postJson(self::VACANTES, ['nivel' => 'Inicial 2', 'disponibilidad' => 'disponible', 'vacantes' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors('vacantes');
    }

    public function test_el_tope_de_seis_niveles_de_vacantes(): void
    {
        foreach (['Cuarto', 'Quinto', 'Sexto'] as $nivel) {
            $this->postJson(self::VACANTES, ['nivel' => $nivel, 'disponibilidad' => 'consultar', 'estado' => 'publicado'])->assertCreated();
        }

        $this->postJson(self::VACANTES, ['nivel' => 'Séptimo', 'disponibilidad' => 'consultar', 'estado' => 'publicado'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->assertCount(6, $this->portal('/v1/matricula/vacantes')['niveles']);
    }

    // ───────── Cronograma ─────────

    public function test_el_cronograma_va_por_fecha(): void
    {
        $this->postJson(self::FECHAS, ['titulo' => 'Charla para familias nuevas', 'inicio' => '2025-10-20', 'estado' => 'publicado'])
            ->assertCreated()->assertJsonPath('data.inicio', '2025-10-20')->assertJsonPath('data.fin', null);

        // Se creó al final, pero el portal y el panel la ponen primero: es la más temprana.
        $this->assertSame('charla-para-familias-nuevas', $this->portal('/v1/matricula/fechas')['items'][0]['id']);
        $this->assertSame('charla-para-familias-nuevas', $this->getJson(self::FECHAS)->json('data.0.clave'));

        // No hay orden a mano.
        $this->postJson(self::FECHAS . '/order', ['ids' => []])->assertNotFound();
    }

    public function test_las_reglas_de_una_fecha(): void
    {
        $this->postJson(self::FECHAS, ['titulo' => 'Matrícula', 'inicio' => '2026-02-10', 'fin' => '2026-02-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('fin');
        $this->postJson(self::FECHAS, ['titulo' => 'Matrícula', 'inicio' => '10/02/2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('inicio');

        // Al cambiar solo «hasta», se compara con el «desde» guardado.
        $fecha = FechaMatricula::where('clave', 'f3')->first();
        $this->putJson(self::FECHAS . "/{$fecha->id}", ['fin' => '2026-01-01'])->assertUnprocessable()->assertJsonValidationErrors('fin');
        $this->putJson(self::FECHAS . "/{$fecha->id}", ['fin' => '2026-03-01'])->assertOk()->assertJsonPath('data.fin', '2026-03-01');
    }

    // ───────── Preguntas ─────────

    public function test_las_preguntas_frecuentes(): void
    {
        $id = $this->postJson(self::PREGUNTAS, [
            'pregunta'  => '¿Hay movilidad escolar?',
            'respuesta' => 'No. Cada familia organiza el traslado.',
            'estado'    => 'publicado',
        ])->assertCreated()->json('data.id');

        $this->assertSame('¿Hay movilidad escolar?', collect($this->portal('/v1/matricula/preguntas'))->last()['pregunta']);

        $this->putJson(self::PREGUNTAS . "/{$id}", ['respuesta' => str_repeat('r', 601)])
            ->assertUnprocessable()->assertJsonValidationErrors('respuesta');

        $ids = Pregunta::orderBy('orden')->pluck('id')->all();
        $this->postJson(self::PREGUNTAS . '/order', ['ids' => array_reverse($ids)])->assertOk();
        $this->assertSame('¿Hay movilidad escolar?', $this->portal('/v1/matricula/preguntas')[0]['pregunta']);
    }

    // ───────── Grados ─────────

    public function test_las_opciones_de_grado(): void
    {
        $primaria = GrupoGrados::where('nivel', 'Primaria')->first();

        $this->putJson(self::GRADOS . "/{$primaria->id}", ['opciones' => ['1.º', '2.º']])->assertOk();
        $this->assertSame(['1.º', '2.º'], collect($this->portal('/v1/grados'))->firstWhere('nivel', 'Primaria')['opciones']);

        // El nivel no se repite: es como el portal reconoce cada grupo.
        $this->postJson(self::GRADOS, ['nivel' => 'Primaria', 'opciones' => ['x']])->assertUnprocessable()->assertJsonValidationErrors('nivel');
        $this->putJson(self::GRADOS . "/{$primaria->id}", ['nivel' => 'Primaria'])->assertOk();

        $this->putJson(self::GRADOS . "/{$primaria->id}", ['opciones' => []])->assertUnprocessable()->assertJsonValidationErrors('opciones');
        $this->putJson(self::GRADOS . "/{$primaria->id}", ['opciones' => array_fill(0, 9, 'x')])->assertUnprocessable()->assertJsonValidationErrors('opciones');
        $this->putJson(self::GRADOS . "/{$primaria->id}", ['opciones' => ['1.º', '1.º']])->assertUnprocessable()->assertJsonValidationErrors('opciones.1');
        $this->putJson(self::GRADOS . "/{$primaria->id}", ['opciones' => [str_repeat('g', 25)]])->assertUnprocessable()->assertJsonValidationErrors('opciones.0');

        $id = $this->postJson(self::GRADOS, ['nivel' => 'Talleres', 'opciones' => ['Música'], 'estado' => 'publicado'])->assertCreated()->json('data.id');
        $this->assertSame('Talleres', collect($this->portal('/v1/grados'))->last()['nivel']);
        $this->deleteJson(self::GRADOS . "/{$id}")->assertOk();
        $this->assertCount(3, $this->portal('/v1/grados'));
    }

    public function test_rrhh_no_edita_matricula(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        foreach ([self::PROCESOS, self::VACANTES, self::FECHAS, self::PREGUNTAS, self::GRADOS, self::FICHAS . 'matricula-cabecera'] as $ruta) {
            $this->getJson($ruta)->assertForbidden();
        }
    }
}
