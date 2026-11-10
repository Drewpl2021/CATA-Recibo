<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use Illuminate\Http\Request;

/**
 * Los datos de "Mi perfil": los de quien está dentro, sea quien sea.
 *
 * Existe porque la pantalla los pedía a GET /employees/{id}, y esa ruta es
 * solo de RR.HH. y Administración: al trabajador le contestaba 403 y su
 * perfil salía con guiones en todos los campos —justo el caso de alguien
 * recién dado de alta, que entra por primera vez a mirar sus datos—.
 *
 * Acá no hace falta permiso de nadie: cada quien pide LO SUYO, y el id sale
 * de su sesión, no de la dirección, así que no hay forma de pedir la ficha
 * de otro.
 *
 * Devuelve dos cosas, porque no todas las cuentas son de un trabajador:
 *
 *   · `empleado`: su ficha, cuando la cuenta está vinculada a una. Van los
 *     datos que la pantalla enseña —quién es, dónde trabaja y desde
 *     cuándo—, no los de plata: el sueldo, la cuenta y el CCI están en su
 *     boleta, y una tarjeta de perfil no es sitio para enseñarlos.
 *   · `cuenta`: lo que SIEMPRE hay. Las cuentas de Administración y de
 *     RR.HH. nacen sin ficha (son para operar el sistema, no personas en
 *     planilla), y antes su perfil se quedaba en blanco entero. Con esto
 *     enseñan al menos su rol, su correo y desde cuándo existen.
 */
class MiPerfilController extends Controller
{
    /** GET /my-profile */
    public function ver(Request $request)
    {
        $usuario = $request->user();

        $empleado = $usuario->empleado_id
            ? Empleado::select([
                'id', 'dni', 'nombre', 'apellido', 'telefono', 'direccion',
                'fecha_nacimiento', 'fecha_ingreso', 'tipo_contrato_id', 'estado',
                'nivel_estudios', 'especialidad', 'institucion_estudios',
                'contacto_emergencia_nombre', 'contacto_emergencia_telefono',
                'area_id', 'cargo_id', 'sede_id',
            ])
                ->with(['area:id,nombre', 'cargo:id,nombre', 'sede:id,nombre', 'tipoContrato:id,nombre'])
                ->find($usuario->empleado_id)
            : null;

        return response()->json([
            'success' => true,
            'data'    => [
                'empleado' => $empleado,
                'cuenta'   => [
                    'nombre'          => $usuario->name,
                    'correo'          => $usuario->email,
                    'rol'             => $usuario->rol?->nombre,
                    'estado'          => $usuario->estado_registro,
                    'creada_en'       => $usuario->created_at?->toDateString(),
                    // Si firmó los términos y cuándo: es lo que antes se
                    // guardaba en la carpeta de cada uno.
                    'terminos_estado' => $usuario->terminos_estado,
                    'terminos_en'     => $usuario->terminos_firmados_en?->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * PATCH /my-profile — lo que cada quien puede corregir de su ficha: cómo
     * ubicarlo (celular, dirección, contacto de emergencia) y sus estudios.
     *
     * Nada más: el DNI, el nombre, el área, el cargo, el contrato, el sueldo y
     * las cuentas los lleva RR.HH.; y el correo es el institucional
     * (@cata.edu.pe), que lo asigna el colegio. Lo que llegue de más se
     * ignora: solo se toman estos campos. El id sale de la sesión, así que
     * nadie corrige la ficha de otro. Los cambios quedan en Auditoría (la
     * ficha es Auditable).
     */
    public function actualizar(Request $request)
    {
        $empleado = $request->user()->empleado_id ? Empleado::find($request->user()->empleado_id) : null;
        if (! $empleado) {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta no tiene ficha de trabajador: no hay datos que editar.',
            ], 422);
        }

        // Las mismas reglas con que RR.HH. llena la ficha (EmpleadoController).
        $datos = $request->validate([
            'telefono'                     => 'nullable|regex:/^[0-9]+$/|max:15',
            'direccion'                    => 'nullable|string|max:255',
            'contacto_emergencia_nombre'   => 'nullable|string|max:150',
            'contacto_emergencia_telefono' => 'nullable|regex:/^[0-9]+$/|max:15',
            'nivel_estudios'               => 'nullable|in:primaria,secundaria,tecnico,universitario,maestria,doctorado',
            'especialidad'                 => 'nullable|string|max:150',
            'institucion_estudios'         => 'nullable|string|max:150',
        ], [
            'telefono.regex'                     => 'El celular va solo con números.',
            'contacto_emergencia_telefono.regex' => 'El celular del contacto va solo con números.',
        ]);

        $empleado->update($datos);

        return response()->json(['success' => true, 'message' => 'Tus datos se guardaron.']);
    }
}
