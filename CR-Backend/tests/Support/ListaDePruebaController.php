<?php

namespace Tests\Support;

use App\Http\Controllers\Portal\Panel\ListaDelPortalController;
use App\Models\Portal\RedSocial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Una lista del panel solo para probar el controlador base, sobre las redes
 * sociales. Sus reglas son flojas A PROPÓSITO (no exigen https): así se ve
 * que la última red —validar contra el contrato antes de confirmar— atrapa
 * lo que las reglas dejan pasar.
 */
class ListaDePruebaController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return RedSocial::class;
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        return [
            'red'        => [$fila ? 'sometimes' : 'required', 'string'],
            'cuenta'     => [$fila ? 'sometimes' : 'required', 'string'],
            'url'        => [$fila ? 'sometimes' : 'required', 'string'],
            'aclaracion' => ['nullable', 'string'],
        ];
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/sitio'];
    }

    protected function entidad(): string
    {
        return 'la red social';
    }
}
