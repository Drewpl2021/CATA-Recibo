<?php
namespace App\Http\Controllers;
use App\Models\Documento;
use App\Models\Planilla;
use App\Support\ExpedienteDigital;
use App\Traits\ListadoPaginado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

class MisDocumentosController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /mis-documentos?tipo=&anio=&sin_firmar=&page=&size=&search=
     *
     * Los documentos del trabajador de la sesión. El empleado sale del token,
     * nunca de la petición.
     *
     * Con el tiempo esto crece sin parar —una boleta por mes, más contratos y
     * constancias—, así que va paginado como el resto: sin ?page devolvía la
     * carrera entera del trabajador de una sola vez.
     *
     * Los tres filtros son los que piden las tres pantallas que cuelgan de
     * aquí, y cada uno ahorra traer documentos que no se van a pintar:
     *
     *   Mis Boletas   -> ?tipo=boleta&anio=2026  (la pantalla ya elige el año)
     *   Mis Documentos-> sin filtro, todo lo suyo
     *   La campanita  -> ?sin_firmar=1           (solo lo que le falta firmar)
     */
    public function index(Request $request)
    {
        $empleado_id = $request->user()->empleado_id;

        // Una cuenta sin ficha —las de Administración y RR.HH., que son para
        // operar el sistema— simplemente no tiene documentos, que no es lo
        // mismo que no tener permiso. Antes acá salía un 403 y la campana de
        // la barra de arriba fallaba en CADA pantalla del administrador.
        // Ahora devuelve la lista vacía, que es la verdad.

        // Ordenado por el periodo que representa, no por cuándo se registró.
        //
        // Un documento se ordena por lo que dice ser: la boleta de agosto va
        // después de la de julio aunque las dos se hayan emitido el mismo día
        // —que es justo lo que pasa cuando se emiten todas de golpe, y ahí
        // created_at no desempata nada—. Lo que no tiene planilla (contratos,
        // constancias) cae al final por su fecha, que es lo único que tiene.
        // empleado_id lleva su tabla delante porque con el join lo tienen las
        // dos. Las demás columnas de aquí abajo (tipo, estado_firma) son solo
        // de documentos, y van sin calificar a propósito: el punto en
        // ListadoPaginado significa "relación", no "tabla".
        // Se trae la sede y la cuenta para poder pintar la "Entidad" y los
        // datos de contacto de la fila sin una consulta por boleta.
        $query = Documento::whereRaw($empleado_id ? '1 = 1' : '1 = 0')
            ->where('documentos.empleado_id', $empleado_id)
            ->with(['planilla', 'empleado:id,nombre,apellido,telefono,sede_id', 'empleado.sede:id,nombre', 'empleado.usuario:id,empleado_id,email'])
            ->leftJoin('planilla', 'documentos.planilla_id', '=', 'planilla.id')
            ->select('documentos.*')
            // Un archivo anterior no tiene planilla: su periodo es el suyo.
            ->orderByRaw('COALESCE(planilla.anio, documentos.periodo_anio) DESC')
            ->orderByRaw('COALESCE(planilla.mes, documentos.periodo_mes) DESC')
            ->orderByDesc('documentos.created_at');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        // El año es el de la planilla, no el de created_at: una boleta de
        // diciembre emitida en enero pertenece al año que se trabajó.
        if ($request->filled('anio')) {
            $query->whereRaw('COALESCE(planilla.anio, documentos.periodo_anio) = ?', [(int) $request->input('anio')]);
        }

        // "Sin firmar" es lo que le falta firmar: la hoja de vida no se firma,
        // así que no tiene nada que hacer en la campana.
        if ($request->boolean('sin_firmar')) {
            $query->where('estado_firma', '!=', 'firmado')
                ->whereNotIn('tipo', ExpedienteDigital::SIN_FIRMA);
        }

        return $this->responderListado(
            $request,
            $query,
            ['tipo'],
            // Cuántos le faltan por firmar: es el número del recuadro de
            // arriba, y tiene que contarse sobre TODOS, no sobre la página.
            // Solo sobre lo que se firma: su CV no es un pendiente.
            fn (Builder $suyos) => $this->conteoPorEstado((clone $suyos)->whereNotIn('tipo', ExpedienteDigital::SIN_FIRMA), 'estado_firma', [
                'pendientes' => 'pendiente',
                'firmados'   => 'firmado',
            ])
        );
    }

    public function visto(Request $request, $id)
    {
        $empleado_id = $request->user()->empleado_id;
        $documento = Documento::where('id', $id)
            ->where('empleado_id', $empleado_id)
            ->firstOrFail();

        if (! ExpedienteDigital::seFirma($documento->tipo)) {
            return response()->json([
                'success' => false,
                'message' => ExpedienteDigital::porQueNoSeFirma($documento->tipo),
            ], 422);
        }

        if ($documento->estado_firma !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede marcar como visto si está pendiente.'
            ], 422);
        }

        $documento->update([
            'estado_firma' => 'visto',
            'fecha_visto'  => now(),
        ]);

        return response()->json(['success' => true, 'data' => $documento]);
    }

    public function firmar(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user        = $request->user();
        $empleado_id = $user->empleado_id;
        $intentosKey = 'intentos_firma_' . $user->id;
        $intentos    = Cache::get($intentosKey, 0);

        if (!Hash::check($request->password, $user->password)) {
            $intentos++;
            Cache::put($intentosKey, $intentos, now()->addMinutes(15));

            if ($intentos >= 3) {
                // Cierra la sesión tras el tercer intento fallido
                $user->tokens()->delete();
                Cache::forget($intentosKey);

                return response()->json([
                    'success' => false,
                    'message' => 'Se superó el límite de 3 intentos. Su sesión ha sido cerrada.'
                ], 403);
            }

            $intentosRestantes = 3 - $intentos;
            return response()->json([
                'success' => false,
                'message' => "Contraseña incorrecta. Le quedan {$intentosRestantes} intento(s).",
            ], 401);
        }

        // Contraseña correcta: reinicia el contador
        Cache::forget($intentosKey);

        $documento = Documento::where('id', $id)
            ->where('empleado_id', $empleado_id)
            ->firstOrFail();

        // Se revisa aquí y no solo escondiendo el botón: una pantalla no es
        // una cerradura.
        if (! ExpedienteDigital::seFirma($documento->tipo)) {
            return response()->json([
                'success' => false,
                'message' => ExpedienteDigital::porQueNoSeFirma($documento->tipo),
            ], 422);
        }

        if ($documento->estado_firma === 'firmado') {
            return response()->json([
                'success' => false,
                'message' => 'El documento ya está firmado.'
            ], 422);
        }

        $empleado = $user->empleado;
        $nombre_completo = $empleado->nombre . ' ' . $empleado->apellido;

        $documento->update([
            'estado_firma' => 'firmado',
            'fecha_firma'  => now(),
            'firmado_por'  => $nombre_completo,
            'codigo_firma' => strtoupper(Str::random(8)) . '-' . time(),
        ]);

        // Si es una boleta, se vuelve a armar el PDF ahora que ya quedó firmada —
        // así el archivo congelado en disco sí incluye el sello de firma+huella y
        // el texto de verificación (si no se regenerara aquí, quedaría archivada
        // para siempre la versión de ANTES de firmar).
        if ($documento->tipo === 'boleta' && $documento->planilla_id) {
            $planilla = Planilla::find($documento->planilla_id);
            if ($planilla) {
                app(BoletaController::class)->construirBoleta(
                    $empleado->load('area', 'cargo', 'identidadFirma'),
                    $planilla,
                    (int) $planilla->mes,
                    (int) $planilla->anio,
                    forzarGuardado: true
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Documento firmado correctamente.',
            'data'    => $documento
        ]);
    }

    /**
     * POST /mis-documentos/hoja-de-vida — el trabajador sube su propio CV.
     *
     * El empleado sale del token, nunca del cuerpo: sin eso cualquiera podía
     * colgarle un archivo al expediente de otro. Reemplaza al CV que tuviera
     * (el anterior queda dado de baja, como cuando lo sube RR.HH.).
     */
    public function subirHojaDeVida(Request $request)
    {
        // Sin `tipo` es la hoja de vida, como antes: la ruta vieja
        // (my-documents/resume) sigue sirviendo igual.
        $tipo = $request->input('tipo', ExpedienteDigital::HOJA_DE_VIDA);

        $request->validate([
            'archivo' => ExpedienteDigital::REGLA_ARCHIVO,
            // Solo los suyos: una boleta o un contrato los emite el colegio,
            // y dejar que se los suba él mismo sería dejarle escribir su
            // propia boleta.
            'tipo'    => ['nullable', Rule::in(ExpedienteDigital::PERSONALES)],
        ], [
            'archivo.required' => 'Elige el archivo que quieres subir.',
            'archivo.mimes'    => 'El archivo tiene que ser PDF, Word o una imagen (JPG o PNG).',
            'archivo.max'      => 'El archivo pasa de 5 MB.',
            'tipo.in'          => 'Ese tipo de documento no lo subes tú: lo emite el colegio.',
        ]);

        $empleadoId = $request->user()->empleado_id;
        if (! $empleadoId) {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta no está vinculada a una ficha de trabajador.',
            ], 403);
        }

        $documento = ExpedienteDigital::guardar($empleadoId, $tipo, $request->file('archivo'));

        return response()->json(['success' => true, 'data' => $documento], 201);
    }
    
}