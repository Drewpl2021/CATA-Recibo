<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Universidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * /web-portal/universities — las universidades de la academia
 * preuniversitaria (contrato §3.25), hasta 8, cada una con sus ciclos
 * (/web-portal/cycles?universidad=…).
 *
 * El contrato pide al menos un ciclo por universidad publicada: sin ciclos
 * se queda en borrador, con un mensaje que lo dice.
 */
class UniversidadesController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return Universidad::class;
    }

    protected function entidad(): string
    {
        return 'la universidad';
    }

    protected function topePublicados(): ?int
    {
        return 8;
    }

    protected function plural(): string
    {
        return 'universidades publicadas';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/propuesta-educativa/academia'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        return ['nombre' => [$fila ? 'sometimes' : 'required', 'string', 'max:80']];
    }

    protected function atributos(): array
    {
        return ['nombre' => 'nombre'];
    }

    protected function guardando(callable $cambio): mixed
    {
        return parent::guardando(function () use ($cambio) {
            $fila = $cambio();

            if ($fila instanceof Universidad && $fila->exists && $fila->estado === Universidad::PUBLICADO && $fila->ciclos()->doesntExist()) {
                throw ValidationException::withMessages([
                    'estado' => "Agrega al menos un ciclo a «{$fila->nombre}» antes de publicarla.",
                ]);
            }

            return $fila;
        });
    }

    /** Se lleva sus ciclos, uno por uno, para que la auditoría los registre. */
    public function destroy(Request $peticion, string $id): JsonResponse
    {
        $universidad = $this->buscar($peticion, $id);

        $this->guardando(function () use ($universidad) {
            $universidad->ciclos->each->delete();
            $universidad->delete();

            return $universidad;
        });

        return $this->ok(['message' => 'Se eliminó ' . $this->entidad() . ' con sus ciclos.']);
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (! $fila) {
            // El id del filtro en la dirección (?universidad=…): se arma una vez y no cambia.
            $v['clave'] = $this->claveUnica($v['nombre'], 'universidad');
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'nombre', 'orden', 'estado']) + [
            'ciclos' => $fila->ciclos()->count(),
        ];
    }
}
