<?php

namespace Tests;

use App\Models\Empleado;
use App\Models\Rol;
use App\Models\TipoContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** El id del tipo de contrato por su nombre, tal como lo siembra la migración del catálogo. */
    protected function idTipoContrato(string $nombre): string
    {
        return TipoContrato::where('nombre', $nombre)->value('id');
    }

    /** Un empleado mínimo y válido; `$datos` pisa lo que haga falta. */
    protected function crearEmpleado(array $datos = []): Empleado
    {
        return Empleado::create(array_merge([
            'dni'               => (string) random_int(10000000, 99999999),
            'nombre'            => 'Ana',
            'apellido'          => 'Prueba',
            'fecha_ingreso'     => '2020-03-01',
            'estado'            => 'activo',
            'sistema_pensiones' => 'ONP',
            'sueldo_base'       => 3000,
            'tipo_contrato_id'  => $this->idTipoContrato('Plazo indeterminado'),
            'tiene_hijos'       => 0,
        ], $datos));
    }

    /** Una cuenta activa con el rol dado, lista para pasar los middleware. */
    protected function crearUsuario(string $rol = 'admin', array $datos = []): User
    {
        $rolModelo = Rol::firstOrCreate(['nombre' => $rol], ['descripcion' => $rol]);

        $user = new User(array_merge([
            'name'                  => 'Usuario ' . $rol,
            'email'                 => $rol . random_int(1, 99999) . '@prueba.test',
            'password'              => 'ClaveSegura#2026',
            'rol_id'                => $rolModelo->id,
            'estado_registro'       => 'activo',
            'debe_cambiar_password' => false,
        ], $datos));
        // No están en $fillable a propósito: se firman al aceptar los términos,
        // y valen solo si son de la versión vigente.
        $user->terminos_firmados = true;
        $user->terminos_version = \App\Support\TerminosDeUso::VERSION;
        $user->save();

        return $user;
    }
}
