<?php
namespace App\Http\Controllers;
use App\Models\Empleado;
use App\Models\User;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
            'dni'                => 'required|string|regex:/^[0-9]{8}$/|unique:empleados',
            'nombre'             => 'required|string|max:100',
            'apellido'           => 'required|string|max:100',
            'cargo_id'           => 'required|uuid|exists:cargos,id',
            'area_id'            => 'nullable|uuid|exists:areas,id',
            'telefono'           => 'nullable|regex:/^[0-9]+$/|max:15',
            'direccion'          => 'nullable|string|max:255',
            'fecha_ingreso'      => 'required|date|before_or_equal:today',
            'estado'             => 'nullable|string|max:20',
            'sistema_pensiones'  => 'nullable|in:AFP,ONP',
            'afp'                => 'nullable|in:Habitat,Integra,Prima,Profuturo|required_if:sistema_pensiones,AFP',
            'cuspp'              => 'nullable|regex:/^[0-9]{11}$/|required_if:sistema_pensiones,AFP',
            'entidad_financiera' => 'nullable|string|max:100',
            'numero_cuenta'      => 'nullable|string|max:50',
            'tiene_hijos'        => 'nullable|boolean',
            'sueldo_base'        => 'nullable|numeric|min:0',
            'tipo_contrato'      => 'nullable|in:por_hora,necesidad_servicio,indeterminado',
            'forma_pago'         => 'nullable|in:banco,efectivo,otro',
            'sede_id'            => 'nullable|uuid|exists:sedes,id',
            'email'              => 'required|email|unique:users,email',
            'rol_id'             => 'required|uuid|exists:roles,id',
            'nivel_estudios'       => 'nullable|in:primaria,secundaria,tecnico,universitario,maestria,doctorado',
            'especialidad'         => 'nullable|string|max:150',
            'institucion_estudios' => 'nullable|string|max:150',
            'contacto_emergencia_nombre'    => 'nullable|string|max:150',
            'contacto_emergencia_telefono'  => 'nullable|regex:/^[0-9]+$/|max:15',
            'fecha_nacimiento'              => 'nullable|date|before:today',
        ]);

        // Crear empleado
        $empleado = Empleado::create($request->except(['email', 'rol_id']));

        // Crear usuario automáticamente
        User::create([
            'name'        => $empleado->nombre . ' ' . $empleado->apellido,
            'email'       => $request->email,
            'password'    => Hash::make($request->dni),
            'rol_id'      => $request->rol_id,
            'empleado_id' => $empleado->id,
        ]);

        $empleado->load('area', 'cargo', 'sede');
        return response()->json([
            'success' => true,
            'data'    => $empleado,
            'mensaje' => 'Empleado creado. Usuario generado con contraseña: DNI del empleado.'
        ], 201);
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
            'dni'                => 'sometimes|string|regex:/^[0-9]{8}$/|unique:empleados,dni,'.$id,
            'nombre'             => 'sometimes|string|max:100',
            'apellido'           => 'sometimes|string|max:100',
            'cargo_id'           => 'sometimes|uuid|exists:cargos,id',
            'area_id'            => 'nullable|uuid|exists:areas,id',
            'telefono'           => 'nullable|regex:/^[0-9]+$/|max:15',
            'direccion'          => 'nullable|string|max:255',
            'fecha_ingreso'      => 'sometimes|date|before_or_equal:today',
            'estado'             => 'nullable|string|max:20',
            'sistema_pensiones'  => 'sometimes|in:AFP,ONP',
            'afp'                => 'nullable|in:Habitat,Integra,Prima,Profuturo',
            'cuspp'              => 'nullable|regex:/^[0-9]{11}$/|required_if:sistema_pensiones,AFP',
            'entidad_financiera' => 'nullable|string|max:100',
            'numero_cuenta'      => 'nullable|string|max:50',
            'tiene_hijos'        => 'nullable|boolean',
            'sueldo_base'        => 'nullable|numeric|min:0',
            'tipo_contrato'      => 'nullable|in:por_hora,necesidad_servicio,indeterminado',
            'forma_pago'         => 'nullable|in:banco,efectivo,otro',
            'sede_id'            => 'nullable|uuid|exists:sedes,id',
            'nivel_estudios'       => 'nullable|in:primaria,secundaria,tecnico,universitario,maestria,doctorado',
            'especialidad'         => 'nullable|string|max:150',
            'institucion_estudios' => 'nullable|string|max:150',
            'contacto_emergencia_nombre'    => 'nullable|string|max:150',
            'contacto_emergencia_telefono'  => 'nullable|regex:/^[0-9]+$/|max:15',
            'fecha_nacimiento'              => 'nullable|date|before:today',
        ]);

        $empleado->update($request->except(['email', 'rol_id']));
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

        $archivo   = $request->file('firma');
        $extension = $archivo->getClientOriginalExtension();
        $nombre    = 'firma_' . $empleado->dni . '.' . $extension;
        $destino   = storage_path('app/public/firmas');
        $archivo->move($destino, $nombre);

        $empleado->update(['firma_imagen' => 'firmas/' . $nombre]);

        return response()->json([
            'success' => true,
            'data'    => ['firma_imagen' => 'firmas/' . $nombre]
        ]);
    }
}