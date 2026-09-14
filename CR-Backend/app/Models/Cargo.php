<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Cargo extends Model
{
    protected $table = 'cargos';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'nombre', 'descripcion', 'estado'];

    /**
     * Las áreas donde este cargo tiene sentido.
     *
     * SIN ninguna = comodín: vale en todas. Es lo que deja que "Practicante"
     * o "Voluntario Misionero" sigan sirviendo en cualquier área sin tener
     * que marcarlos uno por uno en las quince.
     */
    public function areas()
    {
        return $this->belongsToMany(Area::class, 'area_cargo');
    }

    /** Un cargo sin áreas marcadas entra en cualquiera. */
    public function valeEnElArea(?string $areaId): bool
    {
        if (! $areaId || $this->areas->isEmpty()) {
            return true;
        }

        return $this->areas->contains('id', $areaId);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid7());
    }
}