<?php
namespace App\Http\Controllers;
use App\Models\Empleado;
use Illuminate\Http\Request;

class EmpleadoController extends Controller
{
    public function index()
    {
        $empleados = Empleado::with('area', 'cargo', 'sede')->get();
        return response()->json(['success' => true, 'data' => $empleados]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dni'          => 'required|string|regex:/^[0-9]{8}$/|unique:empleados',
            'nombre'       => 'required|string|max:100',
            'apellido'     => 'required|string|max:100',
            'cargo_id'     => 'required|uuid|exists:cargos,id',
            'area_id'      => 'required|uuid|exists:areas,id',
            'telefono'     => 'nullable|string|max:15',
            'direccion'    => 'nullable|string|max:255',
            'fecha_ingreso'=> 'required|date|before_or_equal:today',
            'estado'       => 'nullable|string|max:20',
            'sistema_pensiones'  => 'nullable|in:AFP,ONP',
            'afp'                => 'nullable|in:Habitat,Integra,Prima,Profuturo|required_if:sistema_pensiones,AFP',
            'cuspp'              => 'nullable|string|size:11',
            'entidad_financiera' => 'nullable|string|max:100',
            'numero_cuenta'      => 'nullable|string|max:50',
            'tiene_hijos'        => 'nullable|boolean',
            'sueldo_base'   => 'nullable|numeric|min:0',
            'tipo_contrato' => 'nullable|in:por_hora,necesidad_servicio,indeterminado',
            'forma_pago'    => 'nullable|in:banco,efectivo,otro',
            'sede_id'       => 'nullable|uuid|exists:sedes,id',
        ]);

        $empleado = Empleado::create($request->all());
        $empleado->load('area', 'cargo', 'sede');
        return response()->json(['success' => true, 'data' => $empleado], 201);
    }

    public function show(string $id)
    {
        $empleado = Empleado::with('area', 'cargo', 'sede')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $empleado]);
    }

    public function update(Request $request, string $id)
    {
        $empleado = Empleado::findOrFail($id);
        $request->validate([
            'dni'          => 'sometimes|string|regex:/^[0-9]{8}$/|unique:empleados,dni,'.$id,
            'nombre'       => 'sometimes|string|max:100',
            'apellido'     => 'sometimes|string|max:100',
            'cargo_id'     => 'sometimes|uuid|exists:cargos,id',
            'area_id'      => 'sometimes|uuid|exists:areas,id',
            'telefono'     => 'nullable|string|max:15',
            'direccion'    => 'nullable|string|max:255',
            'fecha_ingreso'=> 'sometimes|date|before_or_equal:today',
            'estado'       => 'nullable|string|max:20',
            'sistema_pensiones'  => 'sometimes|in:AFP,ONP',
            'afp'                => 'nullable|in:Habitat,Integra,Prima,Profuturo',
            'cuspp'              => 'nullable|string|size:11',
            'entidad_financiera' => 'nullable|string|max:100',
            'numero_cuenta'      => 'nullable|string|max:50',
            'tiene_hijos'        => 'nullable|boolean',
            'sueldo_base'   => 'nullable|numeric|min:0',
            'tipo_contrato' => 'nullable|in:por_hora,necesidad_servicio,indeterminado',
            'forma_pago'    => 'nullable|in:banco,efectivo,otro',
            'sede_id'       => 'nullable|uuid|exists:sedes,id',
        ]);

        $empleado->update($request->all());
        $empleado->load('area', 'cargo', 'sede');
        return response()->json(['success' => true, 'data' => $empleado]);
    }

    public function destroy(string $id)
    {
        $empleado = Empleado::findOrFail($id);
        $empleado->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Empleado eliminado correctamente.']]);
    }
            public function subirFirma(Request $request, string $id)
    {
        $empleado = Empleado::findOrFail($id);

        $request->validate([
            'firma' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        $archivo = $request->file('firma');
        $extension = $archivo->getClientOriginalExtension();
        $nombre = 'firma_' . $empleado->dni . '.' . $extension;
        
        $destino = storage_path('app/public/firmas');
        $archivo->move($destino, $nombre);

        $empleado->update(['firma_imagen' => 'firmas/' . $nombre]);

        return response()->json([
            'success' => true,
            'data'    => ['firma_imagen' => 'firmas/' . $nombre]
        ]);
    }
}