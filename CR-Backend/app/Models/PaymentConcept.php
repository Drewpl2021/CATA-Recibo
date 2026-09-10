<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentConcept extends Model
{
    protected $table = 'payment_concepts';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'nombre',
        'etiqueta_boleta', 'tipo', 'calculo', 'valor', 'descripcion', 'aplica_a_todos'];

    /**
     * Se manda con cada concepto para que la pantalla sepa cuáles puede
     * ofrecer en "aplicar a un grupo".
     *
     * La lista de los seis vive en App\Support\ConceptosDePago y el backend
     * los rechaza con 422. Si el frontend repitiera esa lista por su cuenta,
     * el día que cambie uno habría dos verdades: la pantalla ofreciendo algo
     * que el servidor no acepta.
     */
    protected $appends = ['calculo_especial'];

    public function getCalculoEspecialAttribute(): bool
    {
        return in_array($this->nombre, \App\Support\ConceptosDePago::CALCULO_ESPECIAL, true);
    }

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