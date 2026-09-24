<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Empleado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Traits\ListadoPaginado;

class ContratoController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /contratos?empleado_id=&estado=&incluir_inactivos=&page=&size=&search=
     */
    public function index(Request $request)
    {
        $query = Contrato::with('empleado', 'documentos');

        if ($request->filled('empleado_id')) {
            $query->where('empleado_id', $request->empleado_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if (!$request->has('incluir_inactivos')) {
            $query->where('estado_registro', 'activo');
        }

        return $this->responderListado(
            $request,
            $query->orderBy('fecha_inicio', 'desc'),
            ['empleado.nombre', 'empleado.apellido', 'empleado.dni', 'tipo_contrato'],
            // Las cifras de la cabecera: se cuentan sobre todo lo que pasa el
            // filtro, no sobre la página que se está viendo.
            fn (Builder $filtrada) => $this->conteoPorEstado($filtrada, 'estado', ['vigentes' => 'vigente', 'finalizados' => 'finalizado'])
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'empleado_id'   => 'required|uuid|exists:empleados,id',
            'tipo_contrato' => 'required|in:indeterminado,plazo_fijo,suplencia,practicas',
            'fecha_inicio'  => 'required|date',
            'fecha_fin'     => 'nullable|date|after_or_equal:fecha_inicio',
            'observaciones' => 'nullable|string',
        ]);

        $contratoVigente = Contrato::where('empleado_id', $request->empleado_id)
            ->where('estado', 'vigente')
            ->where('estado_registro', 'activo')
            ->first();

        if ($contratoVigente) {
            $fechaFinAnterior = Carbon::parse($request->fecha_inicio)->subDay()->toDateString();

            $contratoVigente->update([
                'estado'     => 'finalizado',
                'fecha_fin'  => $contratoVigente->fecha_fin ?? $fechaFinAnterior,
                'motivo_fin' => $contratoVigente->motivo_fin ?? 'otro',
            ]);
        }

        $contrato = Contrato::create([
            'empleado_id'   => $request->empleado_id,
            'tipo_contrato' => $request->tipo_contrato,
            'fecha_inicio'  => $request->fecha_inicio,
            'fecha_fin'     => $request->fecha_fin,
            'observaciones' => $request->observaciones,
            'estado'        => 'vigente',
        ]);

        $this->ponerAlDiaLaFicha($request->empleado_id);

        $contrato->load('empleado', 'documentos');

        return response()->json(['success' => true, 'data' => $contrato], 201);
    }

    public function show(string $id)
    {
        $contrato = Contrato::with('empleado', 'documentos')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $contrato]);
    }

    public function update(Request $request, string $id)
    {
        $contrato = Contrato::findOrFail($id);

        $datos = $request->validate([
            'tipo_contrato' => 'sometimes|in:indeterminado,plazo_fijo,suplencia,practicas',
            'fecha_inicio'  => 'sometimes|date',
            'fecha_fin'     => 'nullable|date|after_or_equal:fecha_inicio',
            'estado'        => 'sometimes|in:vigente,finalizado,renovado',
            'motivo_fin'    => 'nullable|in:renuncia,despido,fin_contrato_plazo,fin_año_escolar,no_renovacion,jubilacion,otro',
            'observaciones' => 'nullable|string',
        ]);

        $contrato->update($datos);
        $this->ponerAlDiaLaFicha($contrato->empleado_id);

        $contrato->load('empleado', 'documentos');

        return response()->json(['success' => true, 'data' => $contrato]);
    }

    public function destroy(string $id)
    {
        $contrato = Contrato::findOrFail($id);
        $contrato->update(['estado_registro' => 'inactivo']);
        $this->ponerAlDiaLaFicha($contrato->empleado_id);

        return response()->json(['success' => true, 'data' => ['message' => 'Contrato desactivado correctamente.']]);
    }

    /**
     * Copia a la ficha el tipo del contrato que quede vigente.
     *
     * El contrato es el que manda —es el papel que se firma—, pero la ficha
     * guarda una copia del tipo, y hay pantallas que leen esa copia: los
     * filtros de personal y de planilla, y la columna del reporte de
     * planilla. Si no se copia, al renovar acá la ficha se queda con el tipo
     * viejo y el sistema vuelve a decir dos cosas distintas de la misma
     * persona —lo mismo que pasaba al revés cuando solo se editaba la ficha—.
     */
    private function ponerAlDiaLaFicha(?string $empleadoId): void
    {
        if (! $empleadoId) {
            return;
        }

        $vigente = Contrato::where('empleado_id', $empleadoId)
            ->where('estado', 'vigente')
            ->where('estado_registro', 'activo')
            ->latest('fecha_inicio')
            ->first();

        // Sin contrato vigente no se toca nada: la ficha conserva lo último
        // que se supo de él, que es mejor que dejarla en blanco.
        if ($vigente) {
            Empleado::where('id', $empleadoId)->update(['tipo_contrato' => $vigente->tipo_contrato]);
        }
    }
}