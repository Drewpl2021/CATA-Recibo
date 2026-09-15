<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Un nombre con que RR.HH. llama a un concepto en sus Excel ("Movilidad"
 * para "Planilla de Movilidad"). Lo usa App\Support\ReconocedorDeColumnas.
 */
class ConceptoAlias extends Model
{
    protected $table = 'concepto_alias';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['alias', 'payment_concept_id', 'confirmado_por'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid7());
    }

    public function paymentConcept()
    {
        return $this->belongsTo(PaymentConcept::class, 'payment_concept_id');
    }
}
