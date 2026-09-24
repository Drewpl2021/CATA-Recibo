<?php

namespace App\Http\Controllers;

use App\Models\Planilla;
use App\Models\PlanillaCorrida;
use App\Traits\GeneraPlanillasEnLote;
use App\Traits\ListadoPaginado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Las corridas de planilla: "Planilla TIC — Septiembre 2026".
 *
 * Es el primer nivel de la pantalla de Planillas. Antes se entraba y salían
 * las 150 filas de los trabajadores seguidas, sin ninguna estructura; ahora
 * se ven las corridas del mes con cuánta gente tiene cada una y cuánto suma,
 * y al abrir una aparecen sus trabajadores.
 *
 * Los totales NO se guardan: se cuentan sobre las planillas de cada corrida.
 * Guardarlos obligaría a recordar actualizarlos en cada sitio que toca un
 * monto —el ajuste de un concepto, un adelanto, mover a alguien de corrida—
 * y basta olvidarse en uno para que la pantalla mienta.
 */
class PlanillaCorridaController extends Controller
{
    use ListadoPaginado;
    use GeneraPlanillasEnLote;

    /**
     * GET /planilla-corridas?mes=&anio=&periodo_id=&page=&size=&search=
     *     &sede_id=&area_id=&cargo_id=&tipo_contrato=&estado_empleado=
     */
    public function index(Request $request)
    {
        $query = PlanillaCorrida::query()
            ->with('periodo')
            ->withCount('planillas as personas')
            ->withSum('planillas as masa_salarial', 'total')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderBy('nombre');

        foreach (['mes', 'anio', 'periodo_id', 'estado'] as $filtro) {
            if ($request->filled($filtro)) {
                $query->where($filtro, $request->input($filtro));
            }
        }

        $this->filtrarPorElTrabajador($request, $query);

        return $this->responderListado(
            $request,
            $query,
            ['nombre', 'observaciones'],
            fn (Builder $filtradas) => $this->cifrasDeCabecera($request, $filtradas)
        );
    }

    /**
     * Deja solo las planillas que tienen gente de esa sede, área o cargo.
     *
     * La corrida no sabe de sedes: las sabe la gente que lleva dentro. Se
     * usa para lo mismo que en el listado de trabajadores —"enséñame lo de
     * Jerusalén"— y, sobre todo, para que el Excel del mes salga con esa
     * misma gente y no con el colegio entero.
     */
    private function filtrarPorElTrabajador(Request $request, Builder $query): void
    {
        $request->validate([
            'sede_id'         => 'nullable|uuid|exists:sedes,id',
            'area_id'         => 'nullable|uuid|exists:areas,id',
            'cargo_id'        => 'nullable|uuid|exists:cargos,id',
            'tipo_contrato'   => 'nullable|in:indeterminado,plazo_fijo,suplencia,practicas',
            'estado_empleado' => 'nullable|in:activo,inactivo',
        ]);

        $delTrabajador = array_filter([
            'sede_id'       => $request->input('sede_id'),
            'area_id'       => $request->input('area_id'),
            'cargo_id'      => $request->input('cargo_id'),
            'tipo_contrato' => $request->input('tipo_contrato'),
            'estado'        => $request->input('estado_empleado'),
        ], fn ($valor) => $valor !== null && $valor !== '');

        if (! $delTrabajador) {
            return;
        }

        $query->whereHas('planillas.empleado', function (Builder $q) use ($delTrabajador) {
            foreach ($delTrabajador as $columna => $valor) {
                $q->where('empleados.' . $columna, $valor);
            }
        });
    }

    /**
     * Lo de arriba de la pantalla, sobre TODAS las corridas que pasan el
     * filtro y no sobre la página: cuántas hay, cuánta gente cubren y cuánto
     * suman. Va aparte el bolsón de los que no están en ninguna.
     */
    private function cifrasDeCabecera(Request $request, Builder $filtradas): array
    {
        $ids = (clone $filtradas)->reorder()->pluck('id');

        $sueltas = Planilla::whereNull('corrida_id');
        foreach (['mes', 'anio'] as $filtro) {
            if ($request->filled($filtro)) {
                $sueltas->where($filtro, $request->input($filtro));
            }
        }

        return [
            'total'         => $ids->count(),
            'personas'      => Planilla::whereIn('corrida_id', $ids)->count(),
            'masaSalarial'  => (float) Planilla::whereIn('corrida_id', $ids)->sum('total'),
            // El grupo "Sin agrupar" de la pantalla: las planillas que no
            // pertenecen a ninguna corrida. Son las de siempre, que se
            // quedaron como estaban al aparecer las corridas.
            'sinAgrupar'    => (int) $sueltas->count(),
            'sinAgruparMasa' => (float) (clone $sueltas)->sum('total'),
        ];
    }

    /**
     * POST /planilla-corridas
     *
     * Crea la corrida y, si se le indica un grupo, le arma dentro las
     * planillas de esa gente en la misma llamada. Es lo que hace el botón
     * "Generar planillas": antes había que crear el periodo, ir a otra
     * pantalla y generar, y el resultado quedaba suelto.
     *
     * Sin `generar`, la corrida nace vacía y se le van metiendo trabajadores
     * a mano — que es lo que hace falta para una planilla de pocos.
     */
    public function store(Request $request)
    {
        $datos = $request->validate(array_merge([
            'nombre'        => 'required|string|max:100',
            // Un mes suelto, o varios de golpe. Lo segundo es lo que pasa
            // cuando se arranca el año escolar: se abren las planillas de
            // todos sus meses de una vez en vez de volver mes a mes a
            // escribir el mismo nombre.
            'mes'           => 'required_without:meses|integer|min:1|max:12',
            'anio'          => 'required_without:meses|integer|min:2000',
            'meses'         => 'sometimes|array|min:1|max:24',
            'meses.*'       => ['regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'periodo_id'    => 'nullable|uuid|exists:periodos,id',
            'observaciones' => 'nullable|string|max:255',
            // Con esto, además de crearla, se le arma la planilla al grupo.
            'generar'       => 'sometimes|boolean',
        ], $this->reglasDelGrupo()));

        // Varios meses: se atiende aparte porque la respuesta es otra —una
        // lista de planillas y un resumen de todas, no una sola.
        if ($request->filled('meses')) {
            return $this->crearVariosMeses($request, $datos);
        }

        $repetida = PlanillaCorrida::where('nombre', $datos['nombre'])
            ->where('mes', $datos['mes'])
            ->where('anio', $datos['anio'])
            ->exists();

        if ($repetida) {
            return response()->json([
                'success' => false,
                'message' => "Ya hay una planilla llamada \"{$datos['nombre']}\" en ese mes. Ponle otro nombre para poder distinguirlas.",
            ], 422);
        }

        $corrida = PlanillaCorrida::create([
            'nombre'        => $datos['nombre'],
            'mes'           => $datos['mes'],
            'anio'          => $datos['anio'],
            'periodo_id'    => $datos['periodo_id'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
        ]);

        if (!$request->boolean('generar')) {
            return response()->json(['success' => true, 'data' => $this->conCifras($corrida)], 201);
        }

        $empleados = $this->empleadosDelGrupo($request);

        if ($empleados->isEmpty()) {
            // La corrida se queda creada y vacía a propósito: el usuario ya
            // le puso nombre, y borrársela por un filtro que no encontró a
            // nadie le haría escribirlo todo otra vez.
            return response()->json([
                'success' => true,
                'data'    => [
                    'corrida' => $this->conCifras($corrida),
                    'resumen' => ['generadas' => 0, 'omitidas' => 0, 'evaluados' => 0],
                    'detalle' => [],
                    'aviso'   => 'La planilla se creó vacía: ningún trabajador activo coincide con el grupo indicado.',
                ],
            ], 201);
        }

        $resultado = $this->generarLote(
            $empleados,
            (int) $datos['mes'],
            (int) $datos['anio'],
            $datos['periodo_id'] ?? null,
            $corrida
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'corrida' => $this->conCifras($corrida->fresh()),
                'resumen' => [
                    'generadas' => $resultado['generadas'],
                    'omitidas'  => $resultado['omitidas'],
                    'evaluados' => $resultado['evaluados'],
                ],
                'detalle' => $resultado['detalle'],
            ],
        ], 201);
    }

    /**
     * Abre la planilla de VARIOS meses de una vez, con el mismo nombre.
     *
     * Es lo que hace falta al arrancar un periodo: "Planilla Docentes" de
     * marzo a diciembre son diez planillas, y abrirlas una por una es
     * escribir diez veces lo mismo.
     *
     * Cada mes es su propia planilla porque el pago lo es: quien entró en
     * agosto no cobra marzo, y el bono de julio no es el de octubre. Lo que
     * se comparte es el nombre y a quiénes alcanza.
     *
     * Un mes que ya tenía una planilla con ese nombre se salta en vez de
     * fallar: así se puede relanzar para completar los que falten sin tocar
     * los que ya están.
     */
    private function crearVariosMeses(Request $request, array $datos)
    {
        $generar  = $request->boolean('generar');
        $empleados = $generar ? $this->empleadosDelGrupo($request) : collect();

        $creadas   = [];
        $omitidas  = [];
        $porMes    = [];
        $detalle   = [];
        $generadas = 0;
        $saltadas  = 0;

        foreach ($request->input('meses') as $mesAnio) {
            [$anio, $mes] = array_map('intval', explode('-', $mesAnio));

            $repetida = PlanillaCorrida::where('nombre', $datos['nombre'])
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->exists();

            if ($repetida) {
                $omitidas[] = ['mes' => $mes, 'anio' => $anio, 'motivo' => 'Ya existe una planilla con ese nombre en ese mes'];
                continue;
            }

            $corrida = PlanillaCorrida::create([
                'nombre'        => $datos['nombre'],
                'mes'           => $mes,
                'anio'          => $anio,
                'periodo_id'    => $datos['periodo_id'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            $resultado = ['generadas' => 0, 'omitidas' => 0, 'evaluados' => 0, 'detalle' => []];

            if ($generar && $empleados->isNotEmpty()) {
                $resultado = $this->generarLote($empleados, $mes, $anio, $datos['periodo_id'] ?? null, $corrida);
                $generadas += $resultado['generadas'];
                $saltadas  += $resultado['omitidas'];
            }

            // Mes a mes, porque el resultado no es el mismo en todos: quien
            // entró en agosto sale omitido en marzo y generado en septiembre.
            $porMes[] = [
                'mes'       => $mes,
                'anio'      => $anio,
                'generadas' => $resultado['generadas'],
                'omitidas'  => $resultado['omitidas'],
                'evaluados' => $resultado['evaluados'],
            ];

            // El detalle trabajador por trabajador solo cuando es un mes: con
            // diez meses son mil quinientas filas que nadie va a leer, y el
            // resumen de arriba ya dice lo que hace falta.
            if (count($request->input('meses')) === 1) {
                $detalle = $resultado['detalle'];
            }

            $creadas[] = $this->conCifras($corrida->fresh());
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'corridas' => $creadas,
                'resumen'  => [
                    'planillas'        => count($creadas),
                    'mesesOmitidos'    => count($omitidas),
                    'generadas'        => $generadas,
                    'omitidas'         => $saltadas,
                ],
                'porMes'        => $porMes,
                'detalle'       => $detalle,
                'mesesOmitidos' => $omitidas,
            ],
        ], 201);
    }

    public function show(string $id)
    {
        $corrida = PlanillaCorrida::with('periodo')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->conCifras($corrida) + [
                'conceptos_aplicados' => $this->conceptosAplicados($corrida),
            ],
        ]);
    }

    /**
     * Qué conceptos ya tiene puestos la gente de esta planilla, y a cuántos.
     *
     * Sirve para marcar con un visto los que ya están cuando se va a aplicar
     * otro: sin eso, la única manera de saber si el diezmo ya se aplicó era
     * entrar trabajador por trabajador.
     *
     * Va solo en show() y no en conCifras(): la lista de planillas pinta
     * decenas de corridas, y una consulta agregada por cada una sería el
     * clásico N+1 para un dato que ahí no se usa.
     *
     * @return array<string,int>  id del concepto => a cuántos trabajadores
     */
    private function conceptosAplicados(PlanillaCorrida $corrida): array
    {
        return \Illuminate\Support\Facades\DB::table('payroll_detalles as d')
            ->join('planilla as p', 'p.id', '=', 'd.planilla_id')
            ->where('p.corrida_id', $corrida->id)
            ->groupBy('d.payment_concept_id')
            ->selectRaw('d.payment_concept_id, COUNT(DISTINCT p.id) as cuantos')
            ->pluck('cuantos', 'payment_concept_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    public function update(Request $request, string $id)
    {
        $corrida = PlanillaCorrida::findOrFail($id);

        $datos = $request->validate([
            'nombre'        => 'sometimes|string|max:100',
            'periodo_id'    => 'nullable|uuid|exists:periodos,id',
            'estado'        => 'sometimes|in:abierta,cerrada',
            'observaciones' => 'nullable|string|max:255',
        ]);

        // El mes y el año no se cambian: sus planillas ya están armadas para
        // ese mes, y moverlas de fecha sin recalcularlas dejaría a gente
        // cobrando un sueldo prorrateado de un mes que no es.
        if ($request->hasAny(['mes', 'anio'])) {
            return response()->json([
                'success' => false,
                'message' => 'El mes de una planilla no se cambia. Si te equivocaste de mes, crea la planilla del mes correcto y mueve a los trabajadores.',
            ], 422);
        }

        if (isset($datos['nombre']) && $datos['nombre'] !== $corrida->nombre) {
            $repetida = PlanillaCorrida::where('nombre', $datos['nombre'])
                ->where('mes', $corrida->mes)
                ->where('anio', $corrida->anio)
                ->where('id', '!=', $corrida->id)
                ->exists();

            if ($repetida) {
                return response()->json([
                    'success' => false,
                    'message' => "Ya hay una planilla llamada \"{$datos['nombre']}\" en ese mes.",
                ], 422);
            }
        }

        $corrida->update($datos);

        return response()->json(['success' => true, 'data' => $this->conCifras($corrida->fresh())]);
    }

    /**
     * DELETE /planilla-corridas/{id}
     *
     * Borra la agrupación, NO las planillas: sus trabajadores vuelven a
     * "Sin agrupar" con sus montos intactos. Llevarse por delante el pago de
     * doce personas porque alguien borró una carpeta sería imperdonable.
     */
    public function destroy(string $id)
    {
        $corrida = PlanillaCorrida::findOrFail($id);

        if ($corrida->estaCerrada()) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Esta planilla está cerrada. Ábrela primero si de verdad quieres eliminarla.'],
            ], 409);
        }

        $devueltas = Planilla::where('corrida_id', $corrida->id)->count();
        $corrida->delete();

        return response()->json([
            'success' => true,
            'data'    => [
                'message' => $devueltas > 0
                    ? "Planilla eliminada. Sus {$devueltas} trabajador(es) pasaron a \"Sin agrupar\"; no se borró ningún pago."
                    : 'Planilla eliminada.',
            ],
        ]);
    }

    /**
     * POST /planilla-corridas/{id}/generar
     *
     * Le arma la planilla a más gente dentro de una corrida que ya existe.
     * Al que ya la tiene de ese mes se le salta, así que darle dos veces no
     * duplica ni pisa lo que se haya ajustado a mano.
     */
    public function generar(Request $request, string $id)
    {
        $corrida = PlanillaCorrida::findOrFail($id);

        if ($corrida->estaCerrada()) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Esta planilla está cerrada: no se le pueden agregar trabajadores.'],
            ], 409);
        }

        $request->validate($this->reglasDelGrupo());

        $empleados = $this->empleadosDelGrupo($request);

        if ($empleados->isEmpty()) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Ningún trabajador activo coincide con el grupo indicado.'],
            ], 422);
        }

        $resultado = $this->generarLote(
            $empleados,
            (int) $corrida->mes,
            (int) $corrida->anio,
            $corrida->periodo_id,
            $corrida
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'corrida' => $this->conCifras($corrida->fresh()),
                'mes'     => $corrida->mes,
                'anio'    => $corrida->anio,
                'resumen' => [
                    'generadas' => $resultado['generadas'],
                    'omitidas'  => $resultado['omitidas'],
                    'evaluados' => $resultado['evaluados'],
                ],
                'detalle' => $resultado['detalle'],
            ],
        ]);
    }

    /**
     * POST /planilla-corridas/{id}/mover
     *
     * Mete planillas que ya existen dentro de esta corrida. Es lo que saca a
     * las que están en "Sin agrupar" —las de antes de que existieran las
     * corridas— sin tener que volver a generarlas.
     *
     * Solo entran las del MISMO mes de la corrida: una planilla de agosto
     * dentro de la corrida de septiembre haría que los totales del mes no
     * cuadren con la suma de sus filas.
     */
    public function mover(Request $request, string $id)
    {
        $corrida = PlanillaCorrida::findOrFail($id);

        if ($corrida->estaCerrada()) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Esta planilla está cerrada: no se le pueden mover trabajadores.'],
            ], 409);
        }

        $request->validate([
            'planilla_ids'   => 'required|array|min:1',
            'planilla_ids.*' => 'uuid|exists:planilla,id|distinct',
        ]);

        $planillas = Planilla::whereIn('id', $request->planilla_ids)->get();

        $deOtroMes = $planillas->filter(
            fn ($p) => (int) $p->mes !== (int) $corrida->mes || (int) $p->anio !== (int) $corrida->anio
        );

        if ($deOtroMes->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "No se pueden mover {$deOtroMes->count()} planilla(s): son de otro mes que el de esta planilla.",
            ], 422);
        }

        $movidas = DB::transaction(function () use ($planillas, $corrida) {
            $n = 0;
            foreach ($planillas as $planilla) {
                if ($planilla->corrida_id === $corrida->id) {
                    continue;
                }
                $planilla->update(['corrida_id' => $corrida->id]);
                $n++;
            }
            return $n;
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'corrida' => $this->conCifras($corrida->fresh()),
                'movidas' => $movidas,
                'yaEstaban' => $planillas->count() - $movidas,
            ],
        ]);
    }

    /**
     * POST /planilla-corridas/sacar
     *
     * Saca planillas de su corrida y las deja en "Sin agrupar". No borra
     * nada: solo suelta la agrupación.
     */
    public function sacar(Request $request)
    {
        $request->validate([
            'planilla_ids'   => 'required|array|min:1',
            'planilla_ids.*' => 'uuid|exists:planilla,id|distinct',
        ]);

        $cerradas = Planilla::whereIn('id', $request->planilla_ids)
            ->whereHas('corrida', fn ($q) => $q->where('estado', 'cerrada'))
            ->count();

        if ($cerradas > 0) {
            return response()->json([
                'success' => false,
                'message' => "{$cerradas} de esas planillas están en una planilla cerrada. Ábrela primero.",
            ], 409);
        }

        $sacadas = Planilla::whereIn('id', $request->planilla_ids)
            ->whereNotNull('corrida_id')
            ->update(['corrida_id' => null]);

        return response()->json([
            'success' => true,
            'data'    => ['sacadas' => $sacadas],
        ]);
    }

    /** La corrida con sus dos números al lado, como los pide la pantalla. */
    private function conCifras(PlanillaCorrida $corrida): array
    {
        return $corrida->toArray() + [
            'personas'      => (int) $corrida->planillas()->count(),
            'masa_salarial' => (float) $corrida->planillas()->sum('total'),
        ];
    }
}
