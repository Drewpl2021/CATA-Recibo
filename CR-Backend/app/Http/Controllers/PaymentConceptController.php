<?php
namespace App\Http\Controllers;
use App\Models\PaymentConcept;
use App\Models\Empleado;
use App\Models\Planilla;
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
        $request->validate([
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
        $concept = PaymentConcept::create($request->all());
        return response()->json(['success' => true, 'data' => $concept], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => PaymentConcept::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $concept = PaymentConcept::findOrFail($id);
        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:150', Rule::unique('payment_concepts', 'nombre')->ignore($id)],
            'tipo' => 'sometimes|in:bonificacion,descuento,aportacion,adelanto',
            'calculo' => 'nullable|in:fijo,porcentaje|required_if:aplica_a_todos,true',
            'valor' => 'nullable|numeric|min:0|required_if:aplica_a_todos,true',
            'descripcion' => 'nullable|string|max:255',
            'aplica_a_todos' => 'nullable|boolean',
        ]);
        $concept->update($request->all());
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

        if (empty($concepto->calculo) || is_null($concepto->valor)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => "\"{$concepto->nombre}\" no tiene calculo/valor definidos en el catálogo, así que no hay un monto único para aplicar al grupo. Agrégalo manualmente a cada empleado (POST /payroll-detalles), o primero defínele un calculo/valor en este concepto."],
            ], 422);
        }

        $request->validate([
            'mes'             => 'required|integer|min:1|max:12',
            'anio'            => 'required|integer|min:2000',
            'empleado_ids'    => 'required|array|min:1',
            'empleado_ids.*'  => 'uuid|exists:empleados,id|distinct',
        ]);

        $aplicadas = 0;
        $omitidas  = 0;
        $detalle   = [];

        foreach ($request->empleado_ids as $empleadoId) {
            $empleado = Empleado::find($empleadoId);
            $nombreCompleto = trim($empleado->nombre . ' ' . $empleado->apellido);

            $planilla = Planilla::where('empleado_id', $empleadoId)
                ->where('mes', $request->mes)
                ->where('anio', $request->anio)
                ->first();

            if (!$planilla) {
                $omitidas++;
                $detalle[] = ['empleado' => $nombreCompleto, 'estado' => 'omitida', 'motivo' => 'No tiene planilla de este mes/año'];
                continue;
            }

            $monto = $concepto->calculo === 'porcentaje'
                ? (float) $planilla->sueldo_base * ((float) $concepto->valor / 100)
                : (float) $concepto->valor;
            $monto = round($monto, 2);

            PayrollDetalle::updateOrCreate(
                ['planilla_id' => $planilla->id, 'payment_concept_id' => $concepto->id],
                ['monto_calculado' => $monto, 'descripcion' => 'Aplicado a un grupo de empleados']
            );
            $planilla->recalcularTotal();

            $aplicadas++;
            $detalle[] = ['empleado' => $nombreCompleto, 'estado' => 'aplicada', 'monto' => $monto];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'concepto' => $concepto->nombre,
                'mes'      => (int) $request->mes,
                'anio'     => (int) $request->anio,
                'resumen'  => ['aplicadas' => $aplicadas, 'omitidas' => $omitidas],
                'detalle'  => $detalle,
            ],
        ]);
    }
}