<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Http\Controllers\Controller;
use App\Support\Portal\ValidadorDeContrato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lo común a cada lista del portal que se edita desde el panel (banners,
 * sedes, noticias…): ver, crear, editar, ordenar, publicar o pasar a
 * borrador, y eliminar.
 *
 * Cada lista hereda esto y solo dice lo suyo: su modelo, sus reglas y qué
 * respuestas del portal cambian cuando ella cambia. Así las veinte listas se
 * portan igual, y una regla nueva (por ejemplo, otra comprobación antes de
 * guardar) se pone en un solo lugar.
 *
 * Todo cambio va en una transacción que solo se confirma si el portal sigue
 * cumpliendo su contrato (ValidadorDeContrato). Si no, se deshace y se
 * responde 422 con el porqué, como cualquier error de formulario.
 *
 * Responde con el formato del sistema ({ success, data }), no con el del
 * portal: quien lo lee es la pantalla de RR.HH.
 */
abstract class ListaDelPortalController extends Controller
{
    /** El modelo de la lista (App\Models\Portal\…). */
    abstract protected function modelo(): string;

    /**
     * Las reglas de un registro, con los largos y vocabularios del contrato.
     *
     * @param  Model|null  $fila  null al crear
     */
    abstract protected function reglas(Request $peticion, ?Model $fila): array;

    /**
     * Las rutas del portal que cambian cuando cambia esta lista, con sus
     * valores: ['/v1/portada/banners']. Son las que se validan antes de
     * confirmar.
     *
     * @param  Model|null  $fila  la fila tocada (para un detalle: '/v1/proyectos/{slug}')
     */
    abstract protected function rutasAfectadas(?Model $fila): array;

    /** Cómo se llama un registro en los mensajes: 'el banner'. */
    abstract protected function entidad(): string;

    /**
     * Cuántos puede haber publicados a la vez, según el contrato (8 sedes, 8
     * banners…). null = sin tope. Se comprueba antes de confirmar, con un
     * mensaje que entiende quien edita; el esquema lo atraparía igual, pero
     * diciéndolo en su idioma.
     */
    protected function topePublicados(): ?int
    {
        return null;
    }

    /**
     * Cómo se leen los campos en los mensajes de error, sobre todo los de
     * adentro de una lista: 'pasos.*.titulo' => 'título del paso'.
     */
    protected function atributos(): array
    {
        return [];
    }

    /** Cómo se dicen en ese mensaje, con su concordancia: 'sedes publicadas'. */
    protected function plural(): string
    {
        return 'registros publicados';
    }

    /**
     * Si lo nuevo entra al principio de la lista y no al final: los banners,
     * que el contrato quiere del más reciente al más antiguo.
     */
    protected function nuevoAlPrincipio(): bool
    {
        return false;
    }

    /** Si la lista se publica y se pasa a borrador (las categorías, no). */
    protected function tieneEstado(): bool
    {
        return true;
    }

    /**
     * Las filas de la lista. Por defecto, todas en su orden; una lista que
     * vive dentro de otra (las cifras de una página, los ciclos de una
     * universidad) la acota aquí con la petición.
     */
    protected function consulta(Request $peticion): Builder
    {
        return ($this->modelo())::query()->orderBy('orden');
    }

    /** Lo validado → columnas del modelo. Por defecto, tal cual. */
    protected function aColumnas(array $validado, Request $peticion, ?Model $fila): array
    {
        return $validado;
    }

    /** Cómo ve el panel una fila. Por defecto, el modelo entero. */
    protected function aPanel(Model $fila): array
    {
        return $fila->toArray();
    }

    // ───────── Acciones ─────────

    public function index(Request $peticion): JsonResponse
    {
        return $this->ok($this->consulta($peticion)->get()->map(fn (Model $f) => $this->aPanel($f))->all());
    }

    public function show(Request $peticion, string $id): JsonResponse
    {
        return $this->ok($this->aPanel($this->buscar($peticion, $id)));
    }

    /**
     * Crea al final de la lista y en BORRADOR, salvo que se pida otra cosa:
     * nada nuevo aparece en el portal sin que alguien lo publique.
     */
    public function store(Request $peticion): JsonResponse
    {
        $validado = $peticion->validate($this->reglas($peticion, null) + $this->reglaDeEstado(), [], $this->atributos());

        $fila = $this->guardando(function () use ($validado, $peticion) {
            $columnas = $this->aColumnas($validado, $peticion, null);

            if ($this->nuevoAlPrincipio()) {
                // Los demás bajan un lugar (uno por uno, para que la auditoría lo registre).
                $this->consulta($peticion)->get()->each(fn (Model $f) => $f->update(['orden' => $f->orden + 1]));
                $columnas['orden'] = 1;
            } else {
                $columnas['orden'] = (int) $this->consulta($peticion)->max('orden') + 1;
            }

            if ($this->tieneEstado()) {
                $columnas['estado'] = $validado['estado'] ?? ($this->modelo())::BORRADOR;
            }

            return ($this->modelo())::create($columnas);
        });

        return $this->ok($this->aPanel($fila->refresh()), 201);
    }

    public function update(Request $peticion, string $id): JsonResponse
    {
        $fila = $this->buscar($peticion, $id);
        $validado = $peticion->validate($this->reglas($peticion, $fila) + $this->reglaDeEstado(), [], $this->atributos());

        $this->guardando(function () use ($fila, $validado, $peticion) {
            $fila->update($this->aColumnas($validado, $peticion, $fila)
                + ($this->tieneEstado() && isset($validado['estado']) ? ['estado' => $validado['estado']] : []));

            return $fila;
        });

        return $this->ok($this->aPanel($fila->refresh()));
    }

    /** PATCH …/{id}/status — publicarla o pasarla a borrador. */
    public function estado(Request $peticion, string $id): JsonResponse
    {
        abort_unless($this->tieneEstado(), 404);

        $fila = $this->buscar($peticion, $id);
        $validado = $peticion->validate(['estado' => ['required', Rule::in(($this->modelo())::ESTADOS)]]);

        $this->guardando(function () use ($fila, $validado) {
            $fila->update(['estado' => $validado['estado']]);

            return $fila;
        });

        return $this->ok($this->aPanel($fila->refresh()));
    }

    /**
     * POST …/order — el orden nuevo de TODA la lista, con todos sus ids.
     *
     * Se pide la lista entera y no "sube uno": si dos personas ordenan a la
     * vez, el segundo que guarda no deja la lista con huecos ni repetidos;
     * deja exactamente el orden que estaba viendo.
     */
    public function ordenar(Request $peticion): JsonResponse
    {
        $ids = $peticion->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['required', 'string', 'distinct'],
        ])['ids'];

        $filas = $this->consulta($peticion)->get()->keyBy(fn (Model $f) => (string) $f->getKey());

        if (count($ids) !== $filas->count() || array_diff($ids, $filas->keys()->all()) !== []) {
            throw ValidationException::withMessages([
                'ids' => 'La lista cambió mientras la ordenabas. Vuelve a cargarla e inténtalo otra vez.',
            ]);
        }

        $this->guardando(function () use ($ids, $filas) {
            foreach (array_values($ids) as $i => $id) {
                $filas[$id]->update(['orden' => $i + 1]);
            }

            return null;
        });

        return $this->index($peticion);
    }

    public function destroy(Request $peticion, string $id): JsonResponse
    {
        $fila = $this->buscar($peticion, $id);

        $this->guardando(function () use ($fila) {
            $fila->delete();

            return $fila;
        });

        return $this->ok(['message' => 'Se eliminó ' . $this->entidad() . '.']);
    }

    // ───────── Ayudas ─────────

    /**
     * Aplica el cambio y solo lo confirma si el portal sigue cumpliendo su
     * contrato. Las rutas a validar se calculan con la fila ya cambiada; si
     * se eliminó, con null: su detalle ya no existe y no hay que validarlo.
     */
    protected function guardando(callable $cambio): mixed
    {
        return DB::transaction(function () use ($cambio) {
            $fila = $cambio();

            $tope = $this->topePublicados();
            if ($tope !== null && $fila instanceof Model && $fila->exists && $this->publicadosJuntoA($fila) > $tope) {
                throw ValidationException::withMessages([
                    'estado' => "El portal muestra hasta {$tope} {$this->plural()}. Para publicar más, primero pasa a borrador lo que ya no deba verse.",
                ]);
            }

            $problemas = app(ValidadorDeContrato::class)->problemas($this->rutasAfectadas($fila instanceof Model && $fila->exists ? $fila : null));

            if ($problemas !== []) {
                // Lanzarla deshace la transacción entera.
                throw ValidationException::withMessages(['contrato' => array_values($problemas)]);
            }

            return $fila;
        });
    }

    /**
     * Cuántos hay publicados en la misma lista que $fila. Por defecto, toda la
     * tabla; una tabla con varias listas (las cifras de cada página) la acota.
     */
    protected function publicadosJuntoA(Model $fila): int
    {
        return ($this->modelo())::where('estado', ($this->modelo())::PUBLICADO)->count();
    }

    /**
     * Una `clave` (el id que ve el portal) armada con un texto y que no se
     * repite: «Sede CATA Jerusalén» → «sede-cata-jerusalen», o -2, -3…
     * $acotar limita dónde no debe repetirse (las cifras, dentro de su página).
     */
    protected function claveUnica(string $texto, string $siNoHay, ?callable $acotar = null): string
    {
        $base = Str::limit(Str::slug($texto), 50, '') ?: $siNoHay;
        $existe = function (string $clave) use ($acotar) {
            $consulta = ($this->modelo())::where('clave', $clave);

            return ($acotar ? $acotar($consulta) : $consulta)->exists();
        };

        $clave = $base;
        for ($i = 2; $existe($clave); $i++) {
            $clave = "{$base}-{$i}";
        }

        return $clave;
    }

    protected function buscar(Request $peticion, string $id): Model
    {
        return $this->consulta($peticion)->whereKey($id)->firstOrFail();
    }

    private function reglaDeEstado(): array
    {
        return $this->tieneEstado()
            ? ['estado' => ['sometimes', Rule::in(($this->modelo())::ESTADOS)]]
            : [];
    }

    protected function ok(mixed $datos, int $estado = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $datos], $estado);
    }
}
