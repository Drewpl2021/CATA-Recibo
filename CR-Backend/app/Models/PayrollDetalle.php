<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PayrollDetalle extends Model
{
    protected $table = 'payroll_detalles';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'planilla_id', 'payment_concept_id', 'monto_calculado', 'estado'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid());
    }

    public function planilla()
    {
        return $this->belongsTo(Planilla::class, 'planilla_id');
    }

    public function paymentConcept()
    {
        return $this->belongsTo(PaymentConcept::class, 'payment_concept_id');
    }
}