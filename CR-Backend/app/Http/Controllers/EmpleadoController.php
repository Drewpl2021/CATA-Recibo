<?php
namespace App\Http\Controllers;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\User;
use App\Models\Rol;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Traits\ListadoPaginado;

class EmpleadoController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /empleados?page=&size=&search=&formato=selector
     *
     * Sin ?page devuelve la plantilla completa: así la piden el selector de
     * empleados y los desplegables de los formularios. Con ?page la corta el
     * servidor, que es lo que necesita la tabla.
     *
     * `formato=selector` devuelve lo mínimo para pintar un desplegable —id,
     * nombre, apellido y DNI— sin arrastrar área, cargo, sede, usuario ni la
     * firma. Y no es un detalle: la ficha completa de 150 empleados pesa
     * 297 KB, y NUEVE pantallas se la bajaban entera solo para llenar un
     * <select>. Con este formato son ~10 KB, y no crecen con los datos que se
     * le vayan agregando a la ficha.
     */
    public function index(Request $request)
    {
        $request->validate(['formato' => 'nullable|in:completo,selector']);

        $paraSelector = $request->input('formato') === 'selector';

        $query = $paraSelector
            ? Empleado::query()->select('id', 'nombre', 'apellido', 'dni', 'estado', 'area_id', 'cargo_id', 'sede_id')
            : Empleado::with('area', 'cargo', 'sede', 'usuario', 'identidadFirma');

        return $this->responderListado(
            $request,
            $query->orderBy('apellido')
                ->orderBy('nombre'),
            // el correo no vive en empleados sino en users, por eso va por la relacion
            ['nombre', 'apellido', 'dni', 'usuario.email'],
            // Las cifras de la cabecera: se cuentan sobre todo lo que pasa el
            // filtro, no sobre la página que se está viendo.
            fn (Builder $filtrada) => $this->conteoPorEstado($filtrada, 'estado', ['activos' => 'activo', 'inactivos' => 'inactivo'])
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'dni'                => 'required|string|regex:/^[0-9]{8}$/|unique:empleados',
            'nombre'             => 'required|string|max:100',
            'apellido'           => 'required|string|max:100',
            'cargo_id'           => 'required|uuid|exists:cargos,id',
            'area_id'            => 'required|uuid|exists:areas,id',
            'telefono'           => 'required|regex:/^[0-9]+$/|max:15',
            'direccion'          => 'required|string|max:255',
            'fecha_ingreso'      => 'required|date|before_or_equal:today',
            'estado'             => 'nullable|string|max:20',
            'sistema_pensiones'  => 'nullable|in:AFP,ONP',
            'afp'                => 'nullable|in:Habitat,Integra,Prima,Profuturo|required_if:sistema_pensiones,AFP',
            'cuspp'              => 'nullable|regex:/^[0-9]{11}$/|required_if:sistema_pensiones,AFP',
            'entidad_financiera' => 'nullable|string|max:100',
            'numero_cuenta'      => 'nullable|string|max:50',
            'tiene_hijos'        => 'nullable|boolean',
            'sueldo_base'        => 'required|numeric|min:0',
            'tipo_contrato'      => 'required|in:indeterminado,plazo_fijo,suplencia,practicas',
            // Un plazo fijo, una suplencia o unas prácticas SIN fecha de término no
            // son un contrato: hay que saber cuándo acaba. El indeterminado es el
            // único que no lleva fin, y ahí el campo sobra.
            'fecha_fin_contrato' => 'required_unless:tipo_contrato,indeterminado|nullable|date|after:fecha_ingreso',
            'forma_pago'         => 'nullable|in:banco,efectivo,otro',
            'sede_id'            => 'required|uuid|exists:sedes,id',
            'email'              => 'required|email|unique:users,email',
            'rol_id'             => 'required|uuid|exists:roles,id',
            'nivel_estudios'       => 'nullable|in:primaria,secundaria,tecnico,universitario,maestria,doctorado',
            'especialidad'         => 'nullable|string|max:150',
            'institucion_estudios' => 'nullable|string|max:150',
            'contacto_emergencia_nombre'    => 'nullable|string|max:150',
            'contacto_emergencia_telefono'  => 'nullable|regex:/^[0-9]+$/|max:15',
            'fecha_nacimiento'              => 'required|date|before:today',
        ]);

        $rolAsignado = Rol::findOrFail($request->rol_id);
        if ($rolAsignado->nombre === 'admin' && $request->user()->rol?->nombre !== 'admin') {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Solo un Administrador puede asignar el rol de Administrador.'],
            ], 403);
        }

        // Las tres cosas nacen juntas o no nace ninguna: un empleado sin usuario
        // no puede entrar, y uno sin contrato queda con el historial en blanco
        // aunque su ficha diga "plazo fijo".
        $empleado = DB::transaction(function () use ($request) {
            $empleado = Empleado::create($request->except(['email', 'rol_id', 'fecha_fin_contrato']));

            User::create([
                'name'        => $empleado->nombre . ' ' . $empleado->apellido,
                'email'       => $request->email,
                'password'    => Hash::make($request->dni),
                'rol_id'      => $request->rol_id,
                'empleado_id' => $empleado->id,
                // Entra con su DNI, y el sistema no le deja hacer nada más
                // hasta que ponga una contraseña suya: el DNI está a la vista
                // de todos en la ficha y en la boleta.
                'debe_cambiar_password' => true,
            ]);

            // El primer contrato sale de lo que ya se pide en el alta: el tipo y la
            // fecha de ingreso. Las renovaciones se hacen luego desde Contratos, que
            // al crear una nueva cierra la anterior.
            Contrato::create([
                'empleado_id'   => $empleado->id,
                'tipo_contrato' => $request->tipo_contrato,
                'fecha_inicio'  => $request->fecha_ingreso,
                'fecha_fin'     => $request->tipo_contrato === 'indeterminado'
                    ? null
                    : $request->fecha_fin_contrato,
                'estado'        => 'vigente',
                'observaciones' => 'Contrato inicial, creado al dar de alta al trabajador.',
            ]);

            return $empleado;
        });

        $empleado->load('area', 'cargo', 'sede', 'contratos');
        return response()->json([
            'success' => true,
            'data'    => $empleado,
            'mensaje' => 'Empleado creado con su contrato inicial. Usuario generado con contraseña: DNI del empleado.'
        ], 201);
    }

    public function show(string $id)
    {
        $empleado = Empleado::with('area', 'cargo', 'sede', 'usuario', 'identidadFirma')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $empleado]);
    }

    public function update(Request $request, string $id)
    {
        $empleado = Empleado::findOrFail($id);
        $usuario  = $empleado->usuario;

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
            'tipo_contrato'      => 'nullable|in:indeterminado,plazo_fijo,suplencia,practicas',
            'forma_pago'         => 'nullable|in:banco,efectivo,otro',
            'sede_id'            => 'nullable|uuid|exists:sedes,id',
            // Email del usuario vinculado: se acepta editar aquí mismo porque no todos los
            // docentes tienen correo institucional al momento de ingresar — RRHH los da de alta
            // con un correo provisional y luego lo actualiza cuando ya tienen su @cata.edu.pe.
            'email'              => ['nullable', 'email', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'nivel_estudios'       => 'nullable|in:primaria,secundaria,tecnico,universitario,maestria,doctorado',
            'especialidad'         => 'nullable|string|max:150',
            'institucion_estudios' => 'nullable|string|max:150',
            'contacto_emergencia_nombre'    => 'nullable|string|max:150',
            'contacto_emergencia_telefono'  => 'nullable|regex:/^[0-9]+$/|max:15',
            'fecha_nacimiento'              => 'nullable|date|before:today',
        ]);

        $empleado->update($request->except(['email', 'rol_id']));

        if ($request->filled('email') && $usuario) {
            $usuario->update(['email' => $request->email]);
        }

        $empleado->load('area', 'cargo', 'sede', 'usuario');
        return response()->json(['success' => true, 'data' => $empleado]);
    }

    public function destroy(string $id)
    {
        $empleado = Empleado::findOrFail($id);
        $empleado->update(['estado' => 'inactivo']);

        User::where('empleado_id', $empleado->id)->each(function (User $user) {
            $user->update(['estado_registro' => 'inactivo']);
            $user->tokens()->delete();
        });

        return response()->json(['success' => true, 'data' => ['message' => 'Empleado desactivado correctamente.']]);
    }
}