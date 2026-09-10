<?php

namespace App\Http\Controllers;

use App\Support\TerminosDeUso;
use Illuminate\Http\Request;

/**
 * Los términos de uso y su firma.
 *
 * Sustituye a la hoja de Excel que se imprimía y se pasaba de mano en mano
 * para que cada trabajador firmara que aceptaba recibir sus boletas por
 * medios digitales. La diferencia práctica es que ahora RR.HH. sabe en todo
 * momento quién firmó y quién no, sin perseguir a nadie.
 */
class TerminosController extends Controller
{
    /**
     * GET /terminos — el documento y si esta persona ya lo firmó.
     */
    public function mostrar(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => TerminosDeUso::documento() + [
                'firmados'    => $this->estanAlDia($user),
                'firmadosEn'  => $user->terminos_firmados_en,
                'versionFirmada' => $user->terminos_version,
                // Habiendo firmado una versión anterior, hay que volver a
                // firmar: lo que se aceptó no es lo que dice el documento de
                // ahora, y decir "aceptó los términos" sin decir cuáles no
                // prueba nada.
                'esVersionNueva' => (bool) $user->terminos_firmados
                    && $user->terminos_version !== TerminosDeUso::VERSION,
            ],
        ]);
    }

    /**
     * POST /terminos/aceptar — la firma.
     *
     * Se guarda quién, cuándo, qué versión y desde dónde: es lo mismo que
     * probaba la hoja firmada, y sin la versión no probaría nada.
     */
    public function aceptar(Request $request)
    {
        $request->validate([
            // Se exige el sí explícito y no basta con llamar a la ruta: es
            // una aceptación, y tiene que haber un acto de aceptar.
            'acepto' => 'required|accepted',
        ]);

        $user = $request->user();

        if ($this->estanAlDia($user)) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'message'    => 'Ya habías aceptado estos términos.',
                    'firmadosEn' => $user->terminos_firmados_en,
                    'version'    => $user->terminos_version,
                ],
            ]);
        }

        $user->forceFill([
            'terminos_firmados'    => true,
            'terminos_firmados_en' => now(),
            'terminos_version'     => TerminosDeUso::VERSION,
            'terminos_ip'          => $request->ip(),
        ])->save();

        return response()->json([
            'success' => true,
            'data'    => [
                'message'    => 'Términos aceptados. Gracias: ya no hace falta firmar nada en papel.',
                'firmadosEn' => $user->terminos_firmados_en,
                'version'    => $user->terminos_version,
            ],
        ], 201);
    }

    /** Firmados, y además la versión que está vigente hoy. */
    private function estanAlDia($user): bool
    {
        return (bool) $user->terminos_firmados
            && $user->terminos_version === TerminosDeUso::VERSION;
    }
}
