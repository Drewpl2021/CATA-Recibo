<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentConcept extends Model
{
    protected $table = 'payment_concepts';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'nombre', 'tipo', 'calculo', 'valor', 'descripcion'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid());
    }
}