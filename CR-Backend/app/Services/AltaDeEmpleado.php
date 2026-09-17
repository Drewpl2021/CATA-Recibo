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
            // 12 caracteres entre letras y números: es como lo entrega la AFP
            // (052281JHPMM4). Antes se exigían 11 cifras, y con eso el sistema
            // rechazaba los CUSPP de su propio personal.
            'cuspp'              => 'nullable|regex:/^[A-Za-z0-9]{12}$/|required_if:sistema_pensiones,AFP',
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

    /**
     * Las reglas para registrar a alguien que YA SE FUE, solo para guardar sus
     * boletas y contratos de antes. Basta con saber quién es y cuándo entró y
     * salió: lo demás, si viene, se valida igual.
     */
    public static function reglasDeCesado(): array
    {
        $reglas = self::reglas();

        foreach (['cargo_id', 'area_id', 'sede_id', 'telefono', 'direccion', 'sueldo_base', 'tipo_contrato', 'email', 'fecha_nacimiento'] as $campo) {
            $reglas[$campo] = preg_replace('/^required\|/', 'nullable|', $reglas[$campo]);
        }
        $reglas['fecha_fin_contrato'] = 'nullable|date';
        $reglas['fecha_cese']         = 'required|date|after_or_equal:fecha_ingreso|before_or_equal:today';

        return $reglas;
    }

    public static function mensajes(): array
    {
        return [
            // El genérico ("el formato no es válido") no dice qué arreglar.
            'cci.regex'   => 'El CCI son 20 dígitos, sin espacios ni guiones.',
            'cuspp.regex' => 'El CUSPP son 12 caracteres, entre letras y números.',
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
        // Con fecha de cese es alguien que ya se fue: se registra para guardar
        // sus documentos, pero sin acceso y con el contrato ya cerrado.
        $cesado = ! empty($datos['fecha_cese']);

        return DB::transaction(function () use ($datos, $cesado) {
            $empleado = Empleado::create(array_merge(
                Arr::except($datos, ['email', 'rol_id', 'fecha_fin_contrato']),
                $cesado ? ['estado' => 'inactivo'] : []
            ));

            // De un cesado de hace años no suele haber correo: sin él no hay
            // cuenta, y no hace falta, porque no va a entrar.
            if (! empty($datos['email'])) {
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
                    'estado_registro'       => $cesado ? 'inactivo' : 'activo',
                ]);
            }

            // El primer contrato sale de lo que ya se pide en el alta: el tipo y la
            // fecha de ingreso. Las renovaciones se hacen luego desde Contratos, que
            // al crear una nueva cierra la anterior. Un cesado sin tipo de contrato
            // se queda sin él: inventarle uno sería peor que no tenerlo.
            if (! empty($datos['tipo_contrato'])) {
                Contrato::create([
                    'empleado_id'   => $empleado->id,
                    'tipo_contrato' => $datos['tipo_contrato'],
                    'fecha_inicio'  => $datos['fecha_ingreso'],
                    'fecha_fin'     => match (true) {
                        $cesado                                     => $datos['fecha_cese'],
                        $datos['tipo_contrato'] === 'indeterminado' => null,
                        default                                     => $datos['fecha_fin_contrato'] ?? null,
                    },
                    'estado'        => $cesado ? 'finalizado' : 'vigente',
                    'observaciones' => $cesado
                        ? 'Contrato registrado al importar a un trabajador que ya cesó.'
                        : 'Contrato inicial, creado al dar de alta al trabajador.',
                ]);
            }

            return $empleado;
        });
    }
}
