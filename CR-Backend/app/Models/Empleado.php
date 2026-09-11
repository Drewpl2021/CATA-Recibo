<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Empleado extends Model
{
    use \App\Traits\Auditable;

    /**
     * Lo que se anota en la auditoría cuando cambia: lo que mueve el dinero
     * de la persona (sueldo, pensión, cuenta) o su situación (cargo, sede,
     * estado). El teléfono o la dirección no responden a ningún reclamo.
     */
    protected array $camposAuditables = [
        'dni', 'nombre', 'apellido', 'sueldo_base', 'sistema_pensiones', 'afp', 'cuspp',
        'entidad_financiera', 'numero_cuenta', 'forma_pago', 'tiene_hijos',
        'cargo_id', 'area_id', 'sede_id', 'estado', 'tipo_contrato',
    ];

    protected string $entidadAuditada = 'empleado';

    public function nombreAuditado(): string
    {
        return trim("{$this->nombre} {$this->apellido}") . " (DNI {$this->dni})";
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
    'dni',
    'nombre',
    'apellido',
    'cargo_id',
    'area_id',
    'telefono',
    'direccion',
    'fecha_ingreso',
    'estado',
    'sistema_pensiones',
    'afp',
    'cuspp',
    'entidad_financiera',
    'numero_cuenta',
    'tiene_hijos',
    'sueldo_base',
    'tipo_contrato',
    'forma_pago',
    'sede_id',
    'nivel_estudios',
    'especialidad',
    'institucion_estudios',
    'contacto_emergencia_nombre',
    'contacto_emergencia_telefono',
    'fecha_nacimiento',
    ];
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::uuid7();
        });
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }
    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function usuario()
    {
        return $this->hasOne(User::class, 'empleado_id');
    }

    public function contratos()
    {
        return $this->hasMany(Contrato::class);
    }

    public function identidadFirma()
    {
        return $this->hasOne(IdentidadFirma::class);
    }
}