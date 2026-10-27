<?php

namespace App\Support\Portal\Fichas;

use Closure;

/**
 * Los textos del formulario «Solicitar información» (contrato §3.15), que
 * está al final de Matrícula y de Contacto. El formulario no envía nada:
 * arma un mensaje de WhatsApp con la plantilla y lo abre.
 *
 * En las plantillas solo valen {nombre} y {grado}, que el portal reemplaza
 * por lo que escribió la familia. Otra cosa entre llaves llegaría tal cual
 * al WhatsApp del colegio.
 */
class FichaSolicitud implements Ficha
{
    public const PAGINAS = ['matricula', 'contacto'];
    private const MARCAS = ['nombre', 'grado'];

    /** Las etiquetas del formulario (2.13) y su largo máximo, en el orden del contrato. */
    public const ETIQUETAS = ['nombre' => 30, 'grado' => 30, 'gradoEjemplo' => 60, 'gradoElegir' => 30, 'boton' => 32];

    public function nombre(): string
    {
        return 'los textos del formulario «Solicitar información»';
    }

    public function reglas(): array
    {
        return [
            'titulo'                      => ['required', 'string', 'max:40'],
            'texto'                       => ['nullable', 'string', 'max:200'],
            'mensajeWhatsapp'             => ['required', 'string', 'max:300', $this->soloMarcasConocidas()],
            'mensajesPorPagina'           => ['present', 'array', 'max:2'],
            'mensajesPorPagina.*.pagina'  => ['required', 'distinct', 'in:' . implode(',', self::PAGINAS)],
            'mensajesPorPagina.*.mensaje' => ['required', 'string', 'max:300', $this->soloMarcasConocidas()],
            // 2.13: las etiquetas del formulario.
            'etiquetas'                   => ['required', 'array:' . implode(',', array_keys(self::ETIQUETAS))],
            ...collect(self::ETIQUETAS)->mapWithKeys(fn ($maximo, $campo) => ["etiquetas.{$campo}" => ['required', 'string', "max:{$maximo}"]])->all(),
        ];
    }

    public function atributos(): array
    {
        return [
            'titulo'                      => 'título',
            'texto'                       => 'frase bajo el título',
            'mensajeWhatsapp'             => 'mensaje general de WhatsApp',
            'mensajesPorPagina.*.pagina'  => 'página',
            'mensajesPorPagina.*.mensaje' => 'mensaje de la página',
            'etiquetas.nombre'            => 'etiqueta del nombre',
            'etiquetas.grado'             => 'etiqueta del grado',
            'etiquetas.gradoEjemplo'      => 'ejemplo del grado',
            'etiquetas.gradoElegir'       => 'primera opción del selector de grado',
            'etiquetas.boton'             => 'texto del botón',
        ];
    }

    public function aContenido(array $v): array
    {
        return [
            'titulo'            => $v['titulo'],
            'texto'             => $v['texto'] ?? null,
            'mensajeWhatsapp'   => $v['mensajeWhatsapp'],
            // En el orden de PAGINAS, sea cual sea el orden en que llegaron.
            'mensajesPorPagina' => collect($v['mensajesPorPagina'] ?? [])
                ->sortBy(fn ($m) => array_search($m['pagina'], self::PAGINAS, true))
                ->map(fn ($m) => ['pagina' => $m['pagina'], 'mensaje' => $m['mensaje']])
                ->values()
                ->all(),
            'etiquetas'         => collect(self::ETIQUETAS)->map(fn ($maximo, $campo) => $v['etiquetas'][$campo])->all(),
        ];
    }

    public function rutas(): array
    {
        return ['/v1/solicitud'];
    }

    private function soloMarcasConocidas(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla) {
            preg_match_all('/\{([^}]*)\}/', (string) $valor, $marcas);
            $desconocidas = array_diff($marcas[1], self::MARCAS);

            if ($desconocidas !== []) {
                $falla('Solo se puede usar {nombre} y {grado}. «{' . reset($desconocidas) . '}» llegaría tal cual al WhatsApp.');
            }
        };
    }
}
