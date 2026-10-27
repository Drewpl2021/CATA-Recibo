<?php

namespace App\Support\Portal\Fichas;

use App\Models\Portal\Pagina;
use App\Support\Portal\Navegacion;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * El menú del portal, los grupos de Nosotros y los títulos del pie
 * (contrato 2.13, §3.27). Las subpáginas de cada grupo no van aquí: cada
 * página dice su grupo y su nombre en el menú (Nosotros › Subpáginas).
 *
 * Un grupo con páginas no se puede quitar: sus páginas quedarían fuera del
 * menú sin que nadie lo decida. Primero se mueven.
 */
class FichaNavegacion implements Ficha
{
    public function nombre(): string
    {
        return 'el menú y los grupos de Nosotros';
    }

    public function reglas(): array
    {
        return [
            'menu'              => ['required', 'array', 'min:1', 'max:' . count(Navegacion::PAGINAS)],
            'menu.*'            => ['array:pagina,etiqueta'],
            'menu.*.pagina'     => ['required', 'distinct', Rule::in(Navegacion::PAGINAS)],
            'menu.*.etiqueta'   => ['required', 'string', 'max:24'],
            'grupos'            => ['present', 'array', 'max:' . Navegacion::MAXIMO_DE_GRUPOS, $this->sinDejarPaginasSueltas()],
            'grupos.*'          => ['array:id,titulo'],
            // El de un grupo nuevo se arma con su título.
            'grupos.*.id'       => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'distinct'],
            'grupos.*.titulo'   => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'pie'               => ['required', 'array:' . implode(',', Navegacion::PIE)],
            ...collect(Navegacion::PIE)->mapWithKeys(fn ($campo) => ["pie.{$campo}" => ['required', 'string', 'max:30']])->all(),
        ];
    }

    public function atributos(): array
    {
        return [
            'menu'            => 'menú',
            'menu.*.pagina'   => 'página del menú',
            'menu.*.etiqueta' => 'nombre en el menú',
            'grupos'          => 'grupos de Nosotros',
            'grupos.*.id'     => 'dirección del grupo',
            'grupos.*.titulo' => 'título del grupo',
            'pie.colegio'     => 'título de la columna de páginas del pie',
            'pie.nosotros'    => 'título de la columna de Nosotros del pie',
            'pie.contacto'    => 'título de la columna de contacto del pie',
            'pie.redes'       => 'título de las redes («Síguenos»)',
        ];
    }

    public function aContenido(array $v): array
    {
        $usados = [];
        $grupos = [];
        foreach (array_values($v['grupos'] ?? []) as $grupo) {
            $id = $grupo['id'] ?? null ?: (Str::slug($grupo['titulo']) ?: 'grupo');
            for ($base = $id, $n = 2; in_array($id, $usados, true); $n++) {
                $id = "{$base}-{$n}";
            }
            $usados[] = $id;
            $grupos[] = ['id' => $id, 'titulo' => $grupo['titulo']];
        }

        return [
            'menu'   => array_map(fn ($m) => ['pagina' => $m['pagina'], 'etiqueta' => $m['etiqueta']], array_values($v['menu'])),
            'grupos' => $grupos,
            'pie'    => collect(Navegacion::PIE)->mapWithKeys(fn ($campo) => [$campo => $v['pie'][$campo]])->all(),
        ];
    }

    public function rutas(): array
    {
        return ['/v1/navegacion'];
    }

    /** Un grupo que se quita no puede tener páginas: dejarían de verse en el menú. */
    private function sinDejarPaginasSueltas(): Closure
    {
        return function (string $atributo, mixed $grupos, Closure $falla) {
            $quedan = collect(is_array($grupos) ? $grupos : [])->pluck('id')->filter()->all();
            $antes = Navegacion::grupos();

            $suelto = Pagina::whereNotNull('grupo')->whereNotIn('grupo', $quedan)->orderBy('orden')->first();
            if ($suelto) {
                $titulo = $antes[$suelto->grupo] ?? $suelto->grupo;
                $falla("El grupo «{$titulo}» tiene páginas (como «{$suelto->titulo}»). Pásalas a otro grupo o sácalas del menú antes de quitarlo.");
            }
        };
    }
}
