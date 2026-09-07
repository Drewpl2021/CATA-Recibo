<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Un aviso guardado para un trabajador.
 *
 * Hoy solo se crea uno: "ya está tu boleta del mes, fírmala". Queda
 * grabado con su fecha, así que el docente puede volver y ver cuándo le
 * llegó cada una, no solo cuántas tiene pendientes ahora.
 */
class Notificacion extends Model
{
    protected $table = 'notificaciones';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'tipo',
        'titulo',
        'mensaje',
        'documento_id',
        'leida_at',
    ];

    protected $casts = [
        'leida_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** La boleta a la que lleva el aviso. Puede faltar si se borró. */
    public function documento()
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /** Para la respuesta del API: el front no tiene que mirar leida_at. */
    public function getLeidaAttribute(): bool
    {
        return $this->leida_at !== null;
    }

    protected $appends = ['leida'];
}
