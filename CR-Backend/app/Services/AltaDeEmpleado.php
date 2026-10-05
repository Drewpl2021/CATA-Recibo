<?php

namespace App\Services;

use App\Models\Cargo;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\User;
use App\Rules\FechaFinSegunTipoContrato;
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
            // 8 cifras es el DNI de siempre; 9 es un Carné de Extranjería
            // (el padrón de RENIEC solo tiene los de 8, pero un extranjero
            // contratado es una persona real que igual hay que poder registrar).
            'dni'                => 'required|string|regex:/^[0-9]{8,9}$/|unique:empleados',
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
            'tipo_comision_afp'  => 'nullable|in:flujo,mixta',
            // 12 caracteres entre letras y números: es como lo entrega la AFP
            // (052281JHPMM4). Antes se exigían 11 cifras, y con eso el sistema
            // rechazaba los CUSPP de su propio personal.
            'cuspp'              => 'nullable|regex:/^[A-Za-zÑñ0-9]{12}$/u|required_if:sistema_pensiones,AFP',
            'entidad_financiera' => 'nullable|string|max:100',
            'numero_cuenta'      => 'nullable|string|max:50',
            // Opcional, pero si viene tiene que ser un CCI de verdad: son 20
            // dígitos exactos. Uno mal copiado no rebota, se abona a otra
            // persona, y eso no hay forma de verlo hasta el reclamo.
            'cci'                => 'nullable|regex:/^[0-9]{20}$/',
            'tiene_hijos'        => 'nullable|boolean',
            'aplica_diezmo'      => 'nullable|boolean',
            'sueldo_base'        => 'required|numeric|min:0',
            'bonificacion_cargo' => 'nullable|numeric|min:0',
            'tipo_contrato_id'   => 'required|uuid|exists:tipos_contrato,id',
            // Un tipo de contrato que exija fecha de fin (todos salvo el que
            // el catálogo marque `requiere_fecha_fin=false` — hoy, Plazo
            // indeterminado) sin ella no es un contrato: hay que saber
            // cuándo acaba. Esta misma fecha es, si la persona llegara a
            // cesar, su fecha de cese real — no son dos datos distintos.
            'fecha_cese'         => ['nullable', 'date', 'after:fecha_ingreso', new FechaFinSegunTipoContrato()],
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

        foreach (['cargo_id', 'area_id', 'sede_id', 'telefono', 'direccion', 'sueldo_base', 'tipo_contrato_id', 'email', 'fecha_nacimiento'] as $campo) {
            $reglas[$campo] = preg_replace('/^required\|/', 'nullable|', $reglas[$campo]);
        }
        $reglas['fecha_cese'] = 'required|date|after_or_equal:fecha_ingreso|before_or_equal:today';

        return $reglas;
    }

    public static function mensajes(): array
    {
        return [
            // El genérico ("el formato no es válido") no dice qué arreglar.
            'cci.regex'   => 'El CCI son 20 dígitos, sin espacios ni guiones.',
            'cuspp.regex' => 'El CUSPP son 12 caracteres, entre letras (incluida la Ñ) y números.',
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
            $datos['afp']               = null;
            $datos['cuspp']             = null;
            $datos['tipo_comision_afp'] = null;
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
        // Quien manda es la columna Estado, no la fecha de cese: esa misma
        // fecha también puede ser el fin programado de un contrato de
        // alguien que sigue activo (p.ej. un Contratado con plazo), así
        // que su sola presencia ya no basta para decir que alguien se fue.
        $cesado = ($datos['estado'] ?? 'activo') === 'inactivo';

        return DB::transaction(function () use ($datos, $cesado) {
            $empleado = Empleado::create(array_merge(
                Arr::except($datos, ['email', 'rol_id']),
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
            if (! empty($datos['tipo_contrato_id'])) {
                $tipoContrato = \App\Models\TipoContrato::find($datos['tipo_contrato_id']);
                Contrato::create([
                    'empleado_id'      => $empleado->id,
                    'tipo_contrato_id' => $datos['tipo_contrato_id'],
                    'fecha_inicio'     => $datos['fecha_ingreso'],
                    'fecha_fin'        => ! ($tipoContrato?->requiere_fecha_fin ?? true)
                        ? null
                        : ($datos['fecha_cese'] ?? null),
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
