<?php

namespace App\Support\Portal;

/**
 * Lo fijo de GET /v1/navegacion (contrato 2.13, §3.27): qué páginas puede
 * nombrar el menú, las columnas del pie y los títulos de cada página, con su
 * largo máximo. Lo usan el endpoint que lo sirve y las fichas del panel que
 * lo editan, para que no haya dos listas que puedan diferir.
 *
 * Los textos no están aquí: los escribe el colegio en el panel.
 */
final class Navegacion
{
    /** Las páginas que puede nombrar el menú, en el orden del contrato. La ruta la pone el portal. */
    public const PAGINAS = ['inicio', 'proyectos', 'nosotros', 'propuesta-educativa', 'matricula', 'logros', 'contacto'];

    public const MAXIMO_DE_GRUPOS = 4;

    public const MAXIMO_DE_PAGINAS_POR_GRUPO = 12;

    /** Las columnas del pie. `redes` también titula «Síguenos» en el menú móvil. */
    public const PIE = ['colegio', 'nosotros', 'contacto', 'redes'];

    /**
     * Los títulos de cada página: campo => largo máximo, en el orden del
     * contrato. En los que llevan {n}, el portal pone el número en letras.
     */
    public const TITULOS = [
        'inicio' => [
            'quienesSomos' => 60, 'verHistoria' => 32, 'emblema' => 60, 'niveles' => 60, 'verPropuesta' => 32,
            'sedes' => 60, 'sedesVarias' => 60, 'sedesUna' => 60, 'noticias' => 60,
        ],
        'nosotros' => ['accion' => 32, 'rotuloAnio' => 20],
        'propuestaEducativa' => ['pilares' => 60, 'rotuloPilar' => 20, 'niveles' => 60, 'plataformas' => 60],
        'matricula' => [
            'accionPrincipal' => 32, 'accionSecundaria' => 32, 'rotuloAnio' => 20, 'procesos' => 60,
            'vacantes' => 60, 'preguntarGrado' => 32, 'fechas' => 60, 'preguntas' => 60,
        ],
        'contacto' => [
            'accionPrincipal' => 32, 'accionSecundaria' => 32, 'rotuloWhatsapp' => 20, 'sedes' => 60,
            'sedesUna' => 60, 'mapa' => 60, 'redes' => 60,
        ],
        'proyectos' => [
            'accion' => 32, 'contacto' => 32, 'rotulo' => 20, 'rotuloUno' => 20, 'lista' => 60,
            'sobre' => 60, 'galeria' => 60, 'otros' => 60, 'volver' => 32, 'verTodos' => 32,
        ],
        'logros' => ['accion' => 32, 'rotulo' => 20, 'rotuloUno' => 20, 'destacados' => 60, 'todos' => 60],
    ];

    /** Los grupos de Nosotros guardados en la ficha `navegacion`: id => título, en su orden. */
    public static function grupos(): array
    {
        $contenido = \App\Models\Portal\Seccion::find('navegacion')?->contenido ?? [];

        return collect($contenido['grupos'] ?? [])
            ->filter(fn ($g) => is_array($g) && filled($g['id'] ?? null))
            ->mapWithKeys(fn ($g) => [(string) $g['id'] => (string) ($g['titulo'] ?? $g['id'])])
            ->all();
    }
}
