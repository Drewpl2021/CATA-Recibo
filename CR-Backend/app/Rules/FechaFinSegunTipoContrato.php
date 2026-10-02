<?php

namespace App\Rules;

use App\Models\TipoContrato;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reemplaza el viejo `required_unless:tipo_contrato,indeterminado`.
 *
 * Con el tipo de contrato como catálogo (antes de esto era un ENUM fijo con
 * 'indeterminado' como único valor especial), ya no hay un string fijo
 * contra el cual comparar: hay que preguntarle al tipo elegido si
 * `requiere_fecha_fin`. `DataAwareRule` es lo que le da acceso a la regla al
 * resto de los datos del formulario (el `tipo_contrato_id` elegido), no solo
 * al valor de su propio campo.
 */
class FechaFinSegunTipoContrato implements ValidationRule, DataAwareRule
{
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($value) {
            return;
        }

        $tipoId = $this->data['tipo_contrato_id'] ?? null;
        $tipo   = $tipoId ? TipoContrato::find($tipoId) : null;

        if ($tipo && $tipo->requiere_fecha_fin) {
            $fail('La fecha de fin es obligatoria para este tipo de contrato.');
        }
    }
}
