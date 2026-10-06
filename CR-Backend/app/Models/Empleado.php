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
        'dni', 'nombre', 'apellido', 'sueldo_base', 'bonificacion_cargo', 'sistema_pensiones', 'afp', 'tipo_comision_afp', 'cuspp', 'fecha_afiliacion',
        'entidad_financiera', 'numero_cuenta', 'cci', 'forma_pago', 'tiene_hijos', 'aplica_diezmo',
        'cargo_id', 'area_id', 'sede_id', 'estado', 'tipo_contrato_id',
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
    'tipo_comision_afp',
    'cuspp',
    // Desde cuándo está en su AFP u ONP. Opcional, no entra en cálculos.
    'fecha_afiliacion',
    'entidad_financiera',
    'numero_cuenta',
    'cci',
    'tiene_hijos',
    'aplica_diezmo',
    'sueldo_base',
    // Monto fijo al mes (la "Bonificación por Función" del PLAME): cada planilla la trae sola.
    'bonificacion_cargo',
    'tipo_contrato_id',
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
    /**
     * Si $quien NO puede cambiarle el correo a la cuenta de este trabajador.
     *
     * RR.HH. lleva a todo el personal y puede dar de baja a cualquiera
     * (también al de TIC si es Administrador y se va). Lo que no puede es
     * cambiarle el correo a una cuenta de Administrador o de otro RR.HH.:
     * con el correo cambiado bastaba pedir "olvidé mi contraseña" para
     * entrar como Administrador. Ese cambio solo lo hace el Administrador.
     */
    public function cuentaProtegidaPara(?User $quien): bool
    {
        $rolDeLaCuenta = $this->usuario?->rol?->nombre;

        return $rolDeLaCuenta !== null
            && $rolDeLaCuenta !== 'empleado'
            && $quien?->rol?->nombre !== 'admin';
    }

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
            // Un contrato eliminado (estado_registro = inactivo) no manda:
            // el botón "Eliminar" de Contratos solo apaga estado_registro,
            // nunca toca 'estado'. Sin este filtro, un contrato ya borrado
            // seguía contando para las vacaciones y para el tipo que se
            // imprime en la boleta, como si nunca se hubiera eliminado.
            ->where('estado_registro', 'activo')
            ->latest('fecha_inicio');
    }

    /** La copia suelta de la ficha: solo manda para quien nunca tuvo un Contrato. */
    public function tipoContrato()
    {
        return $this->belongsTo(TipoContrato::class);
    }

    public function tipoContratoVigente(): ?TipoContrato
    {
        // La caída a `tipo_contrato` es solo para quien NUNCA tuvo un
        // Contrato (fichas de antes del módulo). Si ya tiene alguno —aunque
        // sea uno eliminado o finalizado—, manda el módulo de Contratos y
        // punto: caer a la copia de la ficha reabriría el mismo hueco que
        // contratoVigente() ya cierra, porque esa copia nunca se borra sola
        // al eliminar el contrato vigente.
        if ($this->contratos()->exists()) {
            return $this->contratoVigente()->first()?->tipoContrato;
        }

        return $this->tipoContrato;
    }

    /**
     * Quién puede PEDIR vacaciones.
     *
     * Solo lo que el catálogo marque `permite_vacaciones` (hoy, únicamente
     * "Plazo indeterminado"). A los demás —Contratado, Contrato/Parcial,
     * Prácticas— el colegio no les da descanso a cuenta: se les paga con el
     * concepto "Vacaciones Truncas" al terminar el contrato.
     *
     * Está acá y no en el controlador a propósito: la regla la consultan el
     * alta de la solicitud, la aprobación y la pantalla de saldo, y con tres
     * copias basta con que alguien cambie una para que el sistema deje pasar
     * por un lado lo que niega por el otro.
     */
    public function puedeTomarVacaciones(): bool
    {
        return (bool) $this->tipoContratoVigente()?->permite_vacaciones;
    }

    public function identidadFirma()
    {
        return $this->hasOne(IdentidadFirma::class);
    }
}