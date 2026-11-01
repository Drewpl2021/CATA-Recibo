<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Once;

/**
 * Un cambio del sueldo mínimo (RMV): cuánto y desde qué mes. Ver
 * ValorLegal::rmvDelMes.
 */
class RmvVigencia extends Model
{
    use Auditable;

    protected $table = 'rmv_vigencias';

    protected $fillable = ['vigente_desde', 'monto'];

    protected $casts = [
        'vigente_desde' => 'date:Y-m-d',
        'monto'         => 'float',
    ];

    protected array $camposAuditables = ['vigente_desde', 'monto'];
    protected string $entidadAuditada = 'cambio del sueldo mínimo';

    public function nombreAuditado(): string
    {
        return 'el sueldo mínimo desde ' . $this->vigente_desde?->format('m/Y');
    }

    protected static function booted(): void
    {
        // Si se cambian, lo leído antes ya no vale.
        static::saved(fn () => Once::flush());
        static::deleted(fn () => Once::flush());
    }

    /** Todos, del más antiguo al más reciente. Una hoja de 5ta los pide decenas de veces. */
    public static function todas()
    {
        return once(fn () => static::orderBy('vigente_desde')->get());
    }
}
