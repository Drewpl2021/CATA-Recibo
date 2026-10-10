<?php

namespace App\Support\Portal;

/**
 * Lee el contenido JSON de una sección del portal con las reglas del contrato.
 *
 * El contrato es estricto: todos los campos van siempre (un campo que falta,
 * aunque admita null, hace que el portal descarte la respuesta) y los textos
 * van sin espacios al inicio ni al final. Esto lo garantiza al leer, campo
 * por campo, en vez de devolver el JSON tal como se guardó:
 *
 *   - un obligatorio que falta o está vacío → SeccionSinConfigurar (503), y
 *     el registro dice cuál; nunca se rellena con un texto escrito aquí.
 *   - un opcional que falta o está vacío → null.
 *
 * Los campos anidados se piden con punto: 'telefono.numero'.
 */
class Ficha
{
    public function __construct(
        private readonly string $seccion,
        private readonly array $contenido,
    ) {}

    public function texto(string $campo): string
    {
        return $this->textoOpcional($campo) ?? throw SeccionSinConfigurar::falta($this->seccion, $campo);
    }

    public function textoOpcional(string $campo): ?string
    {
        $valor = data_get($this->contenido, $campo);

        return is_scalar($valor) ? self::limpio((string) $valor) : null;
    }

    public function entero(string $campo): int
    {
        $valor = data_get($this->contenido, $campo);

        if (! is_int($valor) && ! (is_string($valor) && ctype_digit($valor))) {
            throw SeccionSinConfigurar::falta($this->seccion, $campo);
        }

        return (int) $valor;
    }

    /** Si el objeto opcional está puesto (p. ej. portalAcademico). */
    public function tiene(string $campo): bool
    {
        $valor = data_get($this->contenido, $campo);

        return is_array($valor) && $valor !== [];
    }

    /** Un texto sin espacios en los bordes; vacío vale null. */
    public static function limpio(?string $texto): ?string
    {
        $texto = $texto === null ? '' : trim($texto);

        return $texto === '' ? null : $texto;
    }
}
