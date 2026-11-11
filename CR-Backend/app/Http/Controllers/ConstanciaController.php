<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Notificacion;
use App\Models\User;
use App\Support\Documentos\Constancias;
use App\Support\Documentos\ExpedienteDigital;
use App\Support\Firma\FirmaDelColegio;
use App\Support\Meses;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Las constancias de trabajo y de haberes (ver App\Support\Documentos\Constancias).
 *
 *   POST   /my-documents/letters        el trabajador pide la suya (se arma al momento)
 *   GET    /letters/pending             RR.HH.: las que esperan la firma del colegio
 *   POST   /letters/sign-here           RR.HH.: «Firmar aquí», como las boletas
 *   DELETE /letters/{documento}         RR.HH.: anular una que no corresponde, antes de firmarla
 */
class ConstanciaController extends Controller
{
    /**
     * El trabajador pide su constancia. Se arma al momento con sus datos y
     * sus planillas, y queda esperando la firma del colegio; a RR.HH. le llega
     * el aviso. Si ya pidió la misma y todavía no se firmó, no se arma otra.
     */
    public function pedir(Request $request)
    {
        $datos = $request->validate([
            'tipo'       => ['required', Rule::in(array_keys(Constancias::TIPOS))],
            'meses'      => ['nullable', 'integer', Rule::in(Constancias::MESES_HABERES)],
            // A quién va dirigida (un banco, una cooperativa): sin ella, «A quien corresponda».
            'dirigido_a' => ['nullable', 'string', 'max:120', 'regex:/^[\pL\pN\s.,&()\'-]*$/u'],
        ], [
            'dirigido_a.regex' => 'Escribe solo el nombre de la entidad: letras, números y puntos.',
            'dirigido_a.max'   => 'El nombre de la entidad es muy largo: hasta 120 letras.',
        ]);

        $empleado = $request->user()->empleado_id ? Empleado::find($request->user()->empleado_id) : null;
        if (! $empleado) {
            return response()->json(['success' => false, 'message' => 'Tu cuenta no tiene una ficha de trabajador: no se puede armar una constancia.'], 403);
        }

        $tipo = $datos['tipo'];
        $nombre = Constancias::TIPOS[$tipo];
        if ($ya = Constancias::pendiente($empleado, $tipo)) {
            return response()->json(['success' => true, 'data' => [
                'id'      => $ya->id,
                'nueva'   => false,
                'message' => "Ya pediste tu {$nombre}: está esperando la firma del colegio. Te avisaremos apenas esté lista.",
            ]]);
        }

        $meses = (int) ($datos['meses'] ?? 3);
        if ($motivo = Constancias::porQueNo($empleado, $tipo, $meses)) {
            return response()->json(['success' => false, 'message' => $motivo], 422);
        }

        $documento = DB::transaction(fn () => Constancias::generar($empleado, $tipo, $meses, $datos['dirigido_a'] ?? null));

        Auditoria::registrar('creó', 'constancia', (string) $documento->id,
            "Pidió su {$nombre}" . ($tipo === ExpedienteDigital::CONSTANCIA_HABERES ? " de {$meses} meses" : '') . '.');
        $this->avisarARrhh($empleado, $nombre, $documento);

        return response()->json(['success' => true, 'data' => [
            'id'      => $documento->id,
            'nueva'   => true,
            'message' => "Tu {$nombre} ya está armada. Recursos Humanos la firma y te avisamos por la campanita y por correo.",
        ]], 201);
    }

    /** Las que esperan la firma del colegio, para la bandeja de RR.HH. */
    public function pendientes(Request $request)
    {
        $documentos = $this->porFirmar();
        $filas = (clone $documentos)->with('empleado:id,dni,nombre,apellido')->orderBy('created_at')->get()
            ->map(fn (Documento $d) => [
                'id'         => $d->id,
                'tipo'       => $d->tipo,
                'tipo_texto' => ExpedienteDigital::NOMBRES[$d->tipo] ?? $d->tipo,
                'dni'        => $d->empleado?->dni,
                'nombre'     => trim("{$d->empleado?->apellido} {$d->empleado?->nombre}"),
                'pedida'     => $d->created_at?->toIso8601String(),
                'firma'      => $d->firma_colegio,
            ])->values();

        return response()->json(['success' => true, 'data' => [
            'filas' => $filas,
            'firma' => FirmaDelColegio::resumen($request, $documentos),
        ]]);
    }

    /** «Firmar aquí» de las constancias que esperan la firma de quien pide. */
    public function firmarAqui(Request $request)
    {
        return FirmaDelColegio::firmarTanda(
            $request,
            $this->porFirmar(),
            'constancias',
            fn (Documento $d) => ucfirst(Constancias::TIPOS[$d->tipo] ?? 'constancia'),
            fn (Documento $d) => FirmaDelColegio::avisarAlTrabajador(
                $d, Constancias::TIPOS[$d->tipo] ?? 'constancia',
                mb_strtolower(Meses::nombre((int) $d->periodo_mes)) . ' ' . $d->periodo_anio,
                conConformidad: false,
            ),
            fn (Documento $d) => null,
            fn (int $firmadas, string $detalle) => Auditoria::registrar('firmó', 'constancia', null,
                "Firmó desde el sistema {$firmadas} constancia(s) con su certificado ({$detalle}).", ['firmadas' => $firmadas]),
        );
    }

    /** Anula una constancia que no corresponde, antes de firmarla. */
    public function anular(string $documento)
    {
        $d = Documento::with('empleado:id,nombre,apellido')
            ->whereIn('tipo', ExpedienteDigital::CONSTANCIAS)->findOrFail($documento);
        if ($d->tieneFirmaDelColegio()) {
            return response()->json(['success' => false, 'message' => 'Esta constancia ya está firmada: no se anula.'], 422);
        }

        $archivo = $d->archivo;
        $quien = trim("{$d->empleado?->nombre} {$d->empleado?->apellido}");
        DB::transaction(function () use ($d, $quien) {
            Notificacion::where('documento_id', $d->id)->delete();
            $d->delete();
            Auditoria::registrar('borró', 'constancia', (string) $d->id, "Anuló la " . (Constancias::TIPOS[$d->tipo] ?? 'constancia') . " que pidió {$quien}.");
        });
        Storage::disk('local')->delete($archivo);

        return response()->json(['success' => true, 'message' => 'Constancia anulada.']);
    }

    private function porFirmar(): \Illuminate\Database\Eloquent\Builder
    {
        return Documento::whereIn('tipo', ExpedienteDigital::CONSTANCIAS)
            ->where('estado_registro', 'activo')
            ->whereIn('firma_colegio', Documento::FIRMA_COLEGIO_EN_CURSO);
    }

    /** A quien lleva el expediente (RR.HH. y Administración), en su campanita. */
    private function avisarARrhh(Empleado $empleado, string $nombre, Documento $documento): void
    {
        $quien = trim("{$empleado->apellido} {$empleado->nombre}");
        User::where('estado_registro', 'activo')->get()
            ->filter(fn (User $u) => Permisos::puede($u, '/documentos') && (string) $u->empleado_id !== (string) $empleado->id)
            ->each(fn (User $u) => Notificacion::create([
                'user_id'      => $u->id,
                'tipo'         => 'constancia_pedida',
                'titulo'       => "Constancia por firmar: {$quien}",
                'mensaje'      => ucfirst($nombre) . ' pedida desde Mis Documentos. Fírmala en Documentos → Constancias por firmar.',
                'documento_id' => $documento->id,
            ]));
    }
}
