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
        'estado_registro',
        'empleador_id',
        'firmado_por_empleador',
        'codigo_firma_empleador',
        'fecha_firma_empleador',
        'estado_firma_empleador',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::uuid();
        });
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