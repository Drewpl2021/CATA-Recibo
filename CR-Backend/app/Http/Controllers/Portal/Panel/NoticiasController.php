<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Imagen;
use App\Models\Portal\Noticia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * /web-portal/news — noticias y comunicados (contrato §3.7).
 *
 * Van siempre por fecha, de la más reciente a la más antigua (no se ordenan a
 * mano: no hay ruta de orden). El `slug` se arma con el título al crearla y no
 * cambia: será la dirección de su futura página de detalle.
 *
 * El portal las pide por páginas. Antes de guardar se valida la primera
 * página (la de Inicio) y la página donde cae la noticia tocada, para que
 * una noticia vieja con un problema no pase porque nadie mira esa página.
 */
class NoticiasController extends ListaDelPortalController
{
    /** El tope del contrato para ?limite: con él se recorre el portal en menos páginas. */
    private const POR_PAGINA = 24;

    protected function modelo(): string
    {
        return Noticia::class;
    }

    protected function entidad(): string
    {
        return 'la noticia';
    }

    /** Como las ve el portal: la más reciente primero. */
    protected function consulta(Request $peticion): Builder
    {
        return Noticia::query()->orderByDesc('fecha')->orderBy('orden');
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        $rutas = ['/v1/noticias'];

        if ($fila && $fila->estado === Noticia::PUBLICADO) {
            $posicion = Noticia::recientes()->pluck('id')->search($fila->getKey());
            if ($posicion !== false) {
                $pagina = intdiv($posicion, self::POR_PAGINA) + 1;
                $rutas[] = '/v1/noticias?limite=' . self::POR_PAGINA . "&pagina={$pagina}";
            }
        }

        return $rutas;
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'tipo'             => [$obligatorio, Rule::in(Noticia::TIPOS)],
            'titulo'           => [$obligatorio, 'string', 'max:120'],
            'resumen'          => ['nullable', 'string', 'max:280'],
            'fecha'            => [$obligatorio, 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'imagen_id'        => ['nullable', 'uuid', Rule::exists('portal_imagenes', 'id')],
            // El documento o la página con el contenido completo.
            'enlace'           => ['nullable', 'array'],
            'enlace.etiqueta'  => ['required_with:enlace', 'string', 'max:40'],
            'enlace.url'       => ['required_with:enlace', 'url', 'starts_with:https://', 'max:2048'],
            'enlace.externo'   => ['sometimes', 'boolean'],
        ];
    }

    protected function atributos(): array
    {
        return [
            'tipo'            => 'tipo',
            'titulo'          => 'título',
            'resumen'         => 'resumen',
            'fecha'           => 'fecha',
            'imagen_id'       => 'foto',
            'enlace.etiqueta' => 'texto del enlace',
            'enlace.url'      => 'dirección del enlace',
        ];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (array_key_exists('enlace', $v)) {
            $v['enlace'] = filled($v['enlace']['url'] ?? null)
                ? ['etiqueta' => $v['enlace']['etiqueta'], 'url' => $v['enlace']['url'], 'externo' => (bool) ($v['enlace']['externo'] ?? true)]
                : null;
        }

        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['titulo'], 'noticia');
            $v['slug'] = $this->slugUnico($v['titulo']);
        }

        return $v;
    }

    /** «Reunión de padres» → «reunion-de-padres», o -2, -3… si ya existe. Hasta 120. */
    private function slugUnico(string $titulo): string
    {
        $base = Str::limit(Str::slug($titulo), 110, '') ?: 'noticia';
        $slug = $base;

        for ($i = 2; Noticia::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'slug', 'tipo', 'titulo', 'resumen', 'imagen_id', 'enlace', 'orden', 'estado']) + [
            'fecha'  => $fila->fecha?->format('Y-m-d'),
            'imagen' => $fila->imagen_id ? Imagen::find($fila->imagen_id)?->aPanel() : null,
        ];
    }
}
