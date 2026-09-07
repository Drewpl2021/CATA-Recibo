<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentConcept extends Model
{
    protected $table = 'payment_concepts';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'nombre', 'tipo', 'calculo', 'valor', 'descripcion', 'aplica_a_todos'];

    protected function casts(): array
    {
        return ['aplica_a_todos' => 'boolean'];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid());
    }

    public function payrollDetalles()
    {
        return $this->hasMany(PayrollDetalle::class, 'payment_concept_id');
    }
}