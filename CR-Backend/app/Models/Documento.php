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
        'tipo',
        'archivo',
        'firmado_por',
        'codigo_firma',
        'fecha_firma',
        'estado_firma',
        'planilla_id',
        'fecha_visto',
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

    public function planilla()
    {
        return $this->belongsTo(Planilla::class);
    }
}