<?php

namespace App\Support\Portal;

use Illuminate\Validation\Rule;

/**
 * Un botón del portal (`AccionHero`, contrato §3.3): su etiqueta y a dónde
 * lleva (`Destino`, §1.8), que es una de tres cosas:
 *
 *   { tipo: 'seccion', seccion: 'sedes' }          una sección de Inicio
 *   { tipo: 'pagina',  ruta: '/matricula' }         otra página del portal
 *   { tipo: 'url',     url: 'https://…', externo }  una dirección
 *
 * Las reglas y la forma de guardarlo, en un solo lugar: lo usan los banners
 * y, más adelante, la cabecera de Propuesta Educativa.
 */
final class ReglasDeAccion
{
    /** Las secciones de Inicio a las que puede llevar un botón. */
    public const SECCIONES = ['quienes-somos', 'niveles', 'sedes', 'noticias', 'contacto'];

    public const TIPOS = ['seccion', 'pagina', 'url'];

    /**
     * Las reglas de una acción en `$campo` ('accion').
     *
     * @param  bool  $obligatoria  si el botón no puede faltar
     */
    public static function reglas(string $campo, bool $obligatoria = false): array
    {
        $tipo = "{$campo}.destino.tipo";

        return [
            $campo                         => [$obligatoria ? 'required' : 'nullable', 'array'],
            "{$campo}.etiqueta"            => ["required_with:{$campo}", 'string', 'max:32'],
            "{$campo}.destino"             => ["required_with:{$campo}", 'array'],
            $tipo                          => ["required_with:{$campo}", Rule::in(self::TIPOS)],
            "{$campo}.destino.seccion"     => ["required_if:{$tipo},seccion", 'nullable', Rule::in(self::SECCIONES)],
            "{$campo}.destino.ruta"        => ["required_if:{$tipo},pagina", 'nullable', 'string', 'max:120', 'regex:#^/[a-z0-9/-]*$#'],
            "{$campo}.destino.url"         => ["required_if:{$tipo},url", 'nullable', 'url', 'starts_with:https://', 'max:2048'],
            "{$campo}.destino.externo"     => ['sometimes', 'boolean'],
        ];
    }

    /** Cómo se leen esos campos en los mensajes de error. */
    public static function atributos(string $campo): array
    {
        return [
            "{$campo}.etiqueta"        => 'texto del botón',
            "{$campo}.destino.tipo"    => 'a dónde lleva el botón',
            "{$campo}.destino.seccion" => 'sección',
            "{$campo}.destino.ruta"    => 'página',
            "{$campo}.destino.url"     => 'dirección',
        ];
    }

    /** Lo validado → lo que se guarda, con solo los campos de su tipo. null si no hay botón. */
    public static function guardar(?array $accion): ?array
    {
        if (! $accion || blank($accion['etiqueta'] ?? null)) {
            return null;
        }

        $d = $accion['destino'];

        return [
            'etiqueta' => $accion['etiqueta'],
            'destino'  => match ($d['tipo']) {
                'seccion' => ['tipo' => 'seccion', 'seccion' => $d['seccion']],
                'pagina'  => ['tipo' => 'pagina', 'ruta' => $d['ruta']],
                'url'     => ['tipo' => 'url', 'url' => $d['url'], 'externo' => (bool) ($d['externo'] ?? true)],
            },
        ];
    }
}
