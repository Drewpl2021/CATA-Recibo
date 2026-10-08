<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un ajuste del sistema: clave → valor. Ver la migración de configuraciones.
 */
class Configuracion extends Model
{
    use Auditable;

    protected $table = 'configuraciones';
    protected $primaryKey = 'clave';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['clave', 'valor'];

    protected array $camposAuditables = ['valor'];
    protected string $entidadAuditada = 'configuración';

    /** Lo que se lee en la auditoría en vez de la clave técnica. */
    public const NOMBRES = [
        'permitir_anios_anteriores' => 'el ajuste «Planillas de años anteriores»',
        'renta5ta_como_hoja_rrhh'   => 'el ajuste «Renta de 5ta como la hoja de RR.HH.»',
        'boleta_firmas_requeridas'  => 'el ajuste «Firmas del colegio por boleta»',
    ];

    public function nombreAuditado(): string
    {
        return self::NOMBRES[$this->clave] ?? $this->clave;
    }

    /** Un ajuste de sí/no. Si no está en la base, vale lo que diga $porDefecto. */
    public static function activo(string $clave, bool $porDefecto = false): bool
    {
        $valor = static::whereKey($clave)->value('valor');

        return $valor === null ? $porDefecto : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    /** Un ajuste numérico. Si no está en la base, vale $porDefecto. */
    public static function numero(string $clave, int $porDefecto): int
    {
        $valor = static::whereKey($clave)->value('valor');

        return is_numeric($valor) ? (int) $valor : $porDefecto;
    }

    public static function ponerNumero(string $clave, int $valor): void
    {
        $fila = static::firstOrNew(['clave' => $clave]);
        $fila->valor = (string) $valor;
        $fila->save();
    }

    public static function poner(string $clave, bool $valor): void
    {
        $fila = static::firstOrNew(['clave' => $clave]);
        $fila->valor = $valor ? '1' : '0';
        $fila->save();
    }
}
