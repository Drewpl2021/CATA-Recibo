<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Imagen;
use App\Models\Portal\Pagina;
use App\Support\Portal\Bloques;
use App\Support\Portal\ImagenesEnJson;
use App\Support\Portal\MensajesDeValidacion;
use App\Support\Portal\Navegacion;
use App\Support\Portal\PaginasDeNosotros;
use App\Support\Portal\RevisionDeBloques;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * /web-portal/pages — las subpáginas de Nosotros (contrato §3.19 y §3.20):
 * su título, su frase, la frase de su tarjeta en el índice y su lugar en el
 * menú (2.13: el grupo y el nombre). Sus bloques se editan aparte (y no se
 * tocan aquí).
 *
 * La dirección no cambia después de creada. Desde la 2.13 puede ser
 * cualquiera: el portal abre las que están en el menú (/v1/navegacion). Sin
 * grupo, la página no sale en el menú y el portal no la abre; en borrador,
 * tampoco, y su dirección responde 404.
 */
class PaginasController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return Pagina::class;
    }

    protected function entidad(): string
    {
        return 'la página';
    }

    /** El índice, el menú y, si se ve, la página misma. */
    protected function rutasAfectadas(?Model $fila): array
    {
        return $fila?->estado === Pagina::PUBLICADO
            ? ['/v1/nosotros', '/v1/navegacion', "/v1/nosotros/paginas/{$fila->slug}"]
            : ['/v1/nosotros', '/v1/navegacion'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'slug'    => $fila ? ['prohibited'] : ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('portal_paginas', 'slug')],
            'titulo'  => [$obligatorio, 'string', 'max:60'],
            'bajada'  => ['nullable', 'string', 'max:160'],
            'resumen' => ['nullable', 'string', 'max:140'],
            // null = fuera del menú.
            'grupo'   => ['sometimes', 'nullable', Rule::in(array_keys(Navegacion::grupos()))],
            'nombre'  => ['sometimes', 'nullable', 'required_with:grupo', 'string', 'max:40'],
        ];
    }

    protected function atributos(): array
    {
        return [
            'slug'    => 'dirección',
            'titulo'  => 'título',
            'bajada'  => 'frase bajo el título',
            'resumen' => 'frase de su tarjeta',
            'grupo'   => 'grupo del menú',
            'nombre'  => 'nombre en el menú',
        ];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (! $fila) {
            // Si no se eligió, el lugar que tenía en el menú del portal (las 16 de siempre), si su grupo existe.
            if (! array_key_exists('grupo', $v)) {
                $grupo = PaginasDeNosotros::grupo($v['slug']);
                $v['grupo'] = array_key_exists((string) $grupo, Navegacion::grupos()) ? $grupo : null;
            }
            $v['nombre'] ??= $v['grupo'] ? (PaginasDeNosotros::nombre($v['slug']) ?? $v['titulo']) : null;
            // Sin bloques: «El contenido de esta página se publicará pronto».
            $v['bloques'] = [];
        }

        if (array_key_exists('grupo', $v) && $v['grupo'] === null) {
            $v['nombre'] = null;
        }

        return $v;
    }

    /**
     * GET /web-portal/pages/{id} — la página con sus bloques, tal como se
     * guardan (las imágenes como {"imagenId": …}), cada imagen completa para
     * la vista previa, y la versión que hay que mandar al guardarlos.
     */
    public function show(Request $peticion, string $id): JsonResponse
    {
        $pagina = $this->buscar($peticion, $id);

        // array_merge: aquí `bloques` es la lista entera, no cuántos son.
        return $this->ok(array_merge($this->aPanel($pagina), [
            'bloques'  => $pagina->bloques ?? [],
            'imagenes' => $this->imagenesDe($pagina->bloques ?? []),
            'version'  => $pagina->updated_at?->toJSON(),
        ]));
    }

    /**
     * PUT /web-portal/pages/{id}/blocks — los bloques enteros, en orden:
     * { bloques: [...], version }.
     *
     * No se guarda nada que el portal no mostraría tal cual: los bloques se
     * revisan con la misma clase que los sirve (Bloques::problemas), y si
     * alguno se omitiría o perdería algo, responde 422 con qué falla y en qué
     * bloque. Si alguien guardó la página entre medio, 409.
     */
    public function bloques(Request $peticion, string $id): JsonResponse
    {
        $pagina = $this->buscar($peticion, $id);
        $validado = $peticion->validate([
            'bloques'   => ['present', 'array', 'max:' . Bloques::MAXIMO_DE_BLOQUES],
            'bloques.*' => ['array'],
            'version'   => ['nullable', 'string'],
        ], MensajesDeValidacion::MENSAJES, ['bloques' => 'bloques']);

        if ($peticion->input('version') !== $pagina->updated_at?->toJSON()) {
            return response()->json([
                'success' => false,
                'message' => 'Alguien guardó esta página mientras la editabas. Vuelve a cargarla para no pisar sus cambios.',
            ], 409);
        }

        $bloques = $this->conIdsDePersonas(array_values($validado['bloques']));

        $problemas = $this->imagenesQueFaltan($bloques);
        if ($problemas === []) {
            $problemas = Bloques::problemas(ImagenesEnJson::resolver($bloques));
        }
        if ($problemas !== []) {
            throw ValidationException::withMessages(['bloques' => $problemas]);
        }

        $this->guardando(function () use ($pagina, $bloques) {
            $pagina->update(['bloques' => $bloques]);

            return $pagina;
        });

        return $this->show($peticion, $id);
    }

    /** Cada {"imagenId"} que ya no existe, dicho por bloque. */
    private function imagenesQueFaltan(array $bloques): array
    {
        $problemas = [];

        foreach ($bloques as $i => $bloque) {
            $ids = [];
            array_walk_recursive($bloque, function ($valor, $clave) use (&$ids) {
                if ($clave === ImagenesEnJson::CLAVE) {
                    $ids[] = $valor;
                }
            });

            $validos = array_filter($ids, fn ($id) => is_string($id) && Str::isUuid($id));
            $existen = Imagen::whereIn('id', $validos)->count();

            if ($existen !== count(array_unique($ids))) {
                $nombre = RevisionDeBloques::NOMBRES[$bloque['tipo'] ?? ''] ?? null;
                $problemas[] = 'Bloque ' . ($i + 1) . ($nombre ? " ({$nombre})" : '') . ': una de sus imágenes ya no existe. Vuelve a subirla.';
            }
        }

        return $problemas;
    }

    /**
     * Las personas necesitan un id que no se repita en su bloque (el portal lo
     * usa de clave). Quien edita no lo escribe: se arma con el nombre y se
     * conserva el que ya tenían.
     */
    private function conIdsDePersonas(array $bloques): array
    {
        foreach ($bloques as $i => $bloque) {
            if (($bloque['tipo'] ?? null) !== 'personas' || ! is_array($bloque['personas'] ?? null)) {
                continue;
            }

            $usados = [];
            foreach ($bloque['personas'] as $j => $persona) {
                if (! is_array($persona)) {
                    continue;
                }

                $id = Str::slug((string) ($persona['id'] ?? '')) ?: Str::slug((string) ($persona['nombre'] ?? '')) ?: 'persona';
                $base = Str::limit($id, 50, '');
                for ($n = 2; in_array($id, $usados, true); $n++) {
                    $id = "{$base}-{$n}";
                }

                $usados[] = $id;
                $bloques[$i]['personas'][$j]['id'] = $id;
            }
        }

        return $bloques;
    }

    /** id → la imagen como la ve el panel, de cada {"imagenId": …} de los bloques. */
    private function imagenesDe(array $bloques): object
    {
        $ids = [];
        array_walk_recursive($bloques, function ($valor, $clave) use (&$ids) {
            if ($clave === ImagenesEnJson::CLAVE && is_string($valor)) {
                $ids[] = $valor;
            }
        });

        // Un objeto y no una lista: en JSON tiene que salir {} aunque esté vacío.
        return (object) Imagen::whereIn('id', $ids)->get()->mapWithKeys(fn (Imagen $i) => [$i->id => $i->aPanel()])->all();
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'slug', 'titulo', 'bajada', 'resumen', 'orden', 'estado']) + [
            'grupo'       => $fila->grupo,
            // El título del grupo, para la tabla; null si no está en el menú.
            'grupoTitulo' => $fila->grupo ? (Navegacion::grupos()[$fila->grupo] ?? null) : null,
            'nombre'      => $fila->nombre,
            'bloques'     => count($fila->bloques ?? []),
        ];
    }
}
