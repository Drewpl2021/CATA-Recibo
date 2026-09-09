<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\Planilla;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Traits\ListadoPaginado;

class DocumentoController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /documentos?empleado_id=&contrato_id=&tipo=&page=&size=&search=
     */
    public function index(Request $request)
    {
        $query = Documento::with('empleado', 'contrato');

        if ($request->filled('empleado_id')) {
            $query->where('empleado_id', $request->empleado_id);
        }

        if ($request->filled('contrato_id')) {
            $query->where('contrato_id', $request->contrato_id);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $rolNombre = $request->user()->rol?->nombre;
        if ($rolNombre !== 'rrhh') {
            $query->where('estado_registro', 'activo');
        }

        return $this->responderListado(
            $request,
            $query->orderBy('created_at', 'desc'),
            ['empleado.nombre', 'empleado.apellido', 'empleado.dni', 'tipo']
        );
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'empleado_id' => 'required|exists:empleados,id',
            'contrato_id' => 'nullable|uuid|exists:contratos,id',
            'tipo' => 'required|in:boleta,contrato,cts,vacaciones_truncas,comprobante_transferencia,hoja_de_vida,otro',
            // Ruta relativa dentro de storage y nada más: sin "..", sin
            // ruta absoluta y sin barras invertidas. Flysystem ya frena la
            // travesía de directorios al descargar, pero aceptar la cadena
            // dejaba el documento apuntando a un archivo que no existe y roto
            // para siempre.
            'archivo'     => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+$/', 'not_regex:/\.\./'],
            'firmado_por' => 'nullable|string|max:120',
        ]);

        if ($request->filled('contrato_id')) {
            $contrato = Contrato::findOrFail($request->contrato_id);
            if ($contrato->empleado_id !== $request->empleado_id) {
                throw ValidationException::withMessages([
                    'contrato_id' => ['El contrato indicado no pertenece a este empleado.'],
                ]);
            }
        }

        // Solo lo validado, y el estado de firma lo pone el sistema.
        //
        // Antes esto era `$request->all()` más un código y una fecha de firma
        // puestos al crear. Dos problemas de golpe:
        //
        //  1. Se podía pasar "estado_firma":"firmado" en el cuerpo y nacía un
        //     documento ya firmado a nombre del trabajador, sin su contraseña.
        //     Es el mismo agujero que tenía el update, por la otra puerta.
        //  2. Un documento recién registrado llevaba código y fecha de firma
        //     sin que nadie hubiera firmado nada. Esos dos datos los genera el
        //     acto de firmar (MisDocumentosController::firmar para el
        //     trabajador, firmarComoEmpleador para el colegio), y de hecho los
        //     sobrescribían — el de aquí no servía para nada.
        //
        // `firmado_por` sí se admite: en un documento que se registra a mano
        // (un contrato en papel, una hoja de vida) es el nombre de quien lo
        // firmó de puño y letra, no la firma digital del sistema.
        $documento = Documento::create(array_merge($datos, [
            'estado_firma' => 'pendiente',
        ]));

        return response()->json(['success' => true, 'data' => $documento], 201);
    }

    public function show(string $id)
    {
        $documento = Documento::with('empleado')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $documento]);
    }

    /**
     * PUT /documentos/{id} — corregir el tipo o el archivo de un documento.
     *
     * Acá NO se toca nada de la firma, y es lo más importante de este método.
     *
     * Antes hacía `update($request->all())` validando solo tres campos, pero
     * `estado_firma`, `firmado_por`, `codigo_firma` y `fecha_firma` son todos
     * asignables. Probado: bastaba un PUT con
     *
     *     {"estado_firma":"firmado","firmado_por":"Elena Chávez",
     *      "codigo_firma":"…","fecha_firma":"…"}
     *
     * para dejar la boleta de un trabajador marcada como firmada por él —sin
     * su contraseña— y devolvía 200. Eso vacía de sentido la firma entera: es
     * justamente la prueba de que el trabajador vio y aceptó su boleta.
     *
     * Una firma solo se pone por sus dos puertas, y las dos piden contraseña:
     * POST mis-documentos/{id}/firmar (el trabajador) y
     * POST documentos/{id}/firmar-empleador (el colegio).
     */
    public function update(Request $request, string $id)
    {
        $documento = Documento::findOrFail($id);

        $datos = $request->validate([
            'tipo'    => 'sometimes|in:boleta,contrato,cts,vacaciones_truncas,comprobante_transferencia,hoja_de_vida,otro',
            'archivo' => ['sometimes', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+$/', 'not_regex:/\.\./'],
        ]);

        $documento->update($datos);

        return response()->json(['success' => true, 'data' => $documento]);
    }

    public function destroy(string $id)
    {
        $documento = Documento::findOrFail($id);
        $documento->update(['estado_registro' => 'inactivo']);

        return response()->json(['success' => true, 'data' => ['message' => 'Documento eliminado correctamente.']]);
    }

    /**
     * Descarga el archivo físico (PDF) de un Documento ya generado.
     * Se guarda en el disco privado "local" (storage/app/private) — nunca en
     * el disco "public", porque una boleta trae sueldo, DNI y cuenta bancaria.
     * Acceso: RRHH/admin sobre cualquier documento, o el empleado dueño sobre el suyo.
     */
    public function descargar(Request $request, string $id)
    {
        $documento = Documento::findOrFail($id);
        $user = $request->user();
        $esRrhhOAdmin = in_array($user->rol?->nombre, ['rrhh', 'admin'], true);

        if (!$esRrhhOAdmin && $documento->empleado_id !== $user->empleado_id) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'No tienes permiso para descargar este documento.'],
            ], 403);
        }

        if (!$documento->archivo || !Storage::disk('local')->exists($documento->archivo)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'El archivo de este documento aún no está disponible. Vuelve a generarlo.'],
            ], 404);
        }

        return Storage::disk('local')->download($documento->archivo, basename($documento->archivo));
    }

    /**
     * El lado "Firma Empleador" de un Documento — lo firma un RRHH/admin en
     * representación de la institución, con el mismo respaldo de contraseña
     * (y bloqueo a los 3 intentos) que usa el empleado para su propio lado.
     * RRHH también es un Empleado en este sistema, así que su firma/huella
     * salen de la misma tabla identidades_firma.
     */
    public function firmarComoEmpleador(Request $request, string $id)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user        = $request->user();
        $empleado_id = $user->empleado_id;
        if (!$empleado_id) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Tu usuario no tiene empleado vinculado.'],
            ], 403);
        }

        $intentosKey = 'intentos_firma_empleador_' . $user->id;
        $intentos    = Cache::get($intentosKey, 0);

        if (!Hash::check($request->password, $user->password)) {
            $intentos++;
            Cache::put($intentosKey, $intentos, now()->addMinutes(15));

            if ($intentos >= 3) {
                $user->tokens()->delete();
                Cache::forget($intentosKey);

                return response()->json([
                    'success' => false,
                    'data'    => ['message' => 'Se superó el límite de 3 intentos. Su sesión ha sido cerrada.'],
                ], 403);
            }

            $intentosRestantes = 3 - $intentos;
            return response()->json([
                'success' => false,
                'data'    => ['message' => "Contraseña incorrecta. Le quedan {$intentosRestantes} intento(s)."],
            ], 401);
        }

        Cache::forget($intentosKey);

        $documento = Documento::findOrFail($id);

        if ($documento->estado_firma_empleador === 'firmado') {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'El lado del empleador ya está firmado.'],
            ], 422);
        }

        $empleadoFirmante = Empleado::findOrFail($empleado_id);

        $documento->update([
            'empleador_id'           => $empleadoFirmante->id,
            'estado_firma_empleador' => 'firmado',
            'fecha_firma_empleador'  => now(),
            'firmado_por_empleador'  => $empleadoFirmante->nombre . ' ' . $empleadoFirmante->apellido,
            'codigo_firma_empleador' => strtoupper(Str::random(8)) . '-' . time(),
        ]);

        // Si es una boleta, se vuelve a armar el PDF ahora que ya quedó firmada por
        // el empleador — mismo mecanismo que usa MisDocumentosController::firmar()
        // para el lado del trabajador, así el archivo congelado en disco incluye
        // también este sello.
        if ($documento->tipo === 'boleta' && $documento->planilla_id) {
            $planilla = Planilla::find($documento->planilla_id);
            $empleadoTitular = Empleado::with('area', 'cargo', 'identidadFirma')->find($documento->empleado_id);
            if ($planilla && $empleadoTitular) {
                app(BoletaController::class)->construirBoleta(
                    $empleadoTitular,
                    $planilla,
                    (int) $planilla->mes,
                    (int) $planilla->anio,
                    forzarGuardado: true
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Documento firmado como empleador correctamente.',
            'data'    => $documento,
        ]);
    }
}