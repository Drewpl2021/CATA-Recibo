<?php
namespace App\Models;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'rol_id', 'empleado_id', 'estado_registro'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends = ['es_institucional'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
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
}