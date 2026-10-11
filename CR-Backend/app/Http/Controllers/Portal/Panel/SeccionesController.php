<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Http\Controllers\Controller;
use App\Models\Portal\Seccion;
use App\Support\Portal\Fichas\Ficha;
use App\Support\Portal\Fichas\FichaSitio;
use App\Support\Portal\ValidadorDeContrato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Las fichas del portal: el contenido de una sola vez de cada página (los
 * datos del colegio, la cabecera de Matrícula…), en portal_secciones.
 *
 *   GET /web-portal/sections/{clave}  → su contenido y su versión
 *   PUT /web-portal/sections/{clave}  → lo guarda entero
 *
 * Cada ficha dice sus reglas en su propia clase (App\Support\Portal\Fichas);
 * aquí solo se registran. Se van sumando a medida que se habilita cada página.
 *
 * Dos personas editando la misma ficha: el PUT trae la `version` que se
 * leyó, y si alguien guardó entre medio se responde 409 en vez de pisarle el
 * trabajo sin que se entere.
 */
class SeccionesController extends Controller
{
    /** clave → su definición. */
    private const FICHAS = [
        'sitio' => FichaSitio::class,
    ];

    public function ver(string $clave): JsonResponse
    {
        $ficha = $this->ficha($clave);
        $seccion = Seccion::find($clave);

        return response()->json(['success' => true, 'data' => [
            'clave'     => $clave,
            'nombre'    => $ficha->nombre(),
            // null si todavía no se cargó: la pantalla parte de un formulario vacío.
            'contenido' => $seccion?->contenido,
            'version'   => $seccion?->updated_at?->toJSON(),
        ]]);
    }

    public function guardar(Request $peticion, string $clave): JsonResponse
    {
        $ficha = $this->ficha($clave);
        $validado = $peticion->validate($ficha->reglas(), [], $ficha->atributos());

        $actual = Seccion::find($clave);

        if ($actual && $peticion->input('version') !== $actual->updated_at?->toJSON()) {
            return response()->json([
                'success' => false,
                'message' => 'Alguien guardó ' . $ficha->nombre() . ' mientras los editabas. Vuelve a cargarlos para no pisar sus cambios.',
            ], 409);
        }

        $seccion = DB::transaction(function () use ($clave, $ficha, $validado) {
            $seccion = Seccion::updateOrCreate(['clave' => $clave], ['contenido' => $ficha->aContenido($validado)]);

            $problemas = app(ValidadorDeContrato::class)->problemas($ficha->rutas());
            if ($problemas !== []) {
                throw ValidationException::withMessages(['contrato' => array_values($problemas)]);
            }

            return $seccion;
        });

        return $this->ver($seccion->clave);
    }

    private function ficha(string $clave): Ficha
    {
        abort_unless(isset(self::FICHAS[$clave]), 404);

        return app(self::FICHAS[$clave]);
    }
}
