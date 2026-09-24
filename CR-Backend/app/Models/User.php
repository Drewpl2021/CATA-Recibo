<?php
namespace App\Models;
use App\Mail\RestablecerPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'foto', 'password', 'rol_id', 'empleado_id', 'estado_registro', 'debe_cambiar_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use \App\Traits\Auditable;

    /**
     * El rol (quién puede ver qué), si la cuenta está activa, su correo y la
     * contraseña. De la contraseña se anota QUE cambió, nunca su valor.
     */
    protected array $camposAuditables = ['email', 'rol_id', 'estado_registro', 'password'];

    protected array $camposSecretos = ['password'];

    protected string $entidadAuditada = 'usuario';

    public function nombreAuditado(): string
    {
        return "{$this->name} ({$this->email})";
    }

    protected $appends = ['es_institucional', 'terminos_estado'];

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',
            'debe_cambiar_password' => 'boolean',
            'terminos_firmados'     => 'boolean',
            'terminos_firmados_en'  => 'datetime',
            'guia_vista_en'         => 'datetime',
        ];
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    /**
     * true si el correo ya es el institucional.
     *
     * Sirve para distinguir de un vistazo qué cuentas siguen con un correo
     * provisional y todavía necesitan que se les actualice.
     *
     * El dominio sale de la configuración y no del código: iba escrito aquí
     * dentro, y en un repositorio público eso enseña a qué organización
     * pertenece el sistema y cómo son sus direcciones. Sin
     * INSTITUCION_DOMINIO_CORREO en el .env devuelve false para todos, que es
     * lo correcto cuando no hay dominio con el que comparar.
     */
    protected function esInstitucional(): Attribute
    {
        return Attribute::make(
            get: function () {
                $dominio = config('institucion.dominio_correo');

                return $dominio
                    ? str_ends_with(strtolower($this->email ?? ''), '@' . strtolower($dominio))
                    : false;
            },
        );
    }

    /**
     * En qué anda esta persona con los términos de uso.
     *
     * Es la columna "FIRMADO SÍ / NO" de la hoja que se repartía impresa,
     * con un tercer estado que el papel no tenía: quien firmó una versión
     * anterior no está pendiente, pero tampoco al día.
     */
    protected function terminosEstado(): Attribute
    {
        return Attribute::make(get: function () {
            if (! $this->terminos_firmados) {
                return 'pendiente';
            }

            return $this->terminos_version === \App\Support\TerminosDeUso::VERSION
                ? 'firmado'
                : 'desactualizado';
        });
    }

    /**
     * Firmó los términos, y además la versión que rige hoy.
     *
     * Es LA regla: la usan el middleware que traba la cuenta, el cambio de
     * contraseña y la pantalla de los términos. Antes estaba copiada en los
     * tres, y bastaba con cambiar una para que dejaran de coincidir.
     */
    public function terminosAlDia(): bool
    {
        return $this->terminos_estado === 'firmado';
    }

    /**
     * El correo del "olvidé mi contraseña".
     *
     * Se sobrescribe el aviso de Laravel, que llega en inglés y apunta a una
     * ruta web que este proyecto no tiene: aquí el enlace va al frontend en
     * Angular, y el correo está escrito para un docente, no para un
     * desarrollador. Va a la cola, como el de las boletas, para que la
     * respuesta del login no se quede esperando al servidor de correo.
     */
    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email)->queue(new RestablecerPassword($this->name, $this->email, $token));
    }
}
