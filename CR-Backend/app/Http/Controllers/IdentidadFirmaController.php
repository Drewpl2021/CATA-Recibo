<?php
namespace App\Http\Controllers;
use App\Models\Empleado;
use App\Models\IdentidadFirma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IdentidadFirmaController extends Controller
{
    /**
     * RRHH/admin registra o actualiza la firma y/o huella de cualquier empleado.
     */
    public function subir(Request $request, string $empleado_id)
    {
        $empleado = Empleado::findOrFail($empleado_id);
        return $this->guardar($request, $empleado);
    }

    /**
     * Autoservicio: el propio empleado registra o actualiza su firma y/o huella.
     */
    public function subirMia(Request $request)
    {
        $empleado_id = $request->user()->empleado_id;
        if (!$empleado_id) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Tu usuario no tiene empleado vinculado.'],
            ], 403);
        }

        $empleado = Empleado::findOrFail($empleado_id);
        return $this->guardar($request, $empleado);
    }

    /**
     * Guarda las imágenes en el disco privado "local" (storage/app/private) — una
     * huella dactilar es dato sensible (Ley N° 29733), así que nunca debe quedar
     * accesible por una URL pública. Se puede mandar solo firma, solo huella, o
     * ambas juntas; lo que no se manda en esta llamada queda como estaba.
     */
    private function guardar(Request $request, Empleado $empleado): \Illuminate\Http\JsonResponse
    {
        // Ojo: "sometimes" no puede ir junto a "required_without" en el mismo campo —
        // "sometimes" hace que Laravel salte TODAS las reglas del campo (incluido
        // required_without) cuando no viene en la petición, así que una petición
        // totalmente vacía pasaba la validación sin quejarse. Sin "sometimes",
        // Laravel ya de por sí no exige image/mimes en el campo que falta si el
        // otro sí vino (deja de ser "requerido" y sus demás reglas no se evalúan).
        $request->validate([
            'firma'  => 'required_without:huella|image|mimes:png,jpg,jpeg|max:2048',
            'huella' => 'required_without:firma|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        $datos = ['registrado_por' => $request->user()->id];

        if ($request->hasFile('firma')) {
            $extension = $request->file('firma')->getClientOriginalExtension();
            $ruta      = "identidad-firma/{$empleado->id}/firma.{$extension}";
            Storage::disk('local')->put($ruta, file_get_contents($request->file('firma')->getRealPath()));
            $datos['firma_imagen'] = $ruta;
        }

        if ($request->hasFile('huella')) {
            $extension = $request->file('huella')->getClientOriginalExtension();
            $ruta      = "identidad-firma/{$empleado->id}/huella.{$extension}";
            Storage::disk('local')->put($ruta, file_get_contents($request->file('huella')->getRealPath()));
            $datos['huella_imagen'] = $ruta;
        }

        $identidad = IdentidadFirma::updateOrCreate(['empleado_id' => $empleado->id], $datos);

        return response()->json(['success' => true, 'data' => $identidad]);
    }
}
