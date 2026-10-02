<?php
namespace App\Http\Controllers;
use App\Models\TipoContrato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\ListadoPaginado;

class TipoContratoController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /tipos-contrato
     *
     * Sin ?page devuelve la lista completa (así la piden los desplegables
     * de los formularios: Nuevo Empleado, Contratos, los filtros de
     * Planillas/Boletas). Con ?page&size la corta el servidor. Ver
     * App\Traits\ListadoPaginado.
     */
    public function index(Request $request)
    {
        return $this->responderListado(
            $request,
            TipoContrato::query()->orderBy('nombre'),
            ['nombre'],
            fn (Builder $filtrada) => $this->conteoPorEstado($filtrada, 'estado', ['activos' => 'activo', 'inactivos' => 'inactivo'])
        );
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre'              => 'required|string|max:100|unique:tipos_contrato,nombre',
            'requiere_fecha_fin'  => 'sometimes|boolean',
            'permite_vacaciones'  => 'sometimes|boolean',
            'estado'              => 'nullable|string|in:activo,inactivo',
        ]);
        $tipo = TipoContrato::create($datos);
        return response()->json(['success' => true, 'data' => $tipo], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => TipoContrato::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $tipo = TipoContrato::findOrFail($id);
        $datos = $request->validate([
            'nombre'              => ['sometimes', 'string', 'max:100', Rule::unique('tipos_contrato', 'nombre')->ignore($id)],
            'requiere_fecha_fin'  => 'sometimes|boolean',
            'permite_vacaciones'  => 'sometimes|boolean',
            'estado'              => 'nullable|string|in:activo,inactivo',
        ]);
        $tipo->update($datos);
        return response()->json(['success' => true, 'data' => $tipo]);
    }

    public function destroy(string $id)
    {
        $tipo = TipoContrato::findOrFail($id);

        // En uso: la FK restrictOnDelete tira el error de la base, pero acá
        // se adelanta con un mensaje que RR.HH. entiende, igual que ya se
        // hace con los demás catálogos referenciados.
        $enUso = \App\Models\Empleado::where('tipo_contrato_id', $id)->exists()
            || \App\Models\Contrato::where('tipo_contrato_id', $id)->exists();
        if ($enUso) {
            return response()->json([
                'success' => false,
                'data' => ['message' => 'Este tipo de contrato está en uso: no se puede eliminar. Puedes desactivarlo en vez de borrarlo.'],
            ], 422);
        }

        $tipo->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Tipo de contrato eliminado.']]);
    }
}
