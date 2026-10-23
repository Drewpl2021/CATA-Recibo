<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * La foto de perfil de una cuenta, vista desde las tablas de personal.
 *
 * Lee la misma tabla `users`, pero entrega solo lo que una tabla necesita
 * para pintar el circulito: de qué cuenta pedir la imagen y su versión. Con
 * el modelo User completo salían además el correo, el rol y sus atributos
 * calculados (que, con las columnas recortadas, ni siquiera eran ciertos).
 *
 * Solo lectura: la foto se pone y se quita en FotoPerfilController.
 */
class FotoDePerfil extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    public function toArray(): array
    {
        return [
            'usuario_id' => $this->getAttribute('id'),
            'version'    => User::versionDeFoto($this->getAttribute('foto')),
        ];
    }
}
