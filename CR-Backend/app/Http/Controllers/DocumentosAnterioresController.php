<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Empleado;
use App\Support\ExpedienteDigital;
use App\Support\LectorDeCeldas;
use App\Support\Meses;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Boletas y contratos de antes del sistema, subidos en lote al expediente de
 * cada trabajador.
 *
 * RR.HH. tiene años de PDFs sacados del Excel de la planilla, y lo difícil no
 * es guardarlos: es saber de quién es cada uno. El navegador lee el texto de
 * cada PDF (la boleta del colegio dice "DNI: 42558107" y "Del 01/03/2026 al
 * 31/03/2026") y el nombre del archivo, y aquí solo llega lo que encontró:
 *
 *   previsualizar   de quién es cada archivo, qué es y de qué mes; lo que ya
 *                   estaba y lo que hay que decidir a mano. No guarda nada.
 *   subir           un archivo, con el trabajador ya decidido. Se vuelve a
 *                   revisar todo: la pantalla no es una cerradura.
 *
 * No es todo o nada, como los Excel: cada archivo es independiente, y uno sin
 * dueño no tiene por qué frenar a los otros 499.
 */
class DocumentosAnterioresController extends Controller
{
    private const MAX_ARCHIVOS = 1000;
    private const PRIMER_ANIO  = 1990;
    private const TIPOS        = ExpedienteDigital::ANTERIORES;

    /** POST /documentos-anteriores/previsualizar */
    public function previsualizar(Request $request)
    {
        $datos = $request->validate([
            'archivos'               => 'required|array|min:1|max:' . self::MAX_ARCHIVOS,
            'archivos.*.indice'      => 'required|integer|min:0',
            'archivos.*.nombre'      => 'required|string|max:255',
            'archivos.*.huella'      => 'nullable|string|regex:/^[0-9a-f]{64}$/',
            'archivos.*.tipo'        => ['nullable', Rule::in(self::TIPOS)],
            'archivos.*.dnis'        => 'present|array|max:20',
            'archivos.*.dnis.*'      => 'string|max:12',
            'archivos.*.dni_nombre'  => 'nullable|string|max:12',
            'archivos.*.dni_elegido' => 'nullable|string|max:12',
            'archivos.*.mes'         => 'nullable|integer|between:1,12',
            'archivos.*.anio'        => 'nullable|integer|min:' . self::PRIMER_ANIO . '|max:2100',
        ], [
            'archivos.max' => 'Son más de ' . self::MAX_ARCHIVOS . ' archivos: súbelos en dos tandas.',
        ]);

        $archivos = $datos['archivos'];

        // ── Todo lo que hay que consultar, de una vez ─────────────
        $claves = collect($archivos)
            ->flatMap(fn ($a) => [...$a['dnis'], $a['dni_nombre'] ?? null, $a['dni_elegido'] ?? null])
            ->filter()
            ->flatMap(fn ($dni) => LectorDeCeldas::dni($dni)['claves'])
            ->unique()->values();

        $empleados = Empleado::whereIn('dni', $claves->all() ?: ['-'])
            ->get(['id', 'dni', 'nombre', 'apellido', 'estado'])
            ->keyBy('dni');

        $ids = $empleados->pluck('id')->all();

        $huellas = Documento::whereIn('empleado_id', $ids ?: ['-'])
            ->whereNotNull('huella')
            ->where('estado_registro', 'activo')
            ->get(['empleado_id', 'huella'])
            ->mapWithKeys(fn ($d) => [$d->empleado_id . '|' . $d->huella => true]);

        $boletasSubidas   = $this->boletasAnteriores($ids);
        $boletasDelSistema = $this->boletasDelSistema($ids);

        // ── Archivo por archivo ─────────────────────────────────
        $filas        = [];
        $vistosHuella = [];
        $vistosMes    = [];

        foreach ($archivos as $a) {
            $tipo = $a['tipo'] ?? null;
            $fila = [
                'indice'     => (int) $a['indice'],
                'nombre'     => $a['nombre'],
                'tipo'       => $tipo,
                // El mes solo cuenta en una boleta: un contrato se nombra por su año.
                'mes'        => $tipo === ExpedienteDigital::BOLETA_ANTERIOR ? ($a['mes'] ?? null) : null,
                'anio'       => $a['anio'] ?? null,
                'estado'     => 'lista',
                'mensaje'    => '',
                'origen'     => null,
                'empleado'   => null,
                'candidatos' => [],
            ];

            // ¿De quién es?
            [$empleado, $origen, $candidatos, $problema] = $this->duenio($a, $empleados);
            $fila['empleado']   = $empleado ? $this->paraPantalla($empleado) : null;
            $fila['origen']     = $origen;
            $fila['candidatos'] = $candidatos->map(fn ($e) => $this->paraPantalla($e))->values()->all();

            if ($problema) {
                $filas[] = [...$fila, 'estado' => $candidatos->isNotEmpty() ? 'elegir' : 'error', 'mensaje' => $problema];
                continue;
            }

            // ¿Qué es, y de cuándo?
            if ($problema = $this->problemaDeTipoYPeriodo($fila['tipo'], $fila['mes'], $fila['anio'])) {
                $filas[] = [...$fila, 'estado' => 'error', 'mensaje' => $problema];
                continue;
            }

            // ¿Ya estaba?
            $huella = $a['huella'] ?? null;
            if ($huella && isset($huellas[$empleado->id . '|' . $huella])) {
                $filas[] = [...$fila, 'estado' => 'omitida', 'mensaje' => 'Este mismo archivo ya está en su expediente.'];
                continue;
            }
            if ($huella && isset($vistosHuella[$huella])) {
                $filas[] = [...$fila, 'estado' => 'omitida', 'mensaje' => "Es el mismo archivo que «{$vistosHuella[$huella]}»."];
                continue;
            }

            if ($fila['tipo'] === ExpedienteDigital::BOLETA_ANTERIOR) {
                $periodo = $this->periodo($fila['mes'], $fila['anio']);
                $clave   = "{$empleado->id}|{$fila['anio']}|{$fila['mes']}";

                if (isset($boletasDelSistema[$clave])) {
                    $filas[] = [...$fila, 'estado' => 'omitida', 'mensaje' => "El sistema ya generó su boleta de {$periodo}."];
                    continue;
                }
                if (isset($boletasSubidas[$clave])) {
                    $filas[] = [...$fila, 'estado' => 'omitida', 'mensaje' => "Ya tiene subida su boleta de {$periodo}."];
                    continue;
                }
                // Dos PDFs distintos que dicen ser la misma boleta: uno de los
                // dos está mal, y eso lo tiene que mirar una persona.
                if (isset($vistosMes[$clave])) {
                    $filas[] = [...$fila, 'estado' => 'error', 'mensaje' => "«{$vistosMes[$clave]}» también dice ser su boleta de {$periodo}. Revisa cuál es la buena."];
                    continue;
                }
                $vistosMes[$clave] = $a['nombre'];
            }

            if ($huella) {
                $vistosHuella[$huella] = $a['nombre'];
            }

            $fila['mensaje'] = match ($origen) {
                'pdf'     => 'Encontrado dentro del PDF.',
                'nombre'  => 'Por el DNI del nombre del archivo.',
                default   => 'Elegido a mano.',
            };
            $filas[] = $fila;
        }

        $porEstado = collect($filas)->countBy('estado');

        return response()->json([
            'success' => true,
            'data'    => [
                'filas'   => $filas,
                'resumen' => [
                    'archivos'    => count($filas),
                    'listas'      => $porEstado['lista'] ?? 0,
                    'omitidas'    => $porEstado['omitida'] ?? 0,
                    'por_revisar' => ($porEstado['error'] ?? 0) + ($porEstado['elegir'] ?? 0),
                ],
            ],
        ]);
    }

    /** POST /documentos-anteriores — un archivo del lote, con su dueño ya decidido. */
    public function subir(Request $request)
    {
        $datos = $request->validate([
            'archivo' => ExpedienteDigital::REGLA_ARCHIVO,
            'dni'     => 'required|string|max:12',
            'tipo'    => ['required', Rule::in(self::TIPOS)],
            'mes'     => 'nullable|required_if:tipo,' . ExpedienteDigital::BOLETA_ANTERIOR . '|integer|between:1,12',
            'anio'    => 'nullable|required_if:tipo,' . ExpedienteDigital::BOLETA_ANTERIOR . '|integer|min:' . self::PRIMER_ANIO . '|max:2100',
        ], [
            'archivo.mimes'    => 'El archivo tiene que ser PDF, Word o una imagen (JPG o PNG).',
            'archivo.max'      => 'El archivo pasa de 5 MB.',
            'mes.required_if'  => 'Falta el mes de la boleta.',
            'anio.required_if' => 'Falta el año de la boleta.',
        ]);

        $empleado = Empleado::whereIn('dni', LectorDeCeldas::dni($datos['dni'])['claves'] ?: ['-'])->first();
        if (! $empleado) {
            return $this->rechazo("No hay ningún trabajador con el DNI {$datos['dni']}.");
        }

        $esBoleta = $datos['tipo'] === ExpedienteDigital::BOLETA_ANTERIOR;
        $mes      = $esBoleta ? (int) $datos['mes'] : null;
        $anio     = isset($datos['anio']) ? (int) $datos['anio'] : null;

        if ($problema = $this->problemaDeTipoYPeriodo($datos['tipo'], $mes, $anio)) {
            return $this->rechazo($problema);
        }

        $archivo = $request->file('archivo');
        $yaEsta  = Documento::where('empleado_id', $empleado->id)
            ->where('huella', ExpedienteDigital::huella($archivo))
            ->where('estado_registro', 'activo')
            ->exists();
        if ($yaEsta) {
            return $this->rechazo('Este mismo archivo ya está en su expediente.');
        }

        if ($esBoleta) {
            $clave   = "{$empleado->id}|{$anio}|{$mes}";
            $periodo = $this->periodo($mes, $anio);

            if (isset($this->boletasDelSistema([$empleado->id])[$clave])) {
                return $this->rechazo("El sistema ya generó su boleta de {$periodo}.");
            }
            if (isset($this->boletasAnteriores([$empleado->id])[$clave])) {
                return $this->rechazo("Ya tiene subida su boleta de {$periodo}.");
            }
        }

        $documento = ExpedienteDigital::guardar($empleado->id, $datos['tipo'], $archivo, [
            'periodo_mes'  => $mes,
            'periodo_anio' => $anio,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'documento' => $documento,
                'dni'       => $empleado->dni,
                'nombre'    => trim($empleado->nombre . ' ' . $empleado->apellido),
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * De quién es un archivo, en este orden:
     *
     *   1. el DNI que se eligió a mano en la revisión
     *   2. el DNI de dentro del PDF, si es de UNA sola persona del sistema
     *   3. el DNI del nombre del archivo
     *
     * Un contrato trae dos DNI —el del trabajador y el de quien firma por el
     * colegio— y quien firma también puede estar en planilla. Con dos
     * candidatos no se adivina: desempata el nombre del archivo o una persona.
     *
     * @return array{0: ?Empleado, 1: ?string, 2: Collection, 3: ?string}
     */
    private function duenio(array $a, Collection $empleados): array
    {
        $buscar = function (?string $dni) use ($empleados): ?Empleado {
            foreach ($dni ? LectorDeCeldas::dni($dni)['claves'] : [] as $clave) {
                if ($empleados->has($clave)) {
                    return $empleados->get($clave);
                }
            }

            return null;
        };

        if (! empty($a['dni_elegido'])) {
            $elegido = $buscar($a['dni_elegido']);

            return $elegido
                ? [$elegido, 'elegido', collect(), null]
                : [null, null, collect(), "No hay ningún trabajador con el DNI {$a['dni_elegido']}."];
        }

        $delPdf    = collect($a['dnis'])->map($buscar)->filter()->unique('id')->values();
        $delNombre = $buscar($a['dni_nombre'] ?? null);

        if ($delPdf->count() === 1) {
            return [$delPdf->first(), 'pdf', collect(), null];
        }
        if ($delPdf->count() > 1) {
            return $delNombre && $delPdf->contains('id', $delNombre->id)
                ? [$delNombre, 'nombre', collect(), null]
                : [null, null, $delPdf, 'El PDF trae el DNI de ' . $delPdf->count() . ' personas del sistema: elige de quién es.'];
        }
        if ($delNombre) {
            return [$delNombre, 'nombre', collect(), null];
        }

        $leidos = collect([...$a['dnis'], $a['dni_nombre'] ?? null])->filter()->unique()->values();

        return [null, null, collect(), $leidos->isNotEmpty()
            ? 'El DNI ' . $leidos->implode(', ') . ' no es de nadie en el sistema. Si ya no trabaja aquí, dalo de alta como «Cesado» desde Importar empleados y vuelve a revisar.'
            : 'No encontramos ningún DNI, ni dentro del archivo ni en su nombre: escribe de quién es.'];
    }

    private function problemaDeTipoYPeriodo(?string $tipo, ?int $mes, ?int $anio): ?string
    {
        if (! $tipo) {
            return 'No sabemos si es una boleta o un contrato: elige qué es.';
        }
        if ($tipo !== ExpedienteDigital::BOLETA_ANTERIOR) {
            return $anio && $anio > now()->year ? "El año {$anio} todavía no llega." : null;
        }
        if (! $mes || ! $anio) {
            return 'No encontramos de qué mes es la boleta: elige el mes y el año.';
        }
        if ($anio * 12 + $mes > now()->year * 12 + now()->month) {
            return 'La boleta es de ' . $this->periodo($mes, $anio) . ', un mes que todavía no llega.';
        }

        return null;
    }

    /** Las boletas anteriores ya subidas, como "empleado|año|mes". */
    private function boletasAnteriores(array $ids): array
    {
        return Documento::whereIn('empleado_id', $ids ?: ['-'])
            ->where('tipo', ExpedienteDigital::BOLETA_ANTERIOR)
            ->where('estado_registro', 'activo')
            ->get(['empleado_id', 'periodo_anio', 'periodo_mes'])
            ->mapWithKeys(fn ($d) => ["{$d->empleado_id}|{$d->periodo_anio}|{$d->periodo_mes}" => true])
            ->all();
    }

    /** Las boletas que generó el sistema, por el periodo de su planilla. */
    private function boletasDelSistema(array $ids): array
    {
        return Documento::query()
            ->join('planilla', 'planilla.id', '=', 'documentos.planilla_id')
            ->whereIn('documentos.empleado_id', $ids ?: ['-'])
            ->where('documentos.tipo', 'boleta')
            ->where('documentos.estado_registro', 'activo')
            ->get(['documentos.empleado_id', 'planilla.anio', 'planilla.mes'])
            ->mapWithKeys(fn ($d) => ["{$d->empleado_id}|{$d->anio}|{$d->mes}" => true])
            ->all();
    }

    private function periodo(?int $mes, ?int $anio): string
    {
        return trim(Meses::nombre($mes) . ' ' . $anio);
    }

    private function paraPantalla(Empleado $e): array
    {
        return [
            'id'     => $e->id,
            'dni'    => $e->dni,
            'nombre' => trim($e->apellido . ', ' . $e->nombre),
            'estado' => $e->estado,
        ];
    }

    private function rechazo(string $mensaje)
    {
        return response()->json(['success' => false, 'message' => $mensaje], 422);
    }
}
