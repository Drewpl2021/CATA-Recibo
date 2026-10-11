<?php

namespace App\Support\Portal;

/**
 * Los mensajes de las reglas que usa el panel del portal y que la
 * traducción del sistema (lang/es/validation.php) no trae: sin esto, quien
 * edita vería «validation.starts_with». Van aquí, y no en ese archivo, para
 * no tocar el sistema.
 *
 * En el panel, starts_with siempre es «https://» y prohibited siempre es un
 * campo que no cambia después de creado.
 */
final class MensajesDeValidacion
{
    public const MENSAJES = [
        'starts_with' => 'El campo :attribute debe empezar con :values.',
        'decimal'     => 'El campo :attribute admite hasta dos decimales.',
        'prohibited'  => 'El campo :attribute no se puede cambiar.',
    ];
}
