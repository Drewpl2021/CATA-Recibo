<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Lo que comparten las filas de contenido del portal (App\Models\Portal):
 *
 *   - id uuid7, como el resto del sistema.
 *   - Auditoría: todo esto se va a editar desde el panel, y cada cambio
 *     queda anotado igual que los de RR.HH. Cada modelo dice qué campos
 *     vigilar en su propio $camposAuditables (va en el modelo y no aquí
 *     porque PHP no deja que una clase cambie una propiedad de su trait).
 *   - publicados(): lo que se muestra, en el orden del portal.
 */
trait ContenidoDelPortal
{
    use Auditable;

    public const PUBLICADO = 'publicado';
    public const BORRADOR = 'borrador';
    public const ESTADOS = [self::PUBLICADO, self::BORRADOR];

    public static function bootContenidoDelPortal(): void
    {
        static::creating(function ($modelo) {
            $modelo->{$modelo->getKeyName()} ??= (string) Str::uuid7();
        });
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    /** Lo que el portal muestra, en su orden. */
    public function scopePublicados(Builder $consulta): Builder
    {
        return $consulta->where($this->qualifyColumn('estado'), self::PUBLICADO)
            ->orderBy($this->qualifyColumn('orden'));
    }

    /** Cómo se lee la fila en la auditoría: "el banner «Matrículas 2026…»". */
    public function nombreAuditado(): string
    {
        $nombre = $this->titulo ?? $this->nombre ?? $this->pregunta ?? $this->nivel ?? $this->clave ?? $this->getKey();

        return '«' . Str::limit((string) $nombre, 60) . '»';
    }
}
