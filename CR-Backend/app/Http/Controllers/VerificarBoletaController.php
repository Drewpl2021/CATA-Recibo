<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Illuminate\Http\Request;

/**
 * GET /api/verificar-boleta/{documento}?signature=… — lo que abre el QR de
 * la boleta.
 *
 * Es pública: la abre quien tiene la boleta en la mano (un banco, otra
 * institución) para saber si es auténtica. Por eso la dirección va FIRMADA
 * por el servidor (URL::signedRoute): sin la firma no se puede adivinar la
 * de otra boleta cambiando el id, y una dirección alterada dice que no se
 * pudo verificar.
 *
 * Enseña lo justo para comparar con el papel: quién, de qué mes, cuánto
 * cobró y si ya está firmada. El DNI va a medias.
 */
class VerificarBoletaController extends Controller
{
    public function ver(Request $request, string $documento)
    {
        $doc = $request->hasValidSignature()
            ? Documento::with('empleado', 'planilla')->where('tipo', 'boleta')->find($documento)
            : null;

        if (! $doc || ! $doc->planilla || ! $doc->empleado) {
            return response()->view('verificar-boleta', ['valida' => false], 404);
        }

        $planilla = $doc->planilla;
        $empleado = $doc->empleado;
        $dni = (string) $empleado->dni;

        return response()->view('verificar-boleta', [
            'valida'   => true,
            'numero'   => $planilla->numeroDeBoleta(),
            'periodo'  => \App\Support\Meses::nombre((int) $planilla->mes) . ' ' . $planilla->anio,
            'nombre'   => trim("{$empleado->apellido} {$empleado->nombre}"),
            'dni'      => str_repeat('•', max(0, strlen($dni) - 4)) . substr($dni, -4),
            'neto'     => (float) $planilla->total,
            'firmada'  => in_array($doc->estado_firma, Documento::FIRMA_RESUELTA, true),
            'fechaFirma' => $doc->fecha_firma?->format('d/m/Y H:i'),
            'emitida'  => $doc->created_at?->format('d/m/Y'),
        ]);
    }
}
