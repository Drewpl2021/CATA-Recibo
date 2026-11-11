<?php

namespace App\Http\Controllers;

use App\Mail\CambioDeCuenta;
use App\Models\Auditoria;
use App\Models\Empleado;
use App\Models\Notificacion;
use App\Models\Solicitud;
use App\Models\User;
use App\Support\Permisos;
use App\Support\Solicitudes\Cruces;
use App\Support\Solicitudes\EfectosDeSolicitud;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * «Mis solicitudes» del trabajador y la bandeja de RR.HH.
 *
 *   GET    /my-requests                  las suyas
 *   POST   /my-requests                  pedir (con su sustento)
 *   DELETE /my-requests/{s}              cancelar una pendiente
 *   GET    /my-requests/{s}/support      bajar su sustento
 *
 *   GET    /requests                     RR.HH.: todas, con filtros
 *   GET    /requests/{s}/preview         RR.HH.: qué pasaría al aprobar
 *   GET    /requests/{s}/support         RR.HH.: el sustento (queda anotado que lo vio)
 *   POST   /requests/{s}/approve         RR.HH.: aprobar
 *   POST   /requests/{s}/reject          RR.HH.: rechazar, con motivo
 *   POST   /requests/{s}/annul           RR.HH.: revertir una aprobada, con motivo
 *
 * Lo que cada aprobación cambia en la planilla y la CTS está en
 * App\Support\Solicitudes\EfectosDeSolicitud.
 */
class SolicitudController extends Controller
{
    /** Más pendientes que esto ya no es pedir: es llenar la bandeja. */
    private const MAX_PENDIENTES = 10;

    private const MENSAJES = [
        'tipo.in'               => 'Elige qué quieres pedir.',
        'motivo.required'       => 'Cuenta brevemente el motivo.',
        'motivo.min'            => 'Cuenta un poco más el motivo.',
        'motivo.max'            => 'El motivo es muy largo: hasta 500 letras.',
        'fecha_inicio.required' => 'Elige la fecha.',
        'fecha_fin.required'    => 'Elige hasta qué fecha.',
        'fecha_fin.after_or_equal' => 'La fecha final no puede ser antes de la inicial.',
        'hora_inicio.required'  => 'Elige desde qué hora.',
        'hora_fin.required'     => 'Elige hasta qué hora.',
        'hora_fin.after'        => 'La hora final tiene que ser después de la inicial.',
        'con_goce.required'     => 'Indica si la pides con goce o sin goce de haber.',
        'sustento.required'     => 'Adjunta el sustento.',
        'sustento.mimes'        => 'El sustento tiene que ser PDF o una imagen (JPG o PNG).',
        'sustento.max'          => 'El sustento pasa de 5 MB.',
        'destino.in'            => 'Elige qué cuenta quieres cambiar.',
        'entidad.required'      => 'Escribe el banco o la caja.',
        'entidad.regex'         => 'Escribe solo el nombre del banco o la caja.',
        'numero.required'       => 'Escribe el número de cuenta.',
        'numero.regex'          => 'El número de cuenta lleva solo números, con guiones si el banco la escribe así.',
        'numero.min'            => 'El número de cuenta tiene al menos 6 cifras.',
        'numero.max'            => 'El número de cuenta tiene hasta 30 cifras.',
        'cci.required'          => 'Escribe el CCI: sin él no se puede transferir desde otro banco.',
        'cci.regex'             => 'El CCI tiene 20 números, sin espacios ni guiones.',
        'password.required'     => 'Escribe tu contraseña para confirmar que eres tú.',
        'respuesta.required'    => 'Escribe el motivo: le llega al trabajador.',
        'respuesta.min'         => 'Cuenta un poco más el motivo.',
    ];

    // ───────────────────────── el trabajador ─────────────────────────

    public function mias(Request $request)
    {
        $empleado = $this->empleadoDe($request);
        if (! $empleado) {
            return $this->sinFicha();
        }

        $filas = Solicitud::where('empleado_id', $empleado->id)->latest()->limit(300)->get()
            ->map(fn (Solicitud $s) => $this->fila($s, conEmpleado: false));

        return response()->json(['success' => true, 'data' => $filas]);
    }

    public function pedir(Request $request)
    {
        $empleado = $this->empleadoDe($request);
        if (! $empleado) {
            return $this->sinFicha();
        }
        if ($empleado->estado !== 'activo') {
            return response()->json(['success' => false, 'message' => 'Ya no figuras como trabajador activo: habla con Recursos Humanos.'], 403);
        }

        $tipo = (string) $request->input('tipo');
        $datos = $request->validate($this->reglas($tipo, $request), self::MENSAJES);

        // Cambiar la cuenta es pedir que te paguen en otro lado: se confirma
        // con la contraseña, aunque ya tenga la sesión abierta.
        if ($tipo === Solicitud::CAMBIO_CUENTA && ! Hash::check((string) $datos['password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => ['La contraseña no es correcta.']]);
        }

        if (Solicitud::where('empleado_id', $empleado->id)->where('estado', 'pendiente')->count() >= self::MAX_PENDIENTES) {
            throw ValidationException::withMessages(['tipo' => ['Ya tienes ' . self::MAX_PENDIENTES . ' solicitudes esperando respuesta. Espera a que Recursos Humanos las revise.']]);
        }

        [$inicio, $fin] = $this->fechas($tipo, $datos);
        $this->revisarFechas($empleado, $tipo, $inicio, $fin, $datos);

        if ($tipo === Solicitud::CAMBIO_CUENTA
            && Solicitud::where('empleado_id', $empleado->id)->where('tipo', $tipo)->where('estado', 'pendiente')
                ->get()->contains(fn ($s) => ($s->datos['destino'] ?? '') === $datos['destino'])) {
            throw ValidationException::withMessages(['destino' => ['Ya pediste cambiar esa cuenta y está esperando respuesta. Cancélala primero si te equivocaste.']]);
        }

        $solicitud = DB::transaction(function () use ($empleado, $tipo, $datos, $inicio, $fin, $request) {
            $s = new Solicitud();
            $s->fill([
                'empleado_id'  => $empleado->id,
                'tipo'         => $tipo,
                'fecha_inicio' => $inicio?->toDateString(),
                'fecha_fin'    => $fin?->toDateString(),
                'hora_inicio'  => $tipo === Solicitud::PERMISO ? $datos['hora_inicio'] . ':00' : null,
                'hora_fin'     => $tipo === Solicitud::PERMISO ? $datos['hora_fin'] . ':00' : null,
                'con_goce'     => in_array($tipo, [Solicitud::PERMISO, Solicitud::LICENCIA], true) ? (bool) $datos['con_goce'] : null,
                'motivo'       => trim(strip_tags($datos['motivo'])),
                'datos'        => $tipo === Solicitud::CAMBIO_CUENTA ? array_filter([
                    'destino' => $datos['destino'],
                    'entidad' => trim(strip_tags($datos['entidad'])),
                    'numero'  => $datos['numero'],
                    'cci'     => $datos['destino'] === 'sueldo' ? $datos['cci'] : null,
                ], fn ($v) => $v !== null) : null,
                'estado'       => 'pendiente',
            ]);
            $s->save();

            if ($archivo = $request->file('sustento')) {
                // El nombre lo pone el servidor; el del usuario solo se muestra.
                $ruta = $archivo->storeAs("solicitudes/{$empleado->id}", $s->id . '.' . strtolower($archivo->extension()), 'local');
                $s->forceFill([
                    'sustento'        => $ruta,
                    'sustento_nombre' => Str::limit(preg_replace('/[^\pL\pN ._()-]/u', '', $archivo->getClientOriginalName()) ?: 'sustento', 140, ''),
                ])->save();
            }

            return $s;
        });

        Auditoria::registrar('pidió', 'solicitud', $solicitud->id, $solicitud->nombre() . ' de ' . $this->quien($empleado) . $this->cuando($solicitud) . '.');
        $this->avisarARrhh($empleado, $solicitud);
        if ($tipo === Solicitud::CAMBIO_CUENTA) {
            $this->correoDeCuenta($request->user(), $empleado, $solicitud, 'pedida');
        }

        return response()->json(['success' => true, 'data' => $this->fila($solicitud, conEmpleado: false)], 201);
    }

    public function cancelar(Request $request, Solicitud $solicitud)
    {
        $empleado = $this->empleadoDe($request);
        if (! $empleado || (string) $solicitud->empleado_id !== (string) $empleado->id) {
            return response()->json(['success' => false, 'message' => 'No se encontró la solicitud.'], 404);
        }

        $hecho = DB::transaction(function () use ($solicitud) {
            $s = Solicitud::whereKey($solicitud->id)->lockForUpdate()->first();
            if ($s->estado !== 'pendiente') {
                return false;
            }
            $s->forceFill(['estado' => 'cancelada', 'resuelto_at' => now()])->save();

            return true;
        });
        if (! $hecho) {
            return response()->json(['success' => false, 'message' => 'Esa solicitud ya fue respondida: si hay que cambiarla, habla con Recursos Humanos.'], 422);
        }

        Auditoria::registrar('canceló', 'solicitud', $solicitud->id, 'Canceló su ' . mb_strtolower($solicitud->nombre()) . '.');

        return response()->json(['success' => true, 'data' => ['message' => 'Solicitud cancelada.']]);
    }

    public function miSustento(Request $request, Solicitud $solicitud)
    {
        $empleado = $this->empleadoDe($request);
        if (! $empleado || (string) $solicitud->empleado_id !== (string) $empleado->id) {
            return response()->json(['success' => false, 'message' => 'No se encontró la solicitud.'], 404);
        }

        return $this->archivo($solicitud);
    }

    // ───────────────────────── RR.HH. ─────────────────────────

    public function index(Request $request)
    {
        $f = $request->validate([
            'estado' => ['nullable', Rule::in(['pendiente', 'aprobada', 'rechazada', 'cancelada', 'anulada'])],
            'tipo'   => ['nullable', Rule::in(array_keys(Solicitud::TIPOS))],
        ]);

        $consulta = Solicitud::with('empleado:id,dni,nombre,apellido,area_id,cargo_id', 'empleado.area:id,nombre', 'empleado.cargo:id,nombre', 'resolutor:id,name')
            ->when($f['estado'] ?? null, fn ($q, $e) => $q->where('estado', $e))
            ->when($f['tipo'] ?? null, fn ($q, $t) => $q->where('tipo', $t));

        $filas = $consulta->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")->latest()->limit(1000)->get()
            ->map(fn (Solicitud $s) => $this->fila($s, conEmpleado: true));

        return response()->json(['success' => true, 'data' => [
            'filas'      => $filas,
            'pendientes' => Solicitud::where('estado', 'pendiente')->count(),
        ]]);
    }

    /** Qué pasaría al aprobar: avisos de planilla y CTS, y las faltas que se podrían restar. */
    public function previa(Request $request, Solicitud $solicitud)
    {
        $request->validate(['con_goce' => 'nullable|boolean']);
        $solicitud->load('empleado');

        return response()->json(['success' => true, 'data' => [
            ...(new EfectosDeSolicitud())->previa($solicitud, $request->has('con_goce') ? $request->boolean('con_goce') : null),
            // Completa para RR.HH.: es la que compara con el sustento.
            'datos'          => $solicitud->datos,
            'sustento_visto' => (bool) $solicitud->sustento_visto_por,
            'propia'         => $this->esPropia($request->user(), $solicitud),
        ]]);
    }

    public function sustento(Request $request, Solicitud $solicitud)
    {
        if ($solicitud->sustento && ! $solicitud->sustento_visto_por) {
            $solicitud->forceFill(['sustento_visto_por' => $request->user()->id])->save();
        }

        return $this->archivo($solicitud);
    }

    public function aprobar(Request $request, Solicitud $solicitud)
    {
        $datos = $request->validate([
            'con_goce'      => 'nullable|boolean',
            'restar_faltas' => 'nullable|boolean',
            'respuesta'     => 'nullable|string|max:500',
        ], self::MENSAJES);

        if ($this->esPropia($request->user(), $solicitud)) {
            return response()->json(['success' => false, 'message' => 'No puedes resolver tu propia solicitud: la resuelve otra persona.'], 403);
        }

        $resultado = DB::transaction(function () use ($solicitud, $datos, $request) {
            $s = Solicitud::with('empleado')->whereKey($solicitud->id)->lockForUpdate()->first();
            if ($s->estado !== 'pendiente') {
                throw ValidationException::withMessages(['estado' => ['Esta solicitud ya no está pendiente: ' . $s->estado . '.']]);
            }
            if ($s->empleado->estado !== 'activo' && $s->tipo !== Solicitud::JUSTIFICAR_FALTA) {
                throw ValidationException::withMessages(['estado' => ['El trabajador ya cesó: esta solicitud no se puede aprobar. Recházala.']]);
            }
            if ($s->tipo === Solicitud::CAMBIO_CUENTA && ! $s->sustento_visto_por) {
                throw ValidationException::withMessages(['estado' => ['Abre el sustento antes de aprobar: un cambio de cuenta no se aprueba sin revisarlo.']]);
            }
            // Pudo aprobarse otra cosa en esas fechas mientras esperaba.
            if (in_array($s->tipo, Solicitud::CON_DIAS, true)
                && ($motivo = Cruces::con($s->empleado_id, $s->fecha_inicio, $s->fecha_fin, exceptoSolicitud: $s->id))) {
                throw ValidationException::withMessages(['estado' => [$motivo]]);
            }

            $cambios = [
                'estado'      => 'aprobada',
                'respuesta'   => isset($datos['respuesta']) ? (trim(strip_tags($datos['respuesta'])) ?: null) : null,
                'resuelto_por' => $request->user()->id,
                'resuelto_at' => now(),
            ];
            // Con o sin goce lo decide RR.HH.: el trabajador solo lo propone.
            if (in_array($s->tipo, [Solicitud::PERMISO, Solicitud::LICENCIA], true) && array_key_exists('con_goce', $datos) && $datos['con_goce'] !== null) {
                $cambios['con_goce'] = (bool) $datos['con_goce'];
            }
            $s->forceFill($cambios)->save();

            $efectos = (new EfectosDeSolicitud())->aplicar($s, (bool) ($datos['restar_faltas'] ?? false));
            $s->forceFill(['efectos' => $efectos])->save();

            return $s;
        });

        Auditoria::registrar('aprobó', 'solicitud', $resultado->id,
            'Aprobó la ' . mb_strtolower($resultado->nombre()) . ' de ' . $this->quien($resultado->empleado) . $this->cuando($resultado) . '.',
            $resultado->tipo === Solicitud::CAMBIO_CUENTA ? ['cuenta' => [
                $resultado->efectos['cuenta_anterior']['numero'] ?? null,
                EfectosDeSolicitud::enmascarar($resultado->datos['numero'] ?? null),
            ]] : null);
        $this->avisarAlTrabajador($resultado);

        return response()->json(['success' => true, 'data' => $this->fila($resultado, conEmpleado: true)]);
    }

    public function rechazar(Request $request, Solicitud $solicitud)
    {
        $datos = $request->validate(['respuesta' => 'required|string|min:5|max:500'], self::MENSAJES);

        if ($this->esPropia($request->user(), $solicitud)) {
            return response()->json(['success' => false, 'message' => 'No puedes resolver tu propia solicitud: la resuelve otra persona.'], 403);
        }

        $s = DB::transaction(function () use ($solicitud, $datos, $request) {
            $s = Solicitud::with('empleado')->whereKey($solicitud->id)->lockForUpdate()->first();
            if ($s->estado !== 'pendiente') {
                throw ValidationException::withMessages(['respuesta' => ['Esta solicitud ya no está pendiente: ' . $s->estado . '.']]);
            }
            $s->forceFill([
                'estado' => 'rechazada', 'respuesta' => trim(strip_tags($datos['respuesta'])),
                'resuelto_por' => $request->user()->id, 'resuelto_at' => now(),
            ])->save();

            return $s;
        });

        Auditoria::registrar('rechazó', 'solicitud', $s->id, 'Rechazó la ' . mb_strtolower($s->nombre()) . ' de ' . $this->quien($s->empleado) . $this->cuando($s) . '.');
        $this->avisarAlTrabajador($s);

        return response()->json(['success' => true, 'data' => $this->fila($s, conEmpleado: true)]);
    }

    /** Revertir una aprobada: se deshace su efecto en lo que todavía no está pagado. */
    public function anular(Request $request, Solicitud $solicitud)
    {
        $datos = $request->validate(['respuesta' => 'required|string|min:5|max:500'], self::MENSAJES);

        if ($this->esPropia($request->user(), $solicitud)) {
            return response()->json(['success' => false, 'message' => 'No puedes resolver tu propia solicitud: la resuelve otra persona.'], 403);
        }

        $avisos = [];
        $s = DB::transaction(function () use ($solicitud, $datos, $request, &$avisos) {
            $s = Solicitud::with('empleado')->whereKey($solicitud->id)->lockForUpdate()->first();
            if ($s->estado !== 'aprobada') {
                throw ValidationException::withMessages(['respuesta' => ['Solo se anula una solicitud aprobada.']]);
            }
            $s->forceFill([
                'estado' => 'anulada', 'respuesta' => trim(strip_tags($datos['respuesta'])),
                'resuelto_por' => $request->user()->id, 'resuelto_at' => now(),
            ])->save();
            $avisos = (new EfectosDeSolicitud())->revertir($s);

            return $s;
        });

        Auditoria::registrar('anuló', 'solicitud', $s->id, 'Anuló la ' . mb_strtolower($s->nombre()) . ' aprobada de ' . $this->quien($s->empleado) . $this->cuando($s) . '.');
        $this->avisarAlTrabajador($s);

        return response()->json(['success' => true, 'data' => [...$this->fila($s, conEmpleado: true), 'avisos' => $avisos]]);
    }

    // ───────────────────────── reglas ─────────────────────────

    private function reglas(string $tipo, Request $request): array
    {
        $comunes = [
            'tipo'   => ['required', Rule::in(array_keys(Solicitud::TIPOS))],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
        $archivo = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];

        return $comunes + match ($tipo) {
            Solicitud::PERMISO => [
                'fecha_inicio' => ['required', 'date_format:Y-m-d'],
                'hora_inicio'  => ['required', 'date_format:H:i'],
                'hora_fin'     => ['required', 'date_format:H:i', 'after:hora_inicio'],
                'con_goce'     => ['required', 'boolean'],
                'sustento'     => ['nullable', ...$archivo],
            ],
            Solicitud::LICENCIA => [
                'fecha_inicio' => ['required', 'date_format:Y-m-d'],
                'fecha_fin'    => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
                'con_goce'     => ['required', 'boolean'],
                // La con goce se paga sin trabajar: va con su respaldo.
                'sustento'     => [$request->boolean('con_goce') ? 'required' : 'nullable', ...$archivo],
            ],
            Solicitud::DESCANSO_MEDICO, Solicitud::JUSTIFICAR_FALTA => [
                'fecha_inicio' => ['required', 'date_format:Y-m-d'],
                'fecha_fin'    => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
                'sustento'     => ['required', ...$archivo],
            ],
            Solicitud::CAMBIO_CUENTA => [
                'destino'  => ['required', Rule::in(['sueldo', 'cts'])],
                'entidad'  => ['required', 'string', 'max:100', 'regex:/^[\pL\pN\s.,&()\'-]+$/u'],
                // Igual que al registrar el depósito de CTS: números, con guiones si el banco la escribe así.
                'numero'   => ['required', 'string', 'min:6', 'max:30', 'regex:/^[0-9]+(-[0-9]+)*$/'],
                'cci'      => [$request->input('destino') === 'sueldo' ? 'required' : 'nullable', 'regex:/^[0-9]{20}$/'],
                'sustento' => ['required', ...$archivo],
                'password' => ['required', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function fechas(string $tipo, array $datos): array
    {
        if ($tipo === Solicitud::CAMBIO_CUENTA) {
            return [null, null];
        }
        $inicio = Carbon::createFromFormat('Y-m-d', $datos['fecha_inicio'])->startOfDay();
        $fin = $tipo === Solicitud::PERMISO ? $inicio->copy() : Carbon::createFromFormat('Y-m-d', $datos['fecha_fin'])->startOfDay();

        return [$inicio, $fin];
    }

    /** Fechas posibles para esa persona y ese tipo, y sin pisar otra ausencia. */
    private function revisarFechas(Empleado $empleado, string $tipo, ?Carbon $inicio, ?Carbon $fin, array $datos): void
    {
        if (! $inicio) {
            return;
        }
        $hoy = now()->startOfDay();
        $error = fn (string $m, string $campo = 'fecha_inicio') => throw ValidationException::withMessages([$campo => [$m]]);

        if ($empleado->fecha_ingreso && $inicio->lt(Carbon::parse($empleado->fecha_ingreso)->startOfDay())) {
            $error('Esa fecha es anterior a tu ingreso (' . Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') . ').');
        }

        $dias = (int) $inicio->diffInDays($fin) + 1;
        match ($tipo) {
            Solicitud::JUSTIFICAR_FALTA => match (true) {
                $fin->gt($hoy)                 => $error('Solo se justifica una falta que ya pasó.', 'fecha_fin'),
                $inicio->lt($hoy->copy()->subDays(90)) => $error('Esa falta es de hace más de 90 días: habla directamente con Recursos Humanos.'),
                $dias > 31                     => $error('Justifica hasta 31 días por solicitud.', 'fecha_fin'),
                default                        => null,
            },
            Solicitud::DESCANSO_MEDICO => match (true) {
                $inicio->lt($hoy->copy()->subDays(90)) => $error('Ese descanso es de hace más de 90 días: habla directamente con Recursos Humanos.'),
                $inicio->gt($hoy->copy()->addDays(30)) => $error('El descanso médico se registra cuando ya lo tienes, no con más de un mes de anticipación.'),
                $dias > 365                    => $error('Un descanso médico por solicitud: hasta 365 días.', 'fecha_fin'),
                default                        => null,
            },
            Solicitud::LICENCIA, Solicitud::PERMISO => match (true) {
                $inicio->lt($hoy->copy()->subDays(30)) => $error('Esa fecha ya pasó hace más de 30 días: habla directamente con Recursos Humanos.'),
                $fin->gt($hoy->copy()->addYear()) => $error('Pide con hasta un año de anticipación.', 'fecha_fin'),
                $dias > 365                    => $error('Una licencia por solicitud: hasta 365 días.', 'fecha_fin'),
                default                        => null,
            },
            default => null,
        };

        if ($tipo === Solicitud::PERMISO) {
            // Un permiso cae en un día que trabaja: no en vacaciones ni en licencia.
            if ($motivo = Cruces::con($empleado->id, $inicio, $fin)) {
                $error($motivo);
            }
            $pisa = Solicitud::where('empleado_id', $empleado->id)->where('tipo', Solicitud::PERMISO)
                ->whereIn('estado', Solicitud::VIGENTES)->whereDate('fecha_inicio', $inicio->toDateString())
                ->where('hora_inicio', '<', $datos['hora_fin'] . ':00')->where('hora_fin', '>', $datos['hora_inicio'] . ':00')
                ->exists();
            if ($pisa) {
                $error('Ya tienes un permiso ese día que se cruza con esas horas.', 'hora_inicio');
            }

            return;
        }

        if ($motivo = Cruces::con($empleado->id, $inicio, $fin)) {
            $error($motivo);
        }
    }

    // ───────────────────────── avisos ─────────────────────────

    private function avisarARrhh(Empleado $empleado, Solicitud $s): void
    {
        $quien = $this->quien($empleado);
        User::where('estado_registro', 'activo')->get()
            ->filter(fn (User $u) => Permisos::puede($u, '/solicitudes') && (string) $u->empleado_id !== (string) $empleado->id)
            ->each(fn (User $u) => Notificacion::create([
                'user_id' => $u->id,
                'tipo'    => 'solicitud_nueva',
                'titulo'  => "{$s->nombre()}: {$quien}",
                'mensaje' => ($s->fecha_inicio ? 'Para' . $this->cuando($s) . '. ' : '') . 'Revísala en Solicitudes.',
            ]));
    }

    private function avisarAlTrabajador(Solicitud $s): void
    {
        $usuario = User::where('empleado_id', $s->empleado_id)->where('estado_registro', 'activo')->first();
        if (! $usuario) {
            return;
        }

        $que = match ($s->estado) {
            'aprobada' => 'aprobada',
            'rechazada' => 'rechazada',
            default    => 'anulada',
        };
        Notificacion::create([
            'user_id' => $usuario->id,
            'tipo'    => 'solicitud_resuelta',
            'titulo'  => "Tu {$this->minuscula($s->nombre())} fue {$que}",
            'mensaje' => $s->respuesta ? 'Recursos Humanos: ' . $s->respuesta : 'Mírala en Mis Solicitudes.',
        ]);

        if ($s->tipo === Solicitud::CAMBIO_CUENTA && $s->estado !== 'anulada') {
            $this->correoDeCuenta($usuario, $s->empleado, $s, $s->estado);
        }
    }

    private function correoDeCuenta(User $usuario, Empleado $empleado, Solicitud $s, string $momento): void
    {
        if (! $usuario->email) {
            return;
        }
        try {
            Mail::to($usuario->email)->queue(new CambioDeCuenta(
                trim("{$empleado->nombre} {$empleado->apellido}"),
                $momento,
                ($s->datos['destino'] ?? '') === 'cts' ? 'CTS' : 'de sueldo',
                (string) ($s->datos['entidad'] ?? ''),
                (string) EfectosDeSolicitud::enmascarar($s->datos['numero'] ?? null),
                $momento === 'pedida' ? null : $s->respuesta,
            ));
        } catch (\Throwable $e) {
            // El correo no tumba la solicitud: el aviso en la campanita ya está.
            Log::warning('No se pudo encolar el correo de cambio de cuenta', ['solicitud' => $s->id, 'error' => $e->getMessage()]);
        }
    }

    // ───────────────────────── piezas ─────────────────────────

    private function fila(Solicitud $s, bool $conEmpleado): array
    {
        $d = $s->datos;
        if ($d) {
            // En las listas, la cuenta enmascarada; entera solo en la revisión de RR.HH.
            $d['numero'] = EfectosDeSolicitud::enmascarar($d['numero'] ?? null);
            if (isset($d['cci'])) {
                $d['cci'] = EfectosDeSolicitud::enmascarar($d['cci']);
            }
        }

        return [
            'id'           => $s->id,
            'tipo'         => $s->tipo,
            'tipo_texto'   => $s->nombre(),
            'fecha_inicio' => $s->fecha_inicio?->toDateString(),
            'fecha_fin'    => $s->fecha_fin?->toDateString(),
            'hora_inicio'  => $s->hora_inicio ? substr($s->hora_inicio, 0, 5) : null,
            'hora_fin'     => $s->hora_fin ? substr($s->hora_fin, 0, 5) : null,
            'dias'         => $s->tipo === Solicitud::PERMISO ? null : ($s->dias() ?: null),
            'con_goce'     => $s->con_goce,
            'motivo'       => $s->motivo,
            'datos'        => $d,
            'tiene_sustento' => (bool) $s->sustento,
            'sustento_nombre' => $s->sustento_nombre,
            'estado'       => $s->estado,
            'respuesta'    => $s->respuesta,
            'resuelto_por' => $conEmpleado ? $s->resolutor?->name : null,
            'resuelto_at'  => $s->resuelto_at?->toIso8601String(),
            'pedida'       => $s->created_at?->toIso8601String(),
            'empleado'     => $conEmpleado && $s->empleado ? [
                'id'     => $s->empleado->id,
                'dni'    => $s->empleado->dni,
                'nombre' => $this->quien($s->empleado),
                'area'   => $s->empleado->area?->nombre,
                'cargo'  => $s->empleado->cargo?->nombre,
            ] : null,
        ];
    }

    private function archivo(Solicitud $s)
    {
        if (! $s->sustento || ! Storage::disk('local')->exists($s->sustento)) {
            return response()->json(['success' => false, 'message' => 'Esta solicitud no tiene sustento.'], 404);
        }
        $ext = strtolower(pathinfo($s->sustento, PATHINFO_EXTENSION));
        $tipo = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'][$ext] ?? 'application/octet-stream';

        return response(Storage::disk('local')->get($s->sustento), 200, [
            'Content-Type'           => $tipo,
            'Content-Disposition'    => 'inline; filename="sustento.' . $ext . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    private function empleadoDe(Request $request): ?Empleado
    {
        $id = $request->user()?->empleado_id;

        return $id ? Empleado::find($id) : null;
    }

    private function sinFicha()
    {
        return response()->json(['success' => false, 'message' => 'Tu cuenta no tiene una ficha de trabajador.'], 403);
    }

    /** Nadie resuelve lo suyo; el superadministrador sí, porque no tiene a nadie encima. */
    private function esPropia(User $u, Solicitud $s): bool
    {
        return $u->empleado_id && (string) $u->empleado_id === (string) $s->empleado_id && ! $u->esSuperadmin();
    }

    private function quien(?Empleado $e): string
    {
        return $e ? trim("{$e->apellido} {$e->nombre}") : '';
    }

    private function minuscula(string $texto): string
    {
        return mb_strtolower(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }

    /** « del 03/11/2026 al 05/11/2026», « el 03/11/2026 de 09:00 a 11:00»; vacío en un cambio de cuenta. */
    private function cuando(Solicitud $s): string
    {
        if (! $s->fecha_inicio) {
            return '';
        }
        if ($s->tipo === Solicitud::PERMISO) {
            return ' el ' . $s->fecha_inicio->format('d/m/Y') . ' de ' . substr((string) $s->hora_inicio, 0, 5) . ' a ' . substr((string) $s->hora_fin, 0, 5);
        }
        $d = $s->fecha_inicio->format('d/m/Y');
        $h = $s->fecha_fin->format('d/m/Y');

        return $d === $h ? " el {$d}" : " del {$d} al {$h}";
    }
}
