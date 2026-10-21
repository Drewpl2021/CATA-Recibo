<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\PaymentConcept;
use App\Models\PlanillaCorrida;
use App\Models\Rol;
use App\Models\Sede;
use App\Models\TipoContrato;
use App\Models\User;
use Carbon\Carbon;

/**
 * El «Ver más» de la auditoría: un registro contado para quien lo lee.
 *
 * La fila guarda lo mínimo —«cambió», «sueldo_base: [2700, 3456.78]»,
 * «cargo_id: 0198…»—, que sirve para probar pero no para entender. Esto lo
 * traduce: el nombre del campo como lo dice RR.HH., los montos en soles, el
 * cargo por su nombre y no por su id, quién era la persona que lo hizo y
 * desde dónde, sobre qué registro fue (y si todavía existe), qué más le
 * pasó a ese registro y qué más hizo esa persona en esos minutos.
 *
 * No escribe nada: la auditoría solo se lee.
 */
final class DetalleDeAuditoria
{
    /** Cómo se llama cada campo en la pantalla. Lo que no está, se escribe sin guiones bajos. */
    private const ETIQUETAS = [
        'apellido' => 'Apellidos', 'nombre' => 'Nombre', 'dni' => 'DNI', 'estado' => 'Estado', 'sueldo_base' => 'Sueldo base',
        'bonificacion_cargo' => 'Bonificación por cargo', 'sistema_pensiones' => 'Sistema de pensión', 'afp' => 'AFP',
        'tipo_comision_afp' => 'Tipo de comisión AFP', 'cuspp' => 'CUSPP', 'fecha_afiliacion' => 'Fecha de afiliación',
        'entidad_financiera' => 'Banco', 'numero_cuenta' => 'N° de cuenta', 'cci' => 'CCI', 'forma_pago' => 'Forma de pago',
        'tiene_hijos' => 'Tiene hijos', 'aplica_diezmo' => 'Se le descuenta el diezmo', 'cargo_id' => 'Cargo',
        'area_id' => 'Área', 'sede_id' => 'Sede', 'tipo_contrato_id' => 'Tipo de contrato',
        'email' => 'Correo', 'rol_id' => 'Rol', 'estado_registro' => 'Estado de la cuenta', 'password' => 'Contraseña',
        'valor' => 'Valor', 'nombre_anio' => 'Nombre del año', 'uit' => 'UIT', 'rmv' => 'Remuneración mínima (RMV)',
        'asignacion_familiar_pct' => 'Asignación familiar (% de la RMV)', 'onp' => 'ONP (%)', 'essalud' => 'EsSalud (%)',
        'aporte_afp' => 'Aporte AFP (%)', 'prima_seguro_afp' => 'Prima de seguro AFP (%)',
        'comision_habitat' => 'Comisión Habitat (%)', 'comision_integra' => 'Comisión Integra (%)',
        'comision_prima' => 'Comisión Prima (%)', 'comision_profuturo' => 'Comisión Profuturo (%)',
        'etiqueta_boleta' => 'Cómo sale en la boleta', 'tipo' => 'Tipo', 'calculo' => 'Cálculo',
        'aplica_a_todos' => 'Se aplica a todos', 'observaciones' => 'Observaciones', 'archivo' => 'Archivo',
        'modo' => 'Qué se hizo', 'planillas' => 'Planillas', 'neto_total' => 'Neto total', 'nuevos' => 'Trabajadores nuevos',
        'activos' => 'activos', 'cesados' => 'cesados', 'recalculadas' => 'Planillas con la 5ta recalculada',
        'lineas_nuevas' => 'Conceptos agregados', 'lineas_cambiadas' => 'Montos cambiados', 'lineas_quitadas' => 'Conceptos quitados',
        'conceptos_creados' => 'Conceptos creados', 'altas' => 'Altas', 'actualizaciones' => 'Actualizaciones',
        'areas_nuevas' => 'Áreas nuevas', 'cargos_nuevos' => 'Cargos nuevos', 'monto' => 'Monto', 'periodo' => 'Periodo',
        'anio' => 'Año', 'mes' => 'Mes', 'fecha_deposito' => 'Fecha de depósito', 'trabajadores' => 'Trabajadores',
    ];

    private const EN_SOLES = ['sueldo_base', 'bonificacion_cargo', 'neto_total', 'monto', 'uit', 'rmv'];
    private const SI_NO = ['tiene_hijos', 'aplica_diezmo', 'aplica_a_todos'];

    private const VALORES = [
        'estado'          => ['activo' => 'Activo', 'inactivo' => 'Cesado', 'abierta' => 'Abierta', 'cerrada' => 'Cerrada'],
        'estado_registro' => ['activo' => 'Con acceso', 'inactivo' => 'Sin acceso'],
        'forma_pago'      => ['banco' => 'Depósito en banco', 'efectivo' => 'Efectivo', 'honorarios' => 'Recibo por honorarios', 'otro' => 'Otro'],
        'tipo'            => ['bonificacion' => 'Ingreso', 'descuento' => 'Descuento', 'adelanto' => 'Adelanto', 'aportacion' => 'Aportación del empleador'],
        'calculo'         => ['fijo' => 'Monto fijo', 'porcentaje' => 'Porcentaje'],
        'tipo_comision_afp' => ['flujo' => 'Flujo', 'mixta' => 'Mixta'],
        'modo'            => ['crear' => 'Creó el mes', 'reemplazar' => 'Reemplazó una importación anterior', 'comparar' => 'Solo comparó'],
    ];

    /** Los ids que se cuentan por su nombre. */
    private const CATALOGOS = [
        'cargo_id' => Cargo::class, 'area_id' => Area::class, 'sede_id' => Sede::class,
        'tipo_contrato_id' => TipoContrato::class, 'rol_id' => Rol::class,
    ];

    private const ENTIDADES = [
        'empleado' => 'Ficha de un trabajador', 'usuario' => 'Cuenta de acceso', 'concepto' => 'Concepto de pago',
        'planilla' => 'Planilla', 'linea' => 'Línea de una planilla', 'configuración' => 'Ajuste del sistema',
        'montos de ley' => 'Montos de ley', 'boleta' => 'Boleta', 'cts' => 'CTS', 'gratificación' => 'Gratificación',
    ];

    public static function de(Auditoria $registro): array
    {
        $usuario = $registro->user_id ? User::with('rol:id,nombre')->find($registro->user_id) : null;

        return [
            'registro'  => $registro,
            'quien'     => [
                'nombre'      => $registro->usuario_nombre ?? $usuario?->name,
                'correo'      => $usuario?->email,
                'rol'         => $usuario?->rol?->nombre ? self::rolLegible($usuario->rol->nombre) : null,
                'sigue_activo'=> $usuario ? $usuario->estado_registro === 'activo' : null,
                'ip'          => $registro->ip,
                'navegador'   => self::navegadorLegible($registro->navegador ?? null),
            ],
            'sobre'     => self::sobre($registro),
            'campos'    => self::campos($registro),
            'es_alta_o_baja' => in_array($registro->accion, ['creó', 'borró'], true),
            'historial' => $registro->entidad_id
                ? self::resumen(Auditoria::where('entidad', $registro->entidad)->where('entidad_id', $registro->entidad_id)
                    ->where('id', '!=', $registro->id)->orderByDesc('created_at')->orderByDesc('id')->limit(20)->get())
                : [],
            // Lo que hizo la misma persona 15 minutos antes y después: una
            // importación o un cierre de mes suelen ser varias líneas juntas.
            'cercanos'  => $registro->user_id
                ? self::resumen(Auditoria::where('user_id', $registro->user_id)->where('id', '!=', $registro->id)
                    ->whereBetween('created_at', [$registro->created_at->copy()->subMinutes(15), $registro->created_at->copy()->addMinutes(15)])
                    ->orderBy('created_at')->orderBy('id')->limit(12)->get())
                : [],
        ];
    }

    /** Sobre qué registro fue, cómo se llama hoy y dónde se abre. */
    private static function sobre(Auditoria $r): array
    {
        $id = $r->entidad_id;
        [$nombre, $enlace] = match ($r->entidad) {
            'empleado' => ($e = $id ? Empleado::find($id) : null)
                ? [trim("{$e->nombre} {$e->apellido}") . " (DNI {$e->dni})", "/inicio/empleados/ver/{$e->id}"] : [null, null],
            'usuario'  => ($u = $id ? User::find($id) : null) ? ["{$u->name} ({$u->email})", '/inicio/usuarios'] : [null, null],
            'planilla' => ($c = $id ? PlanillaCorrida::find($id) : null)
                ? ["{$c->nombre} · " . Meses::nombre((int) $c->mes) . " {$c->anio}", "/inicio/planillas/corrida/{$c->id}"]
                : [null, $id ? null : '/inicio/planillas'],
            'concepto' => ($p = $id ? PaymentConcept::find($id) : null) ? [$p->nombre, '/inicio/conceptos-pago'] : [null, null],
            'configuración', 'montos de ley' => [null, '/inicio/ajustes'],
            'cts'      => [null, '/inicio/cts'],
            'gratificación' => [null, '/inicio/gratificaciones'],
            default    => [null, null],
        };

        return [
            'entidad' => self::ENTIDADES[$r->entidad] ?? ucfirst($r->entidad),
            'nombre'  => $nombre,
            'enlace'  => $enlace,
            // Tenía un registro y ya no aparece: se borró después.
            'ya_no_existe' => $id !== null && $nombre === null && in_array($r->entidad, ['empleado', 'usuario', 'planilla', 'concepto'], true),
        ];
    }

    /** Los campos del registro, uno por línea y ya en palabras. */
    private static function campos(Auditoria $r): array
    {
        $salida = [];
        foreach ($r->cambios ?? [] as $campo => $valor) {
            $esPar = is_array($valor) && array_is_list($valor) && count($valor) === 2
                && ! is_array($valor[0]) && ! is_array($valor[1]) && $r->accion === 'cambió';
            $salida[] = [
                'campo'   => (string) $campo,
                'etiqueta'=> self::etiqueta((string) $campo),
                'antes'   => $esPar ? self::valor((string) $campo, $valor[0]) : null,
                'despues' => self::valor((string) $campo, $esPar ? $valor[1] : $valor),
                'es_par'  => $esPar,
            ];
        }

        // MySQL guarda las claves de un JSON en su propio orden (por largo):
        // se devuelven en el de la ficha —apellidos, nombre, DNI, sueldo…—
        // y lo que no está en la lista, al final.
        $orden = array_flip(array_keys(self::ETIQUETAS));
        usort($salida, fn ($a, $b) => ($orden[$a['campo']] ?? PHP_INT_MAX) <=> ($orden[$b['campo']] ?? PHP_INT_MAX));

        return $salida;
    }

    private static function etiqueta(string $campo): string
    {
        return self::ETIQUETAS[$campo] ?? ucfirst(str_replace('_', ' ', $campo));
    }

    /**
     * El nombre del campo para meterlo en una frase: «Cambió el cargo y el
     * sueldo base», no «cargo id». Las siglas (DNI, AFP) se quedan igual.
     */
    public static function campoEnFrase(string $campo): string
    {
        $etiqueta = self::etiqueta($campo);

        return mb_strlen($etiqueta) > 1 && mb_strtolower(mb_substr($etiqueta, 1, 1)) === mb_substr($etiqueta, 1, 1)
            ? mb_strtolower(mb_substr($etiqueta, 0, 1)) . mb_substr($etiqueta, 1)
            : $etiqueta;
    }

    /** Un valor como lo lee una persona. */
    private static function valor(string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return 'vacío';
        }
        if ($valor === '(oculto)') {
            return 'oculto (no se guarda)';
        }
        if (is_array($valor)) {
            if (! $valor) {
                return 'ninguno';
            }
            return array_is_list($valor)
                ? implode(', ', array_map(fn ($v) => self::valor($campo, $v), $valor))
                : implode(', ', array_map(fn ($k, $v) => self::etiqueta((string) $k) . ': ' . self::valor((string) $k, $v), array_keys($valor), $valor));
        }
        if (in_array($campo, self::SI_NO, true)) {
            return filter_var($valor, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
        }
        if (in_array($campo, self::EN_SOLES, true) && is_numeric($valor)) {
            return 'S/ ' . number_format((float) $valor, 2);
        }
        if (isset(self::CATALOGOS[$campo])) {
            $modelo = self::CATALOGOS[$campo];
            $nombre = $modelo::whereKey($valor)->value('nombre');

            return $nombre ? ($campo === 'rol_id' ? self::rolLegible($nombre) : $nombre) : 'uno que ya no existe';
        }
        if (isset(self::VALORES[$campo][$valor])) {
            return self::VALORES[$campo][$valor];
        }
        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}( |T|$)/', $valor)) {
            try {
                return Carbon::parse($valor)->format('d/m/Y');
            } catch (\Throwable) {
                return $valor;
            }
        }

        return (string) $valor;
    }

    private static function rolLegible(string $rol): string
    {
        return ['admin' => 'Administración', 'rrhh' => 'Recursos Humanos', 'empleado' => 'Trabajador'][$rol] ?? ucfirst($rol);
    }

    /** «Chrome en Windows» a partir de la cadena que manda el navegador. */
    public static function navegadorLegible(?string $agente): ?string
    {
        if (! $agente) {
            return null;
        }
        $navegador = match (true) {
            str_contains($agente, 'Edg/')                                   => 'Edge',
            str_contains($agente, 'OPR/')                                   => 'Opera',
            str_contains($agente, 'Firefox/')                               => 'Firefox',
            str_contains($agente, 'Chrome/')                                => 'Chrome',
            str_contains($agente, 'Safari/')                                => 'Safari',
            str_contains($agente, 'PostmanRuntime')                         => 'Postman',
            default                                                         => 'Otro navegador',
        };
        $equipo = match (true) {
            str_contains($agente, 'Android')                                => 'un celular Android',
            str_contains($agente, 'iPhone')                                 => 'un iPhone',
            str_contains($agente, 'iPad')                                   => 'un iPad',
            str_contains($agente, 'Windows')                                => 'Windows',
            str_contains($agente, 'Mac OS')                                 => 'Mac',
            str_contains($agente, 'Linux')                                  => 'Linux',
            default                                                         => null,
        };

        return $equipo ? "{$navegador} en {$equipo}" : $navegador;
    }

    /** Lo justo para una línea de tiempo. */
    private static function resumen($registros): array
    {
        return $registros->map(fn (Auditoria $a) => [
            'id' => $a->id, 'created_at' => $a->created_at, 'usuario_nombre' => $a->usuario_nombre,
            'accion' => $a->accion, 'entidad' => $a->entidad, 'descripcion' => $a->descripcion,
        ])->values()->all();
    }
}
