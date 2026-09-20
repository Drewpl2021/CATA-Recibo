<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Support\AccesoADocumento;
use App\Support\ExpedienteDigital;
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
        // Igual que en el autoservicio: la sede y la cuenta van eager-loaded
        // para la columna "Entidad" y los datos de contacto del seguimiento.
        $query = Documento::with([
            'empleado', 'contrato', 'planilla',
            'empleado.sede:id,nombre', 'empleado.usuario:id,empleado_id,email',
        ]);

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
            // El tipo va por igualdad ("="): son cuatro palabras fijas, y un
            // like '%apellido%' sobre ellas recorría las 46 000 boletas. De
            // 246 ms a 17 buscando un apellido.
            ['empleado.nombre', 'empleado.apellido', 'empleado.dni', '=tipo']
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

    /**
     * POST /documentos/subir — adjunta un archivo de verdad.
     *
     * store() registra un documento que YA está en disco: es para la boleta
     * que genera el propio sistema, y por eso recibe una ruta y no un
     * archivo. Esto es lo contrario, y hasta ahora no existía: el papel que
     * llega de fuera —la hoja de vida del postulante, el contrato escaneado
     * y firmado— que hay que subir y guardar.
     *
     * Va al disco privado "local" (storage/app/private), igual que las
     * boletas y la huella: una hoja de vida trae DNI, domicilio y teléfono,
     * y no puede quedar colgando de una URL pública.
     */
    public function subir(Request $request)
    {
        $datos = $request->validate([
            'empleado_id' => 'required|uuid|exists:empleados,id',
            'contrato_id' => 'nullable|uuid|exists:contratos,id',
            'tipo'        => 'required|in:boleta,contrato,cts,vacaciones_truncas,comprobante_transferencia,hoja_de_vida,otro',
            // Word entra a propósito: la mitad de las hojas de vida llegan
            // en .docx y obligar a convertirlas a PDF es trabajo que RR.HH.
            // acabaría haciendo a mano.
            'archivo'     => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
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

        // El nombre del archivo, la carpeta y el reemplazo de la hoja de vida
        // anterior viven en ExpedienteDigital: lo usan también el propio
        // trabajador (Mis Documentos) y la importación de empleados.
        $documento = ExpedienteDigital::guardar(
            $datos['empleado_id'],
            $datos['tipo'],
            $request->file('archivo'),
            array_filter([
                'contrato_id' => $datos['contrato_id'] ?? null,
                'firmado_por' => $datos['firmado_por'] ?? null,
            ])
        );

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
    /**
     * GET documentos/{id}/ver — abrirlo en pantalla, sin bajarlo.
     *
     * Va por su propia ruta y no con un parámetro en la descarga porque son
     * dos cosas distintas: esta se permite siempre (para poder leer antes de
     * firmar) y deja anotado que lo revisó; la otra pide la firma primero.
     */
    public function ver(Request $request, string $id)
    {
        $documento = Documento::findOrFail($id);
        $usuario   = $request->user();

        if (! AccesoADocumento::puedeVer($documento, $usuario)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para ver este documento.',
            ], 403);
        }

        if ($problema = $this->archivoQueFalta($documento)) {
            return $problema;
        }

        AccesoADocumento::marcarVisto($documento, $usuario);

        return Storage::disk('local')->response($documento->archivo);
    }

    public function descargar(Request $request, string $id)
    {
        $documento = Documento::findOrFail($id);
        $usuario   = $request->user();

        // Su dueño se lleva el PDF después de firmarlo; RR.HH. y
        // Administración, siempre. Ver AccesoADocumento.
        if ($motivo = AccesoADocumento::porQueNoPuedeDescargar($documento, $usuario)) {
            return response()->json(['success' => false, 'message' => $motivo], 403);
        }

        if ($problema = $this->archivoQueFalta($documento)) {
            return $problema;
        }

        if (AccesoADocumento::esSuyo($documento, $usuario)) {
            // Es SU descarga: que RR.HH. abra el PDF no significa que el
            // trabajador lo haya recibido.
            $documento->registrarDescarga();
        }

        return Storage::disk('local')->download($documento->archivo, basename($documento->archivo));
    }

    /** La respuesta de "todavía no hay archivo"; null si sí está. */
    private function archivoQueFalta(Documento $documento)
    {
        if ($documento->archivo && Storage::disk('local')->exists($documento->archivo)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'El archivo de este documento aún no está disponible. Vuelve a generarlo.',
        ], 404);
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