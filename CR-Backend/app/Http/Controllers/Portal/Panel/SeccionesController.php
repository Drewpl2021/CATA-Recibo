<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Http\Controllers\Controller;
use App\Models\Portal\Imagen;
use App\Models\Portal\Seccion;
use App\Support\Portal\Fichas\Ficha;
use App\Support\Portal\Fichas\FichaContacto;
use App\Support\Portal\Fichas\FichaLogros;
use App\Support\Portal\Fichas\FichaMatriculaCabecera;
use App\Support\Portal\Fichas\FichaMatriculaFechas;
use App\Support\Portal\Fichas\FichaMatriculaVacantes;
use App\Support\Portal\Fichas\FichaNiveles;
use App\Support\Portal\Fichas\FichaNosotros;
use App\Support\Portal\Fichas\FichaPropuestaAcademia;
use App\Support\Portal\Fichas\FichaPropuestaCabecera;
use App\Support\Portal\Fichas\FichaPropuestaPilares;
use App\Support\Portal\Fichas\FichaProyectos;
use App\Support\Portal\Fichas\FichaQuienesSomos;
use App\Support\Portal\Fichas\FichaSitio;
use App\Support\Portal\Fichas\FichaSolicitud;
use App\Support\Portal\ImagenesEnJson;
use App\Support\Portal\MensajesDeValidacion;
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
        'sitio'     => FichaSitio::class,
        'contacto'  => FichaContacto::class,
        'solicitud' => FichaSolicitud::class,
        'logros'    => FichaLogros::class,

        'matricula-cabecera' => FichaMatriculaCabecera::class,
        'matricula-vacantes' => FichaMatriculaVacantes::class,
        'matricula-fechas'   => FichaMatriculaFechas::class,

        'quienes-somos' => FichaQuienesSomos::class,
        'niveles'       => FichaNiveles::class,

        'proyectos' => FichaProyectos::class,

        'propuesta-cabecera' => FichaPropuestaCabecera::class,
        'propuesta-pilares'  => FichaPropuestaPilares::class,
        'propuesta-academia' => FichaPropuestaAcademia::class,

        'nosotros' => FichaNosotros::class,
    ];

    public function ver(string $clave): JsonResponse
    {
        $ficha = $this->ficha($clave);
        $seccion = Seccion::find($clave);

        return response()->json(['success' => true, 'data' => [
            'clave'     => $clave,
            'nombre'    => $ficha->nombre(),
            // null si todavía no se cargó: la pantalla parte de un formulario vacío.
            // Las imágenes van como {"imagenId": …}, igual que se guardan.
            'contenido' => $seccion?->contenido,
            // …y aquí, cada una completa, para la vista previa.
            'imagenes'  => $this->imagenesDe($seccion?->contenido ?? []),
            'version'   => $seccion?->updated_at?->toJSON(),
        ]]);
    }

    /** id → la imagen como la ve el panel, de cada {"imagenId": …} del contenido. */
    private function imagenesDe(array $contenido): object
    {
        $ids = [];
        array_walk_recursive($contenido, function ($valor, $clave) use (&$ids) {
            if ($clave === ImagenesEnJson::CLAVE && is_string($valor)) {
                $ids[] = $valor;
            }
        });

        // Un objeto y no una lista: en JSON tiene que salir {} aunque esté vacío.
        return (object) Imagen::whereIn('id', $ids)->get()->mapWithKeys(fn (Imagen $i) => [$i->id => $i->aPanel()])->all();
    }

    public function guardar(Request $peticion, string $clave): JsonResponse
    {
        $ficha = $this->ficha($clave);
        $validado = $peticion->validate($ficha->reglas(), MensajesDeValidacion::MENSAJES, $ficha->atributos());

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
