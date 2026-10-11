<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Http\Controllers\Controller;
use App\Models\Portal\Banner;
use App\Models\Portal\Cifra;
use App\Models\Portal\Docente;
use App\Models\Portal\FechaMatricula;
use App\Models\Portal\GrupoGrados;
use App\Models\Portal\Logro;
use App\Models\Portal\Nivel;
use App\Models\Portal\NivelPropuesta;
use App\Models\Portal\Noticia;
use App\Models\Portal\Pagina;
use App\Models\Portal\Pilar;
use App\Models\Portal\Plataforma;
use App\Models\Portal\Pregunta;
use App\Models\Portal\ProcesoMatricula;
use App\Models\Portal\Proyecto;
use App\Models\Portal\RedSocial;
use App\Models\Portal\Seccion;
use App\Models\Portal\Sede;
use App\Models\Portal\Universidad;
use App\Models\Portal\Vacante;
use Illuminate\Http\JsonResponse;

/**
 * GET /web-portal/summary — cómo está cada parte del portal, para el índice
 * del panel: cuántos registros publicados y en borrador tiene cada lista, y
 * qué fichas (secciones de una sola vez) están cargadas.
 *
 * Las categorías (General, Inicio, Nosotros…) y qué lista va en cuál las
 * decide la pantalla; aquí solo se cuenta.
 */
class ResumenController extends Controller
{
    /** Clave que lee la pantalla → modelo de la lista. */
    private const LISTAS = [
        'redes'             => RedSocial::class,
        'banners'           => Banner::class,
        'niveles'           => Nivel::class,
        'sedes'             => Sede::class,
        'noticias'          => Noticia::class,
        'procesos'          => ProcesoMatricula::class,
        'vacantes'          => Vacante::class,
        'fechas'            => FechaMatricula::class,
        'preguntas'         => Pregunta::class,
        'grados'            => GrupoGrados::class,
        'proyectos'         => Proyecto::class,
        'paginas'           => Pagina::class,
        'docentes'          => Docente::class,
        'logros'            => Logro::class,
        'pilares'           => Pilar::class,
        'nivelesPropuesta'  => NivelPropuesta::class,
        'universidades'     => Universidad::class,
        'plataformas'       => Plataforma::class,
    ];

    public function __invoke(): JsonResponse
    {
        $listas = collect(self::LISTAS)->map(fn (string $modelo) => $this->contar($modelo::query()));

        foreach (Cifra::AMBITOS as $ambito) {
            $listas["cifras-{$ambito}"] = $this->contar(Cifra::de($ambito));
        }

        return response()->json(['success' => true, 'data' => [
            'listas'  => $listas,
            'fichas'  => Seccion::orderBy('clave')->pluck('clave'),
        ]]);
    }

    /** @return array{publicados: int, borradores: int} */
    private function contar($consulta): array
    {
        $porEstado = (clone $consulta)->toBase()->selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado');

        return [
            'publicados' => (int) ($porEstado['publicado'] ?? 0),
            'borradores' => (int) ($porEstado['borrador'] ?? 0),
        ];
    }
}
