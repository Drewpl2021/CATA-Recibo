<?php
namespace App\Http\Controllers;
use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MisDocumentosController extends Controller
{
    public function index(Request $request)
    {
        $empleado_id = $request->user()->empleado_id;
        if (!$empleado_id) {
            return response()->json(['success' => false, 'message' => 'Sin empleado vinculado.'], 403);
        }

        $documentos = Documento::where('empleado_id', $empleado_id)
            ->with('planilla')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $documentos]);
    }

    public function visto(Request $request, $id)
    {
        $empleado_id = $request->user()->empleado_id;
        $documento = Documento::where('id', $id)
            ->where('empleado_id', $empleado_id)
            ->firstOrFail();

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
        $user        = $request->user();
        $empleado_id = $user->empleado_id;
        $documento   = Documento::where('id', $id)
            ->where('empleado_id', $empleado_id)
            ->firstOrFail();

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

        return response()->json(['success' => true, 'data' => $documento]);
    }
}