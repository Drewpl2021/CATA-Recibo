<?php

namespace App\Services;

use App\Models\Cargo;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Dar de alta a un trabajador, en UN solo sitio.
 *
 * Hay dos caminos para hacerlo —el formulario de Nuevo Empleado y la
 * importación desde Excel— y los dos tienen que dejar exactamente lo mismo:
 * la ficha, su cuenta con el DNI como contraseña provisional y su contrato
 * inicial. Con la lógica copiada en dos controladores, el día que se agregara
 * un paso al alta uno de los dos caminos crearía empleados incompletos.
 */
final class AltaDeEmpleado
{
    /** Las reglas del alta. Las usa tal cual EmpleadoController::store. */
    public static function reglas(): array
    {
        return [
            'dni'                => 'required|string|regex:/^[0-9]{8}$/|unique:empleados',
            'nombre'             => 'required|string|max:100',
            'apellido'           => 'required|string|max:100',
            'cargo_id'           => 'required|uuid|exists:cargos,id',
            'area_id'            => 'required|uuid|exists:areas,id',
            'telefono'           => 'required|regex:/^[0-9]+$/|max:15',
            'direccion'          => 'required|string|max:255',
            'fecha_ingreso'      => 'required|date|before_or_equal:today',
            'estado'             => 'nullable|string|max:20',
            'sistema_pensiones'  => 'nullable|in:AFP,ONP',
            'afp'                => 'nullable|in:Habitat,Integra,Prima,Profuturo|required_if:sistema_pensiones,AFP',
            'cuspp'              => 'nullable|regex:/^[0-9]{11}$/|required_if:sistema_pensiones,AFP',
            'entidad_financiera' => 'nullable|string|max:100',
            'numero_cuenta'      => 'nullable|string|max:50',
            // Opcional, pero si viene tiene que ser un CCI de verdad: son 20
            // dígitos exactos. Uno mal copiado no rebota, se abona a otra
            // persona, y eso no hay forma de verlo hasta el reclamo.
            'cci'                => 'nullable|regex:/^[0-9]{20}$/',
            'tiene_hijos'        => 'nullable|boolean',
            'sueldo_base'        => 'required|numeric|min:0',
            'tipo_contrato'      => 'required|in:indeterminado,plazo_fijo,suplencia,practicas',
            // Un plazo fijo, una suplencia o unas prácticas SIN fecha de término no
            // son un contrato: hay que saber cuándo acaba. El indeterminado es el
            // único que no lleva fin, y ahí el campo sobra.
            'fecha_fin_contrato' => 'required_unless:tipo_contrato,indeterminado|nullable|date|after:fecha_ingreso',
            'forma_pago'         => 'nullable|in:banco,efectivo,otro,honorarios',
            'sede_id'            => 'required|uuid|exists:sedes,id',
            'email'              => 'required|email|unique:users,email',
            'rol_id'             => 'required|uuid|exists:roles,id',
            'nivel_estudios'       => 'nullable|in:primaria,secundaria,tecnico,universitario,maestria,doctorado',
            'especialidad'         => 'nullable|string|max:150',
            'institucion_estudios' => 'nullable|string|max:150',
            'contacto_emergencia_nombre'    => 'nullable|string|max:150',
            'contacto_emergencia_telefono'  => 'nullable|regex:/^[0-9]+$/|max:15',
            'fecha_nacimiento'              => 'required|date|before:today',
        ];
    }

    public static function mensajes(): array
    {
        return [
            // El genérico ("el formato no es válido") no dice qué arreglar.
            'cci.regex' => 'El CCI son 20 dígitos, sin espacios ni guiones.',
        ];
    }

    /**
     * La AFP y el CUSPP solo tienen sentido con AFP. Solo actúa si el sistema
     * de pensiones viene en los datos: una edición parcial que ni lo menciona
     * no debe borrar nada.
     */
    public static function limpiarAfp(array $datos): array
    {
        if (array_key_exists('sistema_pensiones', $datos) && $datos['sistema_pensiones'] !== 'AFP') {
            $datos['afp']   = null;
            $datos['cuspp'] = null;
        }

        return $datos;
    }

    /**
     * Qué está mal si el cargo no vale en esa área; null si está bien.
     *
     * Los cargos SIN áreas marcadas (Practicante, Voluntario Misionero) valen
     * en cualquiera: la relación acota, no obliga.
     */
    public static function problemaCargoArea(?string $cargoId, ?string $areaId): ?string
    {
        if (! $cargoId || ! $areaId) {
            return null;
        }

        $cargo = Cargo::with('areas:id,nombre')->find($cargoId);
        if (! $cargo || $cargo->valeEnElArea($areaId)) {
            return null;
        }

        $donde = $cargo->areas->pluck('nombre')->implode(', ');

        return "\"{$cargo->nombre}\" no pertenece a esa área: es de {$donde}. "
            . 'Elige otro cargo, o agrégale el área desde Configuración → Cargos.';
    }

    /**
     * Crea la ficha, la cuenta y el contrato inicial. Las tres cosas nacen
     * juntas o no nace ninguna: un empleado sin usuario no puede entrar, y uno
     * sin contrato queda con el historial en blanco aunque su ficha diga
     * "plazo fijo".
     *
     * Espera los datos ya validados con reglas().
     */
    public static function crear(array $datos): Empleado
    {
        return DB::transaction(function () use ($datos) {
            $empleado = Empleado::create(Arr::except($datos, ['email', 'rol_id', 'fecha_fin_contrato']));

            User::create([
                'name'        => $empleado->nombre . ' ' . $empleado->apellido,
                'email'       => $datos['email'],
                'password'    => Hash::make($datos['dni']),
                'rol_id'      => $datos['rol_id'],
                'empleado_id' => $empleado->id,
                // Entra con su DNI, y el sistema no le deja hacer nada más
                // hasta que ponga una contraseña suya: el DNI está a la vista
                // de todos en la ficha y en la boleta.
                'debe_cambiar_password' => true,
            ]);

            // El primer contrato sale de lo que ya se pide en el alta: el tipo y la
            // fecha de ingreso. Las renovaciones se hacen luego desde Contratos, que
            // al crear una nueva cierra la anterior.
            Contrato::create([
                'empleado_id'   => $empleado->id,
                'tipo_contrato' => $datos['tipo_contrato'],
                'fecha_inicio'  => $datos['fecha_ingreso'],
                'fecha_fin'     => $datos['tipo_contrato'] === 'indeterminado'
                    ? null
                    : ($datos['fecha_fin_contrato'] ?? null),
                'estado'        => 'vigente',
                'observaciones' => 'Contrato inicial, creado al dar de alta al trabajador.',
            ]);

            return $empleado;
        });
    }
}
