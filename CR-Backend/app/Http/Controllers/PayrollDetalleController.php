<?php
namespace App\Http\Controllers;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Traits\ListadoPaginado;

class PayrollDetalleController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /payroll-detalles?planilla_id=&page=&size=&search=
     */
    public function index(Request $request)
    {
        $query = PayrollDetalle::with('paymentConcept');

        if ($request->filled('planilla_id')) {
            $query->where('planilla_id', $request->planilla_id);
        }

        return $this->responderListado(
            $request,
            $query,
            ['descripcion', 'paymentConcept.nombre'],
            // Los tres totales del pie, sobre TODAS las líneas y no sobre la
            // página: si se sumaran las diez que se están viendo, una planilla
            // con doce conceptos enseñaría un neto que no es el que se paga.
            fn (Builder $lineas) => $this->totalesPorTipo($lineas)
        );
    }

    /**
     * Lo que suma, lo que resta y lo que pone el colegio, en una consulta.
     *
     *   sumanAlSueldo   ingresos (todo lo que no es descuento, adelanto ni
     *                   aportación): básico, bonificaciones, asignación...
     *   restanDelSueldo descuentos y adelantos, lo que se le quita al neto.
     *   aportaciones    EsSalud y SCTR: los paga el empleador, no salen del
     *                   sueldo del trabajador y por eso van aparte.
     *
     * reorder() quita el ORDER BY, que en una suma no pinta nada.
     */
    private function totalesPorTipo(Builder $lineas): array
    {
        $porTipo = function (array $tipos, bool $dentro) use ($lineas) {
            $consulta = (clone $lineas)->reorder();
            $consulta->whereHas(
                'paymentConcept',
                fn (Builder $c) => $dentro ? $c->whereIn('tipo', $tipos) : $c->whereNotIn('tipo', $tipos)
            );

            return (float) $consulta->sum('monto_calculado');
        };

        return [
            'sumanAlSueldo'   => $porTipo(['descuento', 'adelanto', 'aportacion'], false),
            'restanDelSueldo' => $porTipo(['descuento', 'adelanto'], true),
            'aportaciones'    => $porTipo(['aportacion'], true),
        ];
    }

    /**
     * POST /payroll-detalles
     *
     * La línea se puede escribir de dos maneras, y las dos siguen valiendo:
     *
     *   - En soles:      monto_calculado = 125
     *   - Con su regla:  calculo = 'porcentaje', valor = 5
     *
     * La segunda es la que evita que RR.HH. tenga que irse a Conceptos de
     * Pago a cambiar el catálogo —que le toca el valor a TODO el colegio—
     * solo para ajustarle un porcentaje a una persona.
     *
     * Cuando viene la regla, el monto lo saca el servidor: si lo mandara el
     * navegador, el porcentaje guardado y los soles cobrados podrían decir
     * cosas distintas.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'planilla_id'        => 'required|exists:planilla,id',
            'payment_concept_id' => 'required|exists:payment_concepts,id',
            'monto_calculado'    => 'required_without:calculo|nullable|numeric|min:0',
            'calculo'            => 'nullable|in:fijo,porcentaje',
            'valor'              => 'required_with:calculo|nullable|numeric|min:0',
            'descripcion'        => 'nullable|string|max:255',
            'estado'             => 'nullable|string|max:45',
        ]);

        $planilla = Planilla::findOrFail($datos['planilla_id']);

        $nuevo = [
            'planilla_id'        => $datos['planilla_id'],
            'payment_concept_id' => $datos['payment_concept_id'],
            'monto_calculado'    => $this->montoEnSoles($datos, $planilla),
            'calculo'            => $datos['calculo'] ?? null,
            'valor'             => isset($datos['calculo']) ? $datos['valor'] : null,
            'descripcion'        => $datos['descripcion'] ?? null,
        ];

        // `estado` solo si viene: la columna no admite null y tiene su propio
        // valor por defecto en la tabla.
        if (isset($datos['estado'])) {
            $nuevo['estado'] = $datos['estado'];
        }

        $detalle = PayrollDetalle::create($nuevo);

        $planilla->recalcularTotal();

        return response()->json(['success' => true, 'data' => $detalle->load('paymentConcept')], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => PayrollDetalle::with('paymentConcept')->findOrFail($id)]);
    }

    /**
     * PUT /payroll-detalles/{id}
     *
     * Mismo trato que al crear. El concepto no se cambia acá: para eso se
     * quita la línea y se pone otra, que es lo que dice la pantalla.
     */
    public function update(Request $request, string $id)
    {
        $detalle = PayrollDetalle::findOrFail($id);

        $datos = $request->validate([
            'monto_calculado' => 'sometimes|nullable|numeric|min:0',
            'calculo'         => 'nullable|in:fijo,porcentaje',
            'valor'           => 'required_with:calculo|nullable|numeric|min:0',
            'descripcion'     => 'nullable|string|max:255',
            'estado'          => 'nullable|string|max:45',
        ]);

        $planilla = $detalle->planilla;
        $cambios  = [];

        if (isset($datos['calculo'])) {
            $cambios['calculo']         = $datos['calculo'];
            $cambios['valor']           = $datos['valor'];
            $cambios['monto_calculado'] = $this->montoEnSoles($datos, $planilla);
        } elseif (array_key_exists('monto_calculado', $datos)) {
            // Se escribió en soles: se borra la regla anterior, o la línea
            // diría "5%" al lado de un monto que ya no es ese 5%.
            $cambios['monto_calculado'] = $datos['monto_calculado'];
            $cambios['calculo']         = null;
            $cambios['valor']           = null;
        }

        foreach (['descripcion', 'estado'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $cambios[$campo] = $datos[$campo];
            }
        }

        $detalle->update($cambios);
        $planilla->recalcularTotal();

        return response()->json(['success' => true, 'data' => $detalle->load('paymentConcept')]);
    }

    /**
     * Los soles que van a la boleta.
     *
     * El porcentaje se aplica sobre el sueldo básico de ESA planilla —el que
     * ya viene prorrateado si el trabajador entró a mitad de mes—, así que un
     * 5% es el 5% de lo que realmente cobra ese mes.
     */
    private function montoEnSoles(array $datos, Planilla $planilla): float
    {
        if (! isset($datos['calculo'])) {
            return round((float) ($datos['monto_calculado'] ?? 0), 2);
        }

        if ($datos['calculo'] === 'porcentaje') {
            return round((float) $planilla->sueldo_base * ((float) $datos['valor'] / 100), 2);
        }

        return round((float) $datos['valor'], 2);
    }

    public function destroy(string $id)
    {
        $detalle  = PayrollDetalle::findOrFail($id);
        $planilla = $detalle->planilla;

        $detalle->delete();
        $planilla->recalcularTotal();

        return response()->json(['success' => true, 'data' => ['message' => 'Detalle eliminado.']]);
    }
}