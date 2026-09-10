<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Services\ConsultaDni;
use Illuminate\Http\Request;

/**
 * "Buscar por DNI" del alta de un trabajador.
 *
 * Devuelve dos cosas de una vez, porque son las dos que RR.HH. necesita
 * saber antes de seguir llenando la ficha:
 *
 *   - Quién es esa persona según el padrón.
 *   - Si ya está registrada en el colegio. Sin esto se llega hasta el final
 *     del formulario para que el guardado avise "el DNI ya existe", con los
 *     cinco pasos ya llenados.
 */
class ConsultaDniController extends Controller
{
    public function __invoke(Request $request, ConsultaDni $consulta, string $dni)
    {
        $dni = preg_replace('/\D/', '', $dni) ?? '';

        if (strlen($dni) !== 8) {
            return response()->json([
                'success' => false,
                'message' => 'El DNI son 8 dígitos, sin puntos ni espacios.',
            ], 422);
        }

        // Se mira primero en casa: si ya trabaja aquí, no hace falta gastar
        // una consulta al padrón para saber cómo se llama.
        $yaRegistrado = Empleado::where('dni', $dni)
            ->select('id', 'nombre', 'apellido', 'estado')
            ->first();

        if ($yaRegistrado) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'encontrado' => false,
                    'yaEsEmpleado' => [
                        'id'      => $yaRegistrado->id,
                        'nombre'  => trim($yaRegistrado->nombre . ' ' . $yaRegistrado->apellido),
                        'estado'  => $yaRegistrado->estado,
                    ],
                    'mensaje' => "Ese DNI ya está registrado: {$yaRegistrado->nombre} {$yaRegistrado->apellido}.",
                ],
            ]);
        }

        $persona = $consulta->buscar($dni);

        if (! $persona) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'encontrado' => false,
                    'mensaje'    => 'No encontramos ese DNI en el padrón. Escribe los datos a mano.',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => ['encontrado' => true] + $persona,
        ]);
    }
}
