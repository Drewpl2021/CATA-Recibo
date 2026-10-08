<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Support\AniosAnteriores;
use App\Support\FirmaDigitalDeBoletas;
use App\Support\Renta5ta\MetodoRenta5ta;
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
        // Se cambian de a uno: la pantalla manda solo el que se tocó.
        $datos = $request->validate([
            AniosAnteriores::AJUSTE => 'sometimes|boolean',
            MetodoRenta5ta::AJUSTE  => 'sometimes|boolean',
            FirmaDigitalDeBoletas::AJUSTE_FIRMAS => 'sometimes|integer|in:1,2',
        ]);

        foreach ($datos as $clave => $valor) {
            $clave === FirmaDigitalDeBoletas::AJUSTE_FIRMAS
                ? Configuracion::ponerNumero($clave, (int) $valor)
                : Configuracion::poner($clave, (bool) $valor);
        }

        return response()->json(['success' => true, 'data' => $this->ajustes()]);
    }

    private function ajustes(): array
    {
        return [
            AniosAnteriores::AJUSTE => AniosAnteriores::permitidos(),
            MetodoRenta5ta::AJUSTE  => MetodoRenta5ta::comoHojaDeRrhh(),
            FirmaDigitalDeBoletas::AJUSTE_FIRMAS => FirmaDigitalDeBoletas::requeridas(),
            // Cuántas boletas esperan todavía la firma del colegio: al apagar
            // el ajuste, esas siguen su camino (la pantalla lo avisa).
            'boletas_esperando_firma_colegio' => \App\Models\Documento::where('tipo', 'boleta')
                ->whereIn('firma_colegio', \App\Models\Documento::FIRMA_COLEGIO_EN_CURSO)->count(),
        ];
    }
}
