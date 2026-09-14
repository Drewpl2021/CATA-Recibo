<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Area extends Model
{
    protected $table = 'areas';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'nombre', 'descripcion', 'estado'];

    /**
     * Los cargos acotados a esta área.
     *
     * Ojo al leerlo: los cargos comodín (sin ningún área) NO salen aquí y
     * sin embargo sí valen en esta área. Para "qué puedo elegir en esta
     * área" se usa CargoController@index con ?area_id=, que suma los dos.
     */
    public function cargos()
    {
        return $this->belongsToMany(Cargo::class, 'area_cargo');
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid7());
    }
}