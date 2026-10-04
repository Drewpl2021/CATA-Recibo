<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Support\AniosAnteriores;
use Illuminate\Http\Request;

/**
 * Ajustes del sistema. Los lee RR.HH. (para saber qué puede hacer) y los
 * cambia solo el Administrador.
 */
class ConfiguracionController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => $this->ajustes()]);
    }

    public function update(Request $request)
    {
        $datos = $request->validate([
            AniosAnteriores::AJUSTE => 'required|boolean',
        ]);

        Configuracion::poner(AniosAnteriores::AJUSTE, (bool) $datos[AniosAnteriores::AJUSTE]);

        return response()->json(['success' => true, 'data' => $this->ajustes()]);
    }

    private function ajustes(): array
    {
        return [
            AniosAnteriores::AJUSTE => AniosAnteriores::permitidos(),
        ];
    }
}
