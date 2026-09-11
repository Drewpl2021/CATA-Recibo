<?php
namespace App\Http\Controllers;
use App\Models\PaymentConcept;
use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\PlanillaCorrida;
use App\Models\PayrollDetalle;
use App\Traits\CalculaConceptosPlanilla;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\ListadoPaginado;

class PaymentConceptController extends Controller
{
    use ListadoPaginado;
    use CalculaConceptosPlanilla;

    /**
     * GET /payment-concepts?tipo=bonificacion
     *
     * El filtro va acá dentro, sobre el mismo listado, y no en un endpoint
     * aparte: así el filtrado lo resuelve la base de datos con el índice
     * payment_concepts_tipo_idx y no el navegador trayéndose el catálogo
     * entero para descartarlo en memoria.
     *
     * Se usa filled() y no has(): con has(), un "?tipo=" vacío entraba al
     * where y devolvía cero filas en vez de la lista completa.
     */
    public function index(Request $request)
    {
        $request->validate([
            'tipo' => 'nullable|in:bonificacion,descuento,aportacion,adelanto',
        ]);

        $query = PaymentConcept::query();

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        return $this->responderListado(
            $request,
            // Orden estable: si no se pide nada, la tabla llega siempre igual.
            $query->orderBy('tipo')->orderBy('nombre'),
            ['nombre', 'descripcion']
        );
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:150|unique:payment_concepts,nombre',
            // bonificacion = suma a Ingresos | descuento = resta del neto | aportacion = solo informativo,
            // lo paga el empleador y NO afecta el neto (ej. ESSALUD, SCTR) | adelanto = resta del neto
            // en un bloque aparte de "Descuentos" (ej. adelanto de sueldo/bonificación).
            'tipo' => 'required|in:bonificacion,descuento,aportacion,adelanto',
            // Si aplica_a_todos=true, el sistema necesita saber CÓMO calcular el monto de
            // cada empleado al generar la planilla, así que calculo/valor pasan a ser obligatorios.
            'calculo' => 'nullable|in:fijo,porcentaje|required_if:aplica_a_todos,true',
            'valor' => 'nullable|numeric|min:0|required_if:aplica_a_todos,true',
            'descripcion' => 'nullable|string|max:255',
            'aplica_a_todos' => 'nullable|boolean',
        ]);
        $concept = PaymentConcept::create($datos);
        return response()->json(['success' => true, 'data' => $concept], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => PaymentConcept::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $concept = PaymentConcept::findOrFail($id);
        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:150', Rule::unique('payment_concepts', 'nombre')->ignore($id)],
            'tipo' => 'sometimes|in:bonificacion,descuento,aportacion,adelanto',
            'calculo' => 'nullable|in:fijo,porcentaje|required_if:aplica_a_todos,true',
            'valor' => 'nullable|numeric|min:0|required_if:aplica_a_todos,true',
            'descripcion' => 'nullable|string|max:255',
            'aplica_a_todos' => 'nullable|boolean',
        ]);
        $concept->update($datos);
        return response()->json(['success' => true, 'data' => $concept]);
    }

    public function destroy(string $id)
    {
        PaymentConcept::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Concepto eliminado.']]);
    }

    /**
     * "Nivel 2 — por grupo": aplica este concepto a una lista concreta de empleados
     * (ni a todos, ni a uno solo), sobre la planilla que ya tengan de ese mes/año.
     * Usa el mismo calculo/valor del catálogo para todos los del grupo (si necesitas
     * un monto distinto por persona, se agrega manual con POST /payroll-detalles).
     */
    public function aplicarAGrupo(Request $request, string $id)
    {
        $concepto = PaymentConcept::findOrFail($id);

        // Blindaje: los conceptos de cálculo especial (pensión/EsSalud/Renta 5ta) nunca
        // se aplican por un mecanismo genérico, ni siquiera a un grupo — siempre dependen
        // del sistema de pensiones/AFP/historial de CADA empleado individualmente.
        if (in_array($concepto->nombre, self::CONCEPTOS_CON_CALCULO_ESPECIAL, true)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => "\"{$concepto->nombre}\" se calcula automáticamente por empleado y no se puede aplicar por grupo."],
            ], 422);
        }

        /*
         * La regla con la que se aplica: la que venga en la petición, y si no
         * la del catálogo.
         *
         * Antes solo se podían aplicar a un grupo los conceptos que ya
         * traían calculo/valor puestos, que en el catálogo real son tres de
         * veintidós: al resto había que agregárselo de uno en uno a cada
         * planilla. Ahora se manda el monto en el momento —que además es lo
         * normal: un préstamo o un adelanto no tienen un valor "de catálogo"—
         * y el del catálogo queda como propuesta.
         */
        $calculo = $request->input('calculo', $concepto->calculo);
        $valor   = $request->input('valor', $concepto->valor);

        if (empty($calculo) || is_null($valor)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => "\"{$concepto->nombre}\" no tiene un monto por defecto en el catálogo. Indica cuánto se le aplica a cada trabajador."],
            ], 422);
        }

        // Se puede decir a quiénes de dos maneras: la lista de siempre, o
        // "a los de esta planilla". Lo segundo es lo natural cuando ya
        // agrupaste a la gente: sin esto había que volver a re-seleccionar por
        // área a las mismas personas que ya estaban juntas —y si entretanto
        // moviste a alguien a mano, esa selección ya no coincide con lo que
        // hay dentro.
        $request->validate([
            'corrida_id'      => 'required_without:empleado_ids|uuid|exists:planilla_corridas,id',
            // La regla con la que se aplica, si no se usa la del catálogo.
            'calculo'         => 'sometimes|in:fijo,porcentaje',
            'valor'           => 'sometimes|numeric|min:0',
            'mes'             => 'required_without:corrida_id|integer|min:1|max:12',
            'anio'            => 'required_without:corrida_id|integer|min:2000',
            'empleado_ids'    => 'required_without:corrida_id|array|min:1',
            'empleado_ids.*'  => 'uuid|exists:empleados,id|distinct',
        ]);

        $mes  = (int) $request->mes;
        $anio = (int) $request->anio;
        $empleadoIds = $request->input('empleado_ids', []);
        $corrida = null;

        if ($request->filled('corrida_id')) {
            $corrida = PlanillaCorrida::findOrFail($request->corrida_id);

            if ($corrida->estaCerrada()) {
                return response()->json([
                    'success' => false,
                    'data'    => ['message' => "La planilla \"{$corrida->nombre}\" está cerrada: ya se pagó y no se le pueden mover las cifras."],
                ], 409);
            }

            // El mes sale de la planilla, no se pregunta: sus filas ya están
            // calculadas para ese mes y pedirlo otra vez solo abre la puerta
            // a equivocarse.
            $mes  = (int) $corrida->mes;
            $anio = (int) $corrida->anio;

            $deLaPlanilla = $corrida->planillas()->pluck('empleado_id')->all();

            if (empty($deLaPlanilla)) {
                return response()->json([
                    'success' => false,
                    'data'    => ['message' => "La planilla \"{$corrida->nombre}\" todavía no tiene trabajadores."],
                ], 422);
            }

            /*
             * Si además se marcaron personas, se aplica SOLO a esas — y solo
             * a las que estén dentro de la planilla.
             *
             * Antes la lista marcada se pisaba con "todos los de la
             * planilla": marcabas a las tres madres para el Subsidio de
             * Maternidad y se lo llevaban los cuarenta. Ahora las dos cosas
             * se cruzan, que es lo que significa "a estos, dentro de esta
             * planilla".
             */
            if (! empty($empleadoIds)) {
                $elegidos = array_values(array_intersect($empleadoIds, $deLaPlanilla));

                if (empty($elegidos)) {
                    return response()->json([
                        'success' => false,
                        'data'    => ['message' => "Ninguno de los trabajadores que marcaste está dentro de \"{$corrida->nombre}\"."],
                    ], 422);
                }

                $empleadoIds = $elegidos;
            } else {
                $empleadoIds = $deLaPlanilla;
            }
        }

        $aplicadas = 0;
        $omitidas  = 0;
        $detalle   = [];

        foreach ($empleadoIds as $empleadoId) {
            $empleado = Empleado::find($empleadoId);
            $nombreCompleto = trim($empleado->nombre . ' ' . $empleado->apellido);

            $planilla = Planilla::where('empleado_id', $empleadoId)
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->first();

            if (!$planilla) {
                $omitidas++;
                $detalle[] = ['empleado' => $nombreCompleto, 'estado' => 'omitida', 'motivo' => 'No tiene planilla de este mes/año'];
                continue;
            }

            $monto = $calculo === 'porcentaje'
                ? (float) $planilla->sueldo_base * ((float) $valor / 100)
                : (float) $valor;
            $monto = round($monto, 2);

            PayrollDetalle::updateOrCreate(
                ['planilla_id' => $planilla->id, 'payment_concept_id' => $concepto->id],
                [
                    'monto_calculado' => $monto,
                    // Se guarda la regla, no solo los soles: así el detalle de
                    // la planilla puede decir "10% del básico" y no un número
                    // suelto del que nadie se acuerda a los tres meses.
                    'calculo' => $calculo,
                    'valor'   => $valor,
                ]
            );
            $planilla->recalcularTotal();

            $aplicadas++;
            $detalle[] = ['empleado' => $nombreCompleto, 'estado' => 'aplicada', 'monto' => $monto];
        }

        // Una sola línea en la auditoría por toda la operación, no una por
        // trabajador: lo que se quiere saber es "quién aplicó el Subsidio de
        // Maternidad a las tres madres", no leer tres filas iguales.
        if ($aplicadas > 0) {
            $monto = $calculo === 'porcentaje'
                ? "{$valor}% del básico"
                : 'S/ ' . number_format((float) $valor, 2);
            $donde = $corrida ? "de \"{$corrida->nombre}\"" : "de {$mes}/{$anio}";

            \App\Models\Auditoria::registrar(
                'aplicó',
                'concepto',
                $concepto->id,
                "Aplicó {$concepto->nombre} ({$monto}) a {$aplicadas} trabajador(es) {$donde}",
                ['aplicadas' => $aplicadas, 'omitidas' => $omitidas, 'calculo' => $calculo, 'valor' => $valor]
            );
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'concepto' => $concepto->nombre,
                'calculo'  => $calculo,
                'valor'    => (float) $valor,
                'mes'      => $mes,
                'anio'     => $anio,
                // Cuando se aplicó a una planilla, se devuelve con sus cifras
                // ya al día: el neto de la planilla cambió y la pantalla tiene
                // que poder reflejarlo sin pedirlo otra vez.
                'corrida'  => $corrida ? [
                    'id'            => $corrida->id,
                    'nombre'        => $corrida->nombre,
                    'personas'      => (int) $corrida->planillas()->count(),
                    'masa_salarial' => (float) $corrida->planillas()->sum('total'),
                ] : null,
                'resumen'  => ['aplicadas' => $aplicadas, 'omitidas' => $omitidas],
                'detalle'  => $detalle,
            ],
        ]);
    }
}