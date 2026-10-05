<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Los montos de ley de un año. Ver la migración de valores_legales.
 */
class ValorLegal extends Model
{
    use Auditable;

    protected $table = 'valores_legales';
    protected $primaryKey = 'anio';
    public $incrementing = false;
    protected $keyType = 'int';

    public const CAMPOS = [
        'uit', 'rmv', 'asignacion_familiar', 'onp', 'essalud', 'aporte_afp', 'prima_seguro_afp',
        'comision_habitat', 'comision_integra', 'comision_prima', 'comision_profuturo',
    ];

    /** La denominación oficial del año. Va aparte de CAMPOS: no es un monto y no se copia al año siguiente. */
    protected $fillable = ['anio', 'nombre_anio', ...self::CAMPOS];

    protected $casts = [
        'anio'                => 'integer',
        'uit'                 => 'float',
        'rmv'                 => 'float',
        'asignacion_familiar' => 'float',
        'onp'                 => 'float',
        'essalud'             => 'float',
        'aporte_afp'          => 'float',
        'prima_seguro_afp'    => 'float',
        'comision_habitat'    => 'float',
        'comision_integra'    => 'float',
        'comision_prima'      => 'float',
        'comision_profuturo'  => 'float',
    ];

    protected array $camposAuditables = ['nombre_anio', ...self::CAMPOS];
    protected string $entidadAuditada = 'montos de ley';

    public function nombreAuditado(): string
    {
        return "los montos de ley de {$this->anio}";
    }

    /** @var array<int, self> Los ya leídos en esta petición: una planilla los pide varias veces. */
    private static array $leidos = [];

    protected static function booted(): void
    {
        // Si se cambian, lo leído antes ya no vale.
        static::saved(fn () => self::$leidos = []);
    }

    /**
     * Los de ese año. Si ese año no está cargado, los del último año
     * anterior que sí esté (los montos siguen hasta que la ley los cambia);
     * y si es anterior a todos, los del primero que haya.
     */
    public static function delAnio(int $anio): self
    {
        return self::$leidos[$anio] ??= static::where('anio', '<=', $anio)->orderByDesc('anio')->first()
            ?? static::orderBy('anio')->firstOrFail();
    }

    /**
     * La denominación oficial de ESE año, para la boleta. A diferencia de los
     * montos, no se hereda del año anterior: cada año tiene la suya, y si
     * falta es mejor no imprimir nada que imprimir la del año pasado.
     */
    public static function nombreDelAnio(int $anio): ?string
    {
        $nombre = static::where('anio', $anio)->value('nombre_anio');

        return $nombre !== null && trim($nombre) !== '' ? trim($nombre) : null;
    }

    /** La comisión por flujo de esa AFP, en %. 0 si no es una de las cuatro. */
    public function comisionAfp(?string $afp): float
    {
        return match ($afp) {
            'Habitat'   => $this->comision_habitat,
            'Integra'   => $this->comision_integra,
            'Prima'     => $this->comision_prima,
            'Profuturo' => $this->comision_profuturo,
            default     => 0.0,
        };
    }
}
