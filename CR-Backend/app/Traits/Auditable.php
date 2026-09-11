<?php

namespace App\Traits;

use App\Models\Auditoria;

/**
 * Anota en la auditoría las altas, los cambios y las bajas de un modelo.
 *
 * Cada modelo dice qué le importa:
 *
 *   protected array $camposAuditables = ['sueldo_base', 'estado', ...];
 *   protected array $camposSecretos   = ['password'];   // se anota QUE cambió, nunca el valor
 *   protected string $entidadAuditada = 'empleado';
 *   public function nombreAuditado(): string { ... }     // "Wilber Apaza (DNI 42558107)"
 *
 * En los cambios solo se anotan los campos de la lista, con el valor de antes
 * y el de después. Si lo que cambió no está en la lista (una fecha de
 * último acceso, por ejemplo) no se anota nada: el registro es para
 * responder preguntas, no para guardarlo todo.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($modelo) => $modelo->anotarEnAuditoria('creó'));
        static::updated(fn ($modelo) => $modelo->anotarEnAuditoria('cambió'));
        static::deleted(fn ($modelo) => $modelo->anotarEnAuditoria('borró'));
    }

    protected function anotarEnAuditoria(string $accion): void
    {
        $cambios = null;

        if ($accion === 'cambió') {
            $cambios = [];
            $vigilados = $this->camposAuditables ?? [];
            $secretos  = $this->camposSecretos ?? [];

            foreach (array_keys($this->getChanges()) as $campo) {
                if (! in_array($campo, $vigilados, true)) {
                    continue;
                }

                // En el evento "updated" el original todavía es el de antes
                // de guardar: Laravel lo sincroniza después.
                $cambios[$campo] = in_array($campo, $secretos, true)
                    ? ['(oculto)', '(oculto)']
                    : [$this->getOriginal($campo), $this->getAttribute($campo)];
            }

            if (! $cambios) {
                return;
            }
        }

        $entidad = $this->entidadAuditada ?? strtolower(class_basename($this));
        $nombre  = method_exists($this, 'nombreAuditado') ? $this->nombreAuditado() : (string) $this->getKey();

        $descripcion = match ($accion) {
            'creó'   => "Dio de alta: {$nombre}",
            'borró'  => "Borró: {$nombre}",
            default  => 'Cambió ' . $this->enumerarCampos(array_keys($cambios)) . " de {$nombre}",
        };

        Auditoria::registrar($accion, $entidad, (string) $this->getKey(), $descripcion, $cambios);
    }

    /** "el sueldo, el AFP y la cuenta" a partir de los nombres de columna. */
    private function enumerarCampos(array $campos): string
    {
        $legibles = array_map(fn ($c) => str_replace('_', ' ', $c), $campos);

        if (count($legibles) === 1) {
            return $legibles[0];
        }

        $ultimo = array_pop($legibles);

        return implode(', ', $legibles) . ' y ' . $ultimo;
    }
}
