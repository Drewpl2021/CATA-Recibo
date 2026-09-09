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

#[Fillable(['name', 'email', 'password', 'rol_id', 'empleado_id', 'estado_registro', 'debe_cambiar_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends = ['es_institucional'];

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',
            'debe_cambiar_password' => 'boolean',
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
     * true si el correo ya es el institucional del colegio.
     * Sirve para que RRHH identifique de un vistazo qué usuarios
     * siguen con un correo temporal (mientras no tienen su @cata.edu.pe)
     * y todavía necesitan que se les actualice.
     */
    protected function esInstitucional(): Attribute
    {
        return Attribute::make(
            get: fn () => str_ends_with($this->email ?? '', '@cata.edu.pe'),
        );
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
