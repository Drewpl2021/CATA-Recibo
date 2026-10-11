<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Ciclo;
use App\Models\Portal\Universidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * /web-portal/cycles?universidad=… — los ciclos de una universidad de la
 * academia (CicloAcademia, contrato §3.25), de 1 a 12 por universidad.
 *
 * No tienen borrador: se ven cuando se ve su universidad. Precios y fechas
 * solo si están vigentes; si no, vacíos, y el portal no los muestra.
 */
class CiclosController extends ListaDelPortalController
{
    private const TOPE = 12;

    protected function modelo(): string
    {
        return Ciclo::class;
    }

    protected function entidad(): string
    {
        return 'el ciclo';
    }

    protected function tieneEstado(): bool
    {
        return false;
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/propuesta-educativa/academia'];
    }

    /**
     * Los de una universidad: ?universidad=id, o la del ciclo que se crea
     * (para ponerlo al final de los suyos). Al editar o eliminar, cualquiera.
     */
    protected function consulta(Request $peticion): Builder
    {
        $universidad = $peticion->query('universidad')
            ?? ($peticion->routeIs('*.ciclos.store') ? $peticion->input('universidad_id') : null);

        return Ciclo::query()
            ->when(is_string($universidad), fn ($c) => $c->where('universidad_id', $universidad))
            ->orderBy('orden');
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';
        $precio = ['nullable', 'numeric', 'min:0', 'max:99999', 'decimal:0,2'];
        $fecha = ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'];

        return [
            // La universidad no cambia después de creado: es otra lista.
            'universidad_id' => $fila ? ['prohibited'] : ['required', 'uuid', Rule::exists('portal_universidades', 'id')],
            'nombre'         => [$obligatorio, 'string', 'max:40'],
            'turno'          => ['nullable', 'string', 'max:40'],
            'modalidades'    => [$obligatorio, 'array', 'min:1', 'max:2'],
            'modalidades.*'  => ['required', Rule::in(Ciclo::MODALIDADES), 'distinct'],
            'precio'         => $precio,
            'precio_virtual' => $precio,
            'inicio'         => $fecha,
            'fin'            => $fecha,
        ];
    }

    protected function atributos(): array
    {
        return [
            'universidad_id' => 'universidad',
            'nombre'         => 'nombre',
            'turno'          => 'turno',
            'modalidades'    => 'modalidades',
            'modalidades.*'  => 'modalidad',
            'precio'         => 'precio',
            'precio_virtual' => 'precio virtual',
            'inicio'         => 'inicio',
            'fin'            => 'fin',
        ];
    }

    public function store(Request $peticion): JsonResponse
    {
        $universidad = $peticion->input('universidad_id');

        if (is_string($universidad) && Ciclo::where('universidad_id', $universidad)->count() >= self::TOPE) {
            throw ValidationException::withMessages([
                'universidad_id' => 'Cada universidad admite hasta ' . self::TOPE . ' ciclos.',
            ]);
        }

        return parent::store($peticion);
    }

    /** El orden siempre va dentro de una universidad. */
    public function ordenar(Request $peticion): JsonResponse
    {
        $peticion->validate(['universidad' => ['required', 'uuid', Rule::exists('portal_universidades', 'id')]]);

        return parent::ordenar($peticion);
    }

    /** Una universidad publicada no puede quedarse sin ciclos: el contrato pide al menos uno. */
    public function destroy(Request $peticion, string $id): JsonResponse
    {
        $ciclo = $this->buscar($peticion, $id);
        $universidad = $ciclo->universidad;

        if ($universidad->estado === Universidad::PUBLICADO && $universidad->ciclos()->count() === 1) {
            throw ValidationException::withMessages([
                'ciclo' => "«{$ciclo->nombre}» es el único ciclo de «{$universidad->nombre}», que está publicada. "
                    . 'Agrega otro ciclo o pasa la universidad a borrador antes de eliminarlo.',
            ]);
        }

        return parent::destroy($peticion, $id);
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (array_key_exists('modalidades', $v)) {
            // En el orden del contrato y sin repetir.
            $v['modalidades'] = array_values(array_intersect(Ciclo::MODALIDADES, $v['modalidades']));
        }

        // Con lo que ya estaba, para comparar lo que no vino.
        $modalidades = $v['modalidades'] ?? $fila?->modalidades ?? [];
        $inicio = array_key_exists('inicio', $v) ? $v['inicio'] : $fila?->inicio?->format('Y-m-d');
        $fin = array_key_exists('fin', $v) ? $v['fin'] : $fila?->fin?->format('Y-m-d');

        if ($inicio && $fin && $fin < $inicio) {
            throw ValidationException::withMessages(['fin' => 'El fin no puede ser antes del inicio.']);
        }

        // El precio virtual solo existe si el ciclo se dicta en virtual.
        if (! in_array('virtual', $modalidades, true)) {
            $v['precio_virtual'] = null;
        }

        if (! $fila) {
            $universidad = Universidad::find($v['universidad_id']);
            $v['clave'] = $this->claveUnica("{$universidad->clave} {$v['nombre']}", 'ciclo');
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'universidad_id', 'nombre', 'turno', 'modalidades', 'orden']) + [
            'precio'         => $fila->precio === null ? null : (float) $fila->precio,
            'precio_virtual' => $fila->precio_virtual === null ? null : (float) $fila->precio_virtual,
            'inicio'         => $fila->inicio?->format('Y-m-d'),
            'fin'            => $fila->fin?->format('Y-m-d'),
        ];
    }
}
