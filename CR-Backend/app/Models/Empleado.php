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
        'entidad_financiera', 'numero_cuenta', 'cci', 'forma_pago', 'tiene_hijos',
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
    'fecha_cese',
    'estado',
    'sistema_pensiones',
    'afp',
    'cuspp',
    'entidad_financiera',
    'numero_cuenta',
    'cci',
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

    /**
     * Le cierra la puerta: sus cuentas quedan inactivas y las sesiones que
     * tenga abiertas se cortan. Lo usan la baja desde Empleados y la
     * importación, cuando alguien pasa a cesado.
     */
    public function quitarAcceso(): void
    {
        User::where('empleado_id', $this->id)->each(function (User $user) {
            $user->update(['estado_registro' => 'inactivo']);
            $user->tokens()->delete();
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

    /** Todo lo que hay en su expediente: boletas, hoja de vida, contratos firmados… */
    public function documentos()
    {
        return $this->hasMany(Documento::class);
    }

    /**
     * El contrato que manda hoy.
     *
     * `empleados.tipo_contrato` es una copia suelta que quedó del alta y
     * envejece: quien entró por suplencia y ya pasó a planilla fija sigue
     * teniéndola ahí. La verdad está en su contrato vigente, y solo se cae a
     * la copia cuando no hay ninguno (fichas viejas, antes del módulo de
     * Contratos).
     */
    public function contratoVigente()
    {
        return $this->hasOne(Contrato::class)
            ->where('estado', 'vigente')
            ->latest('fecha_inicio');
    }

    public function tipoContratoVigente(): ?string
    {
        return $this->contratoVigente()->first()?->tipo_contrato ?? $this->tipo_contrato;
    }

    /**
     * Quién puede PEDIR vacaciones.
     *
     * Solo el contrato indeterminado. A los otros tres —plazo fijo,
     * suplencia y prácticas— el colegio no les da descanso a cuenta: se les
     * paga con el concepto "Vacaciones Truncas" al terminar el contrato.
     *
     * Está acá y no en el controlador a propósito: la regla la consultan el
     * alta de la solicitud, la aprobación y la pantalla de saldo, y con tres
     * copias basta con que alguien cambie una para que el sistema deje pasar
     * por un lado lo que niega por el otro.
     */
    public function puedeTomarVacaciones(): bool
    {
        return $this->tipoContratoVigente() === 'indeterminado';
    }

    public function identidadFirma()
    {
        return $this->hasOne(IdentidadFirma::class);
    }
}