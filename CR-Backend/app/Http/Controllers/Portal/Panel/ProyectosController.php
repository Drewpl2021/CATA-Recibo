<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Imagen;
use App\Models\Portal\Proyecto;
use App\Models\Portal\ProyectoImagen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * /web-portal/projects — los proyectos del colegio (contrato §3.17 y §3.18),
 * hasta 24 publicados, cada uno con su página de detalle.
 *
 * El `slug` es la dirección del detalle (/proyectos/{slug}). Se arma con el
 * título al crearlo y se puede corregir mientras está en borrador; una vez
 * publicado no cambia, para no romper los enlaces que ya circulan.
 *
 * La galería (0 a 12 fotos 3:2) se guarda entera y en orden con
 * PUT …/{id}/gallery.
 */
class ProyectosController extends ListaDelPortalController
{
    private const MAX_GALERIA = 12;

    protected function modelo(): string
    {
        return Proyecto::class;
    }

    protected function entidad(): string
    {
        return 'el proyecto';
    }

    protected function topePublicados(): ?int
    {
        return 24;
    }

    protected function plural(): string
    {
        return 'proyectos publicados';
    }

    /** La lista y, si está publicado, su detalle. */
    protected function rutasAfectadas(?Model $fila): array
    {
        return $fila && $fila->estado === Proyecto::PUBLICADO
            ? ['/v1/proyectos', "/v1/proyectos/{$fila->slug}"]
            : ['/v1/proyectos'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';
        $publicado = $fila?->estado === Proyecto::PUBLICADO;

        return [
            'titulo'       => [$obligatorio, 'string', 'max:80'],
            // Solo mientras no está publicado; después la dirección no cambia.
            'slug'         => [
                $publicado ? 'prohibited' : 'sometimes', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('portal_proyectos', 'slug')->ignore($fila?->getKey()),
            ],
            'lema'         => ['nullable', 'string', 'max:100'],
            'resumen'      => [$obligatorio, 'string', 'max:240'],
            'categoria_id' => [$obligatorio, 'uuid', Rule::exists('portal_proyecto_categorias', 'id')],
            'niveles'      => ['sometimes', 'array', 'max:6'],
            'niveles.*'    => ['required', 'string', 'max:40', 'distinct'],
            'etapa'        => ['nullable', Rule::in(Proyecto::ETAPAS)],
            'fecha'        => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'imagen_id'    => ['nullable', 'uuid', Rule::exists('portal_imagenes', 'id')],

            'descripcion'   => [$obligatorio, 'array', 'min:1', 'max:6'],
            'descripcion.*' => ['required', 'string', 'max:600'],

            'secciones'                  => ['sometimes', 'array', 'max:8'],
            'secciones.*'                => ['array:titulo,tipo,items'],
            'secciones.*.titulo'         => ['required', 'string', 'max:60'],
            'secciones.*.tipo'           => ['required', Rule::in(Proyecto::TIPOS_DE_SECCION)],
            'secciones.*.items'          => ['required', 'array', 'min:1', 'max:12'],
            'secciones.*.items.*'        => ['array:titulo,texto'],
            'secciones.*.items.*.titulo' => ['nullable', 'string', 'max:60'],
            'secciones.*.items.*.texto'  => ['required', 'string', 'max:240'],
        ];
    }

    protected function atributos(): array
    {
        return [
            'titulo'                     => 'título',
            'slug'                       => 'dirección',
            'lema'                       => 'lema',
            'resumen'                    => 'resumen',
            'categoria_id'               => 'categoría',
            'niveles'                    => 'niveles',
            'niveles.*'                  => 'nivel',
            'etapa'                      => 'etapa',
            'fecha'                      => 'fecha',
            'imagen_id'                  => 'foto',
            'descripcion'                => 'descripción',
            'descripcion.*'              => 'párrafo de la descripción',
            'secciones'                  => 'secciones',
            'secciones.*.titulo'         => 'título de la sección',
            'secciones.*.tipo'           => 'tipo de sección',
            'secciones.*.items'          => 'elementos de la sección',
            'secciones.*.items.*.titulo' => 'título del elemento',
            'secciones.*.items.*.texto'  => 'texto del elemento',
        ];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        foreach (['niveles', 'descripcion'] as $lista) {
            if (array_key_exists($lista, $v)) {
                $v[$lista] = array_values($v[$lista]);
            }
        }

        if (array_key_exists('secciones', $v)) {
            $v['secciones'] = array_map(fn (array $s) => [
                'titulo' => $s['titulo'],
                'tipo'   => $s['tipo'],
                'items'  => array_map(fn (array $i) => [
                    'titulo' => filled($i['titulo'] ?? null) ? $i['titulo'] : null,
                    'texto'  => $i['texto'],
                ], array_values($s['items'])),
            ], array_values($v['secciones']));
        }

        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['titulo'], 'proyecto');
            $v['slug'] ??= $this->slugUnico($v['titulo']);
            $v['niveles'] ??= [];
            $v['secciones'] ??= [];
        }

        return $v;
    }

    /** «Robótica e Impresiones 3D» → «robotica-e-impresiones-3d», o -2, -3… Hasta 60. */
    private function slugUnico(string $titulo): string
    {
        $base = rtrim(Str::limit(Str::slug($titulo), 55, ''), '-') ?: 'proyecto';
        $slug = $base;

        for ($i = 2; Proyecto::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /**
     * PUT /web-portal/projects/{id}/gallery — la galería entera, en orden:
     * { imagenes: [id, id…] }. Las que no se mandan salen de la galería
     * (la limpieza borra después las fotos que ya nadie usa).
     */
    public function galeria(Request $peticion, string $id): JsonResponse
    {
        $proyecto = $this->buscar($peticion, $id);

        $ids = $peticion->validate([
            'imagenes'   => ['present', 'array', 'max:' . self::MAX_GALERIA],
            'imagenes.*' => ['required', 'uuid', 'distinct', Rule::exists('portal_imagenes', 'id')],
        ], [], ['imagenes' => 'galería', 'imagenes.*' => 'foto de la galería'])['imagenes'];

        $this->guardando(function () use ($proyecto, $ids) {
            // Una por una, para que la auditoría registre qué salió y qué entró.
            $proyecto->galeria()->get()->each(fn (ProyectoImagen $foto) => $foto->delete());

            foreach (array_values($ids) as $i => $imagenId) {
                ProyectoImagen::create(['proyecto_id' => $proyecto->getKey(), 'imagen_id' => $imagenId, 'orden' => $i + 1]);
            }

            return $proyecto;
        });

        return $this->ok($this->aPanel($proyecto->refresh()));
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only([
            'id', 'clave', 'slug', 'titulo', 'lema', 'resumen', 'categoria_id', 'niveles', 'etapa',
            'imagen_id', 'descripcion', 'secciones', 'orden', 'estado',
        ]) + [
            'fecha'   => $fila->fecha?->format('Y-m-d'),
            'imagen'  => $fila->imagen_id ? Imagen::find($fila->imagen_id)?->aPanel() : null,
            'galeria' => $fila->galeria()->with('imagen')->get()
                ->map(fn (ProyectoImagen $foto) => $foto->imagen?->aPanel())->filter()->values()->all(),
        ];
    }
}
