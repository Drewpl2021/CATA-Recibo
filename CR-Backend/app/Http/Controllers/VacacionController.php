<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Vacacion;
use App\Traits\ListadoPaginado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Las vacaciones del personal.
 *
 * El flujo es de dos manos: el trabajador SOLICITA y RR.HH. RESUELVE.
 * Nadie se aprueba lo suyo, y nadie pide por otro.
 *
 * Lo que se corrigió aquí, porque el endpoint estaba abierto de par en par:
 *
 *  - Un docente veía las vacaciones de todo el colegio. Ahora el listado se
 *    recorta al empleado del token salvo que sea RR.HH. o Administración.
 *  - Se creaba con `$request->all()`, y como `estado` es asignable bastaba
 *    mandar "estado":"aprobado" para saltarse la aprobación — probado, y
 *    devolvía 201. Ahora la solicitud nace SIEMPRE en 'pendiente' y solo se
 *    escriben los campos que se validaron uno por uno.
 *  - El empleado_id venía en el cuerpo: se podía pedir a nombre de otro.
 *  - Los días los declaraba el cliente: pedir del 1 al 30 diciendo "1 día"
 *    colaba. Ahora se cuentan de las fechas y punto.
 *  - `aprobado_por` era texto libre del solicitante. Ahora sale del token.
 */
class VacacionController extends Controller
{
    use ListadoPaginado;

    /** Lo que da la ley peruana por un año completo de servicio. */
    const DIAS_POR_ANIO = 30;

    /**
     * GET /vacaciones
     *
     * RR.HH. ve las de todos y puede filtrar por empleado; el trabajador ve
     * las suyas, y el empleado_id de la petición se ignora para él.
     */
    public function index(Request $request)
    {
        $request->validate([
            'estado'      => 'nullable|in:pendiente,aprobado,rechazado',
            'empleado_id' => 'nullable|uuid',
            'anio'        => 'nullable|integer|min:2000',
        ]);

        $query = Vacacion::with('empleado:id,nombre,apellido,dni')
            ->orderBy('fecha_inicio', 'desc');

        if ($this->resuelveVacaciones($request)) {
            if ($request->filled('empleado_id')) {
                $query->where('empleado_id', $request->input('empleado_id'));
            }
        } else {
            $query->where('empleado_id', $this->empleadoDelToken($request));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('anio')) {
            $query->whereYear('fecha_inicio', $request->input('anio'));
        }

        // Las dadas de baja solo las ve Administración, como en el resto del
        // sistema.
        if ($request->user()->rol?->nombre !== 'admin') {
            $query->where('estado_registro', 'activo');
        }

        return $this->responderListado(
            $request,
            $query,
            ['motivo', 'empleado.nombre', 'empleado.apellido', 'empleado.dni'],
            fn (Builder $filtrada) => $this->conteoPorEstado($filtrada, 'estado', [
                'pendientes' => 'pendiente',
                'aprobadas'  => 'aprobado',
                'rechazadas' => 'rechazado',
            ])
        );
    }

    /**
     * GET /vacaciones/saldo?empleado_id=&anio=
     *
     * Cuántos días le tocan, cuántos gastó y cuántos le quedan. Es lo que
     * pinta la pantalla antes de dejar pedir: enterarse de que no alcanzan
     * DESPUÉS de llenar el formulario es la peor forma de enterarse.
     */
    public function saldo(Request $request)
    {
        $request->validate([
            'empleado_id' => 'nullable|uuid|exists:empleados,id',
            'anio'        => 'nullable|integer|min:2000',
        ]);

        $empleadoId = $this->resuelveVacaciones($request) && $request->filled('empleado_id')
            ? $request->input('empleado_id')
            : $this->empleadoDelToken($request);

        $anio = (int) ($request->input('anio') ?: now()->year);

        return response()->json([
            'success' => true,
            'data'    => $this->calcularSaldo($empleadoId, $anio),
        ]);
    }

    /**
     * POST /vacaciones
     *
     * El trabajador pide para sí; RR.HH. puede registrar la solicitud de
     * cualquiera (llega gente que lo pide en papel, en la oficina).
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'empleado_id'  => 'nullable|uuid|exists:empleados,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'motivo'       => 'nullable|string|max:500',
        ]);

        $empleadoId = $this->resuelveVacaciones($request) && $request->filled('empleado_id')
            ? $datos['empleado_id']
            : $this->empleadoDelToken($request);

        $inicio = Carbon::parse($datos['fecha_inicio'])->startOfDay();
        $fin    = Carbon::parse($datos['fecha_fin'])->startOfDay();

        // Días de calendario, ambos extremos incluidos: del lunes al lunes
        // son 8 días de vacaciones, no 7. Así lo cuenta la boleta.
        $dias = (int) $inicio->diffInDays($fin) + 1;

        $this->rechazarSiSeCruza($empleadoId, $inicio, $fin);

        $saldo = $this->calcularSaldo($empleadoId, $inicio->year);

        if ($dias > $saldo['diasDisponibles']) {
            throw ValidationException::withMessages([
                'fecha_fin' => [
                    "No alcanzan los días: quedan {$saldo['diasDisponibles']} y estás pidiendo {$dias}.",
                ],
            ]);
        }

        // Campo por campo a propósito: nada de $request->all(). El estado lo
        // pone el sistema, no quien solicita.
        $vacacion = Vacacion::create([
            'empleado_id'      => $empleadoId,
            'fecha_inicio'     => $inicio->toDateString(),
            'fecha_fin'        => $fin->toDateString(),
            'dias_solicitados' => $dias,
            'motivo'           => $datos['motivo'] ?? null,
            'estado'           => 'pendiente',
            // Explícitos en null para que la respuesta traiga siempre las
            // mismas claves: quien la lea no tiene que distinguir entre "vino
            // vacío" y "no vino".
            'aprobado_por'     => null,
            'aprobado_at'      => null,
            'estado_registro'  => 'activo',
        ]);

        $saldoNuevo = $this->calcularSaldo($empleadoId, $inicio->year);

        return response()->json([
            'success'        => true,
            'data'           => $vacacion->load('empleado:id,nombre,apellido,dni'),
            'dias_restantes' => $saldoNuevo['diasDisponibles'],
        ], 201);
    }

    /** GET /vacaciones/{id} — el dueño de la solicitud, o RR.HH. */
    public function show(Request $request, string $id)
    {
        $vacacion = Vacacion::with('empleado:id,nombre,apellido,dni')->findOrFail($id);

        if (! $this->resuelveVacaciones($request)
            && $vacacion->empleado_id !== $this->empleadoDelToken($request)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Esta solicitud no es tuya.'],
            ], 403);
        }

        return response()->json(['success' => true, 'data' => $vacacion]);
    }

    /**
     * PUT /vacaciones/{id} — aprobar o rechazar. Solo RR.HH. y Admin.
     *
     * Aquí NO se tocan fechas ni días: cambiarlos después de pedidos sería
     * resolver una solicitud distinta a la que se hizo. Si hay que corregir
     * las fechas, se rechaza y se pide de nuevo.
     */
    public function update(Request $request, string $id)
    {
        $datos = $request->validate([
            'estado'      => 'required|in:pendiente,aprobado,rechazado',
            'observacion' => 'nullable|string|max:500',
        ]);

        $vacacion = Vacacion::findOrFail($id);

        if ($datos['estado'] === 'aprobado' && $vacacion->estado !== 'aprobado') {
            // Se revisa otra vez al aprobar: entre que se pidió y se resuelve
            // pueden haberse aprobado otras solicitudes del mismo trabajador.
            $saldo = $this->calcularSaldo(
                $vacacion->empleado_id,
                Carbon::parse($vacacion->fecha_inicio)->year,
                $vacacion->id
            );

            if ($vacacion->dias_solicitados > $saldo['diasDisponibles']) {
                throw ValidationException::withMessages([
                    'estado' => [
                        "Ya no le alcanzan los días: le quedan {$saldo['diasDisponibles']} y esta solicitud pide {$vacacion->dias_solicitados}.",
                    ],
                ]);
            }
        }

        $vacacion->update([
            'estado'      => $datos['estado'],
            'observacion' => $datos['observacion'] ?? $vacacion->observacion,
            // Quién resolvió sale del token. Antes lo mandaba el cliente y se
            // podía firmar con el nombre de cualquiera.
            'aprobado_por' => $datos['estado'] === 'pendiente' ? null : $request->user()->name,
            'aprobado_at'  => $datos['estado'] === 'pendiente' ? null : now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $vacacion->load('empleado:id,nombre,apellido,dni'),
        ]);
    }

    /**
     * DELETE /vacaciones/{id} — retirar una solicitud. Baja lógica, como en
     * el resto del sistema.
     *
     * RR.HH. puede retirar cualquiera; el trabajador solo la suya, y solo
     * mientras siga pendiente: una vez resuelta, borrarla por su cuenta le
     * dejaría a RR.HH. sin rastro de lo que aprobó.
     */
    public function destroy(Request $request, string $id)
    {
        $vacacion = Vacacion::findOrFail($id);

        if (! $this->resuelveVacaciones($request)) {
            if ($vacacion->empleado_id !== $this->empleadoDelToken($request)) {
                return response()->json([
                    'success' => false,
                    'data'    => ['message' => 'Esta solicitud no es tuya.'],
                ], 403);
            }

            if ($vacacion->estado !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'data'    => ['message' => 'Esta solicitud ya fue resuelta. Habla con Recursos Humanos.'],
                ], 422);
            }
        }

        $vacacion->update(['estado_registro' => 'inactivo']);

        return response()->json([
            'success' => true,
            'data'    => ['message' => 'Solicitud de vacaciones eliminada.'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────

    /** ¿Este usuario resuelve vacaciones ajenas, o solo pide las suyas? */
    private function resuelveVacaciones(Request $request): bool
    {
        return in_array($request->user()->rol?->nombre, ['rrhh', 'admin'], true);
    }

    /**
     * El empleado dueño de la sesión.
     *
     * Un usuario sin empleado (una cuenta de sistema) no tiene vacaciones que
     * pedir; se le devuelve un id imposible para que su listado salga vacío
     * en vez de mostrarle el de todos.
     */
    private function empleadoDelToken(Request $request): string
    {
        return $request->user()->empleado_id ?? '00000000-0000-0000-0000-000000000000';
    }

    /**
     * Días ganados, gastados y disponibles de un empleado en un año.
     *
     * Los ganados salen de la antigüedad: quien ya cumplió su año de servicio
     * tiene los 30; quien entró hace poco, la parte proporcional a razón de
     * 2.5 por mes cumplido (el mismo doceavo con que se calculan las
     * vacaciones truncas en la boleta). Si el colegio decide otra regla, se
     * cambia aquí y en DIAS_POR_ANIO, en un solo sitio.
     *
     * @param  string|null $exceptoId  solicitud que no debe contarse como
     *                                 gastada (se usa al aprobarla ella misma).
     */
    private function calcularSaldo(string $empleadoId, int $anio, ?string $exceptoId = null): array
    {
        $empleado = Empleado::find($empleadoId);
        $ingreso  = $empleado?->fecha_ingreso ? Carbon::parse($empleado->fecha_ingreso) : null;

        // Se mide hasta el cierre del año consultado, o hasta hoy si el año
        // todavía está corriendo.
        $corte = now()->lt(Carbon::create($anio, 12, 31)) ? now() : Carbon::create($anio, 12, 31);
        $meses = $ingreso && $ingreso->lte($corte) ? (int) $ingreso->diffInMonths($corte) : 0;

        $ganados = $meses >= 12
            ? self::DIAS_POR_ANIO
            : (int) floor($meses * (self::DIAS_POR_ANIO / 12));

        $usadas = Vacacion::where('empleado_id', $empleadoId)
            ->whereYear('fecha_inicio', $anio)
            ->where('estado_registro', 'activo')
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->when($exceptoId, fn ($q) => $q->where('id', '!=', $exceptoId))
            ->sum('dias_solicitados');

        return [
            'anio'            => $anio,
            'mesesTrabajados' => $meses,
            'diasGanados'     => $ganados,
            'diasUsados'      => (int) $usadas,
            'diasDisponibles' => max(0, $ganados - (int) $usadas),
        ];
    }

    /**
     * Dos periodos de vacaciones no pueden pisarse.
     *
     * Sin esto se podía pedir la misma semana dos veces y gastar el saldo por
     * duplicado sin que nadie lo notara hasta que el trabajador no apareciera.
     */
    private function rechazarSiSeCruza(string $empleadoId, Carbon $inicio, Carbon $fin): void
    {
        $cruce = Vacacion::where('empleado_id', $empleadoId)
            ->where('estado_registro', 'activo')
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->where('fecha_inicio', '<=', $fin->toDateString())
            ->where('fecha_fin', '>=', $inicio->toDateString())
            ->first();

        if ($cruce) {
            throw ValidationException::withMessages([
                'fecha_inicio' => [
                    'Esas fechas se cruzan con otra solicitud del ' .
                    Carbon::parse($cruce->fecha_inicio)->format('d/m/Y') . ' al ' .
                    Carbon::parse($cruce->fecha_fin)->format('d/m/Y') . '.',
                ],
            ]);
        }
    }
}
