<?php

namespace App\Support\Portal;

use App\Models\Portal\Seccion;

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

    /** La ficha de una sección guardada, con sus imágenes ya resueltas. */
    public static function de(string $clave): self
    {
        return new self($clave, ImagenesEnJson::resolver(Seccion::contenido($clave)));
    }

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
        return $this->enteroOpcional($campo) ?? throw SeccionSinConfigurar::falta($this->seccion, $campo);
    }

    public function enteroOpcional(string $campo): ?int
    {
        $valor = data_get($this->contenido, $campo);

        if (is_int($valor) || (is_string($valor) && ctype_digit($valor))) {
            return (int) $valor;
        }

        return null;
    }

    /** Si el objeto opcional está puesto (p. ej. portalAcademico). */
    public function tiene(string $campo): bool
    {
        $valor = data_get($this->contenido, $campo);

        return is_array($valor) && $valor !== [];
    }

    /** Una lista de textos (párrafos). Si se exige, al menos uno. */
    public function textos(string $campo, bool $alMenosUno = false): array
    {
        $textos = Formato::textos((array) data_get($this->contenido, $campo, []));

        if ($alMenosUno && $textos === []) {
            throw SeccionSinConfigurar::falta($this->seccion, $campo);
        }

        return $textos;
    }

    /** Una imagen opcional (ya resuelta por ImagenesEnJson). */
    public function imagen(string $campo): ?array
    {
        return $this->tiene($campo) ? Formato::imagenDeArreglo(data_get($this->contenido, $campo)) : null;
    }

    public function accion(string $campo): array
    {
        return $this->accionOpcional($campo) ?? throw SeccionSinConfigurar::falta($this->seccion, $campo);
    }

    public function accionOpcional(string $campo): ?array
    {
        return $this->tiene($campo) ? Formato::accion(data_get($this->contenido, $campo)) : null;
    }

    /** Un objeto opcional como ficha propia, para leer sus campos. */
    public function ficha(string $campo): ?self
    {
        return $this->tiene($campo) ? new self("{$this->seccion}.{$campo}", data_get($this->contenido, $campo)) : null;
    }

    /**
     * Una lista de objetos, cada uno como ficha propia.
     *
     * @return self[]
     */
    public function fichas(string $campo): array
    {
        $lista = data_get($this->contenido, $campo, []);
        $lista = is_array($lista) ? array_values($lista) : [];

        return array_map(
            fn ($i) => new self("{$this->seccion}.{$campo}.{$i}", (array) $lista[$i]),
            array_keys($lista),
        );
    }

    /** Un texto sin espacios en los bordes; vacío vale null. */
    public static function limpio(?string $texto): ?string
    {
        $texto = $texto === null ? '' : trim($texto);

        return $texto === '' ? null : $texto;
    }
}
