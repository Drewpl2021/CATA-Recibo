<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Cifra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/stats?ambito=… — las cifras destacadas (contrato §3.9). Una
 * tabla con tres listas: las de Inicio (`portada`), las de Logros y las de
 * Propuesta Educativa. Cada una hasta 4 publicadas.
 *
 * Solo cifras reales y verificables: lo recuerda la pantalla, porque la API
 * no tiene cómo comprobarlo.
 */
class CifrasController extends ListaDelPortalController
{
    /** Qué respuesta del portal muestra las cifras de cada página. */
    private const RUTAS = [
        'portada'   => '/v1/portada/cifras',
        'logros'    => '/v1/logros',
        'propuesta' => '/v1/propuesta-educativa/pilares',
    ];

    protected function modelo(): string
    {
        return Cifra::class;
    }

    protected function entidad(): string
    {
        return 'la cifra';
    }

    protected function topePublicados(): ?int
    {
        return 4;
    }

    protected function plural(): string
    {
        return 'cifras publicadas en cada página';
    }

    /** La lista de una página: ?ambito=logros (o el de la fila, al editarla). */
    protected function consulta(Request $peticion): Builder
    {
        $ambito = $peticion->query('ambito');

        return Cifra::query()
            ->when(in_array($ambito, Cifra::AMBITOS, true), fn ($c) => $c->where('ambito', $ambito))
            ->orderBy('orden');
    }

    protected function publicadosJuntoA(Model $fila): int
    {
        return Cifra::de($fila->ambito)->where('estado', Cifra::PUBLICADO)->count();
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        // Al ordenar o eliminar no hay fila: se revisan las tres páginas.
        return $fila ? [self::RUTAS[$fila->ambito]] : array_values(self::RUTAS);
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            // La página no cambia después de creada: es otra lista.
            'ambito'   => $fila ? ['prohibited'] : ['required', Rule::in(Cifra::AMBITOS)],
            'valor'    => [$obligatorio, 'integer', 'min:0', 'max:999999'],
            'sufijo'   => ['nullable', 'string', 'max:3'],
            'etiqueta' => [$obligatorio, 'string', 'max:60'],
        ];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['etiqueta'], 'cifra', fn ($c) => $c->where('ambito', $v['ambito']));
        }

        return $v;
    }

    /**
     * El orden siempre va dentro de una página: sin ?ambito, la lista
     * mezclaría las tres y se mandaría un orden que no tiene sentido.
     */
    public function ordenar(Request $peticion): \Illuminate\Http\JsonResponse
    {
        $peticion->validate(['ambito' => ['required', Rule::in(Cifra::AMBITOS)]]);

        return parent::ordenar($peticion);
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'ambito', 'valor', 'sufijo', 'etiqueta', 'orden', 'estado']);
    }
}
