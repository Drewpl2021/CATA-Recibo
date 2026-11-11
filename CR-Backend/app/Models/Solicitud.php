<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Una solicitud del trabajador a RR.HH. (permiso, licencia, descanso médico,
 * justificación de falta o cambio de cuenta). Ver la migración y
 * App\Support\Solicitudes\Solicitudes.
 *
 * `estado`, `resuelto_por`, `efectos` y `sustento_visto_por` los escribe
 * solo el servidor: el controlador pasa campo por campo, nunca el cuerpo de
 * la petición (como en Vacacion, donde así se podía pedir ya aprobada).
 */
class Solicitud extends Model
{
    protected $table = 'solicitudes';
    protected $keyType = 'string';
    public $incrementing = false;

    public const PERMISO          = 'permiso';
    public const LICENCIA         = 'licencia';
    public const DESCANSO_MEDICO  = 'descanso_medico';
    public const JUSTIFICAR_FALTA = 'justificar_falta';
    public const CAMBIO_CUENTA    = 'cambio_cuenta';

    public const TIPOS = [
        self::PERMISO          => 'Permiso por horas',
        self::LICENCIA         => 'Licencia',
        self::DESCANSO_MEDICO  => 'Descanso médico',
        self::JUSTIFICAR_FALTA => 'Justificación de falta',
        self::CAMBIO_CUENTA    => 'Cambio de cuenta',
    ];

    /** Los que ocupan días enteros: no se pueden cruzar entre sí ni con vacaciones. */
    public const CON_DIAS = [self::LICENCIA, self::DESCANSO_MEDICO, self::JUSTIFICAR_FALTA];

    /** Siguen contando para cruces y para la CTS. */
    public const VIGENTES = ['pendiente', 'aprobada'];

    protected $fillable = [
        'empleado_id', 'tipo', 'fecha_inicio', 'fecha_fin', 'hora_inicio', 'hora_fin',
        'con_goce', 'motivo', 'datos', 'sustento', 'sustento_nombre', 'sustento_visto_por',
        'estado', 'respuesta', 'resuelto_por', 'resuelto_at', 'efectos',
    ];

    /** La ruta del archivo no sale: se baja por su propia dirección, con permiso. */
    protected $hidden = ['sustento'];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin'    => 'date:Y-m-d',
        'con_goce'     => 'boolean',
        'datos'        => 'array',
        'efectos'      => 'array',
        'resuelto_at'  => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: Str::uuid7());
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function resolutor()
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    /** Licencias sin goce aprobadas: las que le descuentan días a la planilla y a la CTS. */
    public function scopeSinGoceAprobadas(Builder $q, string $empleadoId): Builder
    {
        return $q->where('empleado_id', $empleadoId)->where('tipo', self::LICENCIA)
            ->where('con_goce', false)->where('estado', 'aprobada');
    }

    /** Cuántos días de calendario abarca (ambos extremos incluidos). */
    public function dias(): int
    {
        if (! $this->fecha_inicio || ! $this->fecha_fin) {
            return 0;
        }

        return (int) $this->fecha_inicio->diffInDays($this->fecha_fin) + 1;
    }

    /** «Licencia sin goce», «Cambio de cuenta CTS»: como lo lee una persona. */
    public function nombre(): string
    {
        return match ($this->tipo) {
            self::LICENCIA      => 'Licencia ' . ($this->con_goce ? 'con goce' : 'sin goce'),
            self::PERMISO       => 'Permiso ' . ($this->con_goce ? 'con goce' : 'sin goce'),
            self::CAMBIO_CUENTA => 'Cambio de cuenta ' . (($this->datos['destino'] ?? '') === 'cts' ? 'CTS' : 'de sueldo'),
            default             => self::TIPOS[$this->tipo] ?? $this->tipo,
        };
    }
}
