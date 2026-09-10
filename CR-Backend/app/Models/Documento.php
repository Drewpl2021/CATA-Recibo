<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Documento extends Model
{
    protected $table = 'documentos';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'empleado_id',
        'contrato_id',
        'tipo',
        'archivo',
        'firmado_por',
        'codigo_firma',
        'fecha_firma',
        'estado_firma',
        'planilla_id',
        'fecha_visto',
        'fecha_aviso',
        'aviso_correo',
        'fecha_descarga',
        'descargas',
        'estado_registro',
        'empleador_id',
        'firmado_por_empleador',
        'codigo_firma_empleador',
        'fecha_firma_empleador',
        'estado_firma_empleador',
    ];

    /**
     * Sin esto salían como texto suelto ("2026-09-08 15:13:30"), sin decir de
     * qué huso: el navegador lo leía como hora local y pintaba las cifras de
     * UTC como si fueran de Juliaca.
     */
    protected $casts = [
        'fecha_firma'           => 'datetime',
        'fecha_firma_empleador' => 'datetime',
        'fecha_visto'           => 'datetime',
        'fecha_aviso'           => 'datetime',
        'fecha_descarga'        => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::uuid();
        });
    }

    /**
     * Deja constancia de que el trabajador se bajó su boleta.
     *
     * La fecha se guarda solo la PRIMERA vez —es la que responde "¿desde
     * cuándo la tiene?"— y el contador sube siempre. Se llama únicamente
     * cuando la baja el propio trabajador: que RR.HH. abra el PDF para
     * revisarlo no significa que el trabajador la haya recibido.
     */
    public function registrarDescarga(): void
    {
        $this->forceFill([
            'fecha_descarga' => $this->fecha_descarga ?? now(),
            'descargas'      => (int) $this->descargas + 1,
        ])->save();
    }

    /**
     * Deja constancia de a quién y cuándo se le avisó que la boleta ya está.
     *
     * El correo se congela: si el trabajador lo cambia el mes que viene, la
     * boleta de este mes tiene que seguir diciendo a dónde se mandó.
     */
    public function registrarAviso(?string $correo): void
    {
        $this->forceFill([
            'fecha_aviso'  => now(),
            'aviso_correo' => $correo,
        ])->save();
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }

    public function planilla()
    {
        return $this->belongsTo(Planilla::class);
    }

    public function empleador()
    {
        return $this->belongsTo(Empleado::class, 'empleador_id');
    }
}