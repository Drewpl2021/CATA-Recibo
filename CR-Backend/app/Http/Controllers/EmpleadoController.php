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
use App\Traits\ExportaCsv;
use App\Models\Cargo;
use Illuminate\Validation\ValidationException;

class EmpleadoController extends Controller
{
    use ListadoPaginado;
    use ExportaCsv;

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

        /*
         * El listado va por fecha de registro, lo último arriba.
         *
         * Antes salía por apellido: RR.HH. daba de alta a alguien y el alta
         * caía en la página 7, entre los Quispe. Ahora el que se acaba de
         * registrar es el primero, y la columna "#" numera desde ahí.
         *
         * El id desempata: los uuid7 llegan en orden de creación, así que dos
         * altas del mismo segundo siguen saliendo en el orden en que se
         * hicieron.
         *
         * El formato "selector" (los desplegables y el buscador de personas)
         * sigue alfabético: ahí se busca a alguien por su nombre, no se mira
         * quién entró último.
         */
        $ordenado = $paraSelector
            ? $query->orderBy('apellido')->orderBy('nombre')
            : $query->orderByDesc('created_at')->orderByDesc('id');

        return $this->responderListado(
            $request,
            $ordenado,
            // El cargo, el área y el correo no viven en empleados: el correo
            // está en users y los otros dos en su propia tabla. Van por la
            // relación, y el trait los resuelve buscando primero ahí.
            ['nombre', 'apellido', 'dni', 'cargo.nombre', 'area.nombre', 'usuario.email'],
            // Las cifras de la cabecera: se cuentan sobre todo lo que pasa el
            // filtro, no sobre la página que se está viendo.
            fn (Builder $filtrada) => $this->conteoPorEstado($filtrada, 'estado', ['activos' => 'activo', 'inactivos' => 'inactivo'])
        );
    }

    /**
     * GET /empleados/exportar — la ficha de todo el personal, en CSV.
     *
     * Es la lista que RR.HH. mantenía aparte en su propio Excel: datos
     * personales, laborales, de planilla y bancarios de cada trabajador. Al
     * salir del sistema deja de ser una copia que se desactualiza sola.
     *
     * Respeta el buscador de la pantalla —los mismos seis campos, relaciones
     * incluidas—, así que lo que se ve en la tabla es lo que baja.
     *
     * Va en orden alfabético y no por fecha de alta como la tabla: esto se
     * imprime y se busca con el dedo, y ahí manda el apellido.
     */
    public function exportar(Request $request)
    {
        $query = Empleado::with('area:id,nombre', 'cargo:id,nombre', 'sede:id,nombre', 'usuario.rol')
            ->orderBy('apellido')
            ->orderBy('nombre');

        $this->aplicarBusqueda(
            $request,
            $query,
            ['nombre', 'apellido', 'dni', 'cargo.nombre', 'area.nombre', 'usuario.email']
        );

        $empleados = $query->get();

        $cabecera = [
            'N°', 'DNI', 'Apellidos', 'Nombres', 'Fecha de nacimiento',
            'Teléfono', 'Dirección', 'Correo', 'Rol',
            'Área', 'Cargo', 'Sede', 'Fecha de ingreso', 'Estado', 'Tipo de contrato',
            'Sueldo base', 'Sistema de pensión', 'AFP', 'CUSPP',
            'Forma de pago', 'Banco', 'N° de cuenta', 'CCI',
            'Tiene hijos', 'Nivel de estudios', 'Especialidad', 'Institución donde estudió',
            'Contacto de emergencia', 'Teléfono del contacto',
        ];

        $archivo = $this->nombreCsvSeguro('Empleados ' . now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($empleados, $cabecera) {
            $salida = fopen('php://output', 'w');

            fwrite($salida, $this->bomUtf8());
            fwrite($salida, $this->filaCsv($cabecera));

            $fecha = fn ($valor) => $valor ? \Carbon\Carbon::parse($valor)->format('d/m/Y') : '';

            foreach ($empleados as $i => $e) {
                fwrite($salida, $this->filaCsv([
                    $i + 1,
                    $e->dni,
                    $e->apellido,
                    $e->nombre,
                    $fecha($e->fecha_nacimiento),
                    $e->telefono ?? '',
                    $e->direccion ?? '',
                    $e->usuario->email ?? 'Sin cuenta',
                    $e->usuario->rol->nombre ?? '',
                    $e->area->nombre ?? '',
                    $e->cargo->nombre ?? '',
                    $e->sede->nombre ?? '',
                    $fecha($e->fecha_ingreso),
                    $e->estado ?? '',
                    $e->tipo_contrato ?? '',
                    $e->sueldo_base !== null ? number_format((float) $e->sueldo_base, 2, '.', '') : '',
                    // Vacío no es un olvido: es "no aporta a ninguna pensión".
                    $e->sistema_pensiones ?: 'No aporta',
                    $e->afp ?? '',
                    $e->cuspp ?? '',
                    $e->forma_pago ?? '',
                    $e->entidad_financiera ?? '',
                    $e->numero_cuenta ?? '',
                    $e->cci ?? '',
                    $e->tiene_hijos ? 'Sí' : 'No',
                    $e->nivel_estudios ?? '',
                    $e->especialidad ?? '',
                    $e->institucion_estudios ?? '',
                    $e->contacto_emergencia_nombre ?? '',
                    $e->contacto_emergencia_telefono ?? '',
                ]));
            }

            fclose($salida);
        }, $archivo, [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
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
            // Opcional, pero si viene tiene que ser un CCI de verdad: son 20
            // dígitos exactos. Uno mal copiado no rebota, se abona a otra
            // persona, y eso no hay forma de verlo hasta el reclamo.
            'cci'                => 'nullable|regex:/^[0-9]{20}$/',
            'tiene_hijos'        => 'nullable|boolean',
            'sueldo_base'        => 'required|numeric|min:0',
            'tipo_contrato'      => 'required|in:indeterminado,plazo_fijo,suplencia,practicas',
            // Un plazo fijo, una suplencia o unas prácticas SIN fecha de término no
            // son un contrato: hay que saber cuándo acaba. El indeterminado es el
            // único que no lleva fin, y ahí el campo sobra.
            'fecha_fin_contrato' => 'required_unless:tipo_contrato,indeterminado|nullable|date|after:fecha_ingreso',
            'forma_pago'         => 'nullable|in:banco,efectivo,otro,honorarios',
            'sede_id'            => 'required|uuid|exists:sedes,id',
            'email'              => 'required|email|unique:users,email',
            'rol_id'             => 'required|uuid|exists:roles,id',
            'nivel_estudios'       => 'nullable|in:primaria,secundaria,tecnico,universitario,maestria,doctorado',
            'especialidad'         => 'nullable|string|max:150',
            'institucion_estudios' => 'nullable|string|max:150',
            'contacto_emergencia_nombre'    => 'nullable|string|max:150',
            'contacto_emergencia_telefono'  => 'nullable|regex:/^[0-9]+$/|max:15',
            'fecha_nacimiento'              => 'required|date|before:today',
        ], [
            // El genérico ("el formato no es válido") no dice qué arreglar.
            'cci.regex' => 'El CCI son 20 dígitos, sin espacios ni guiones.',
        ]);

        $this->limpiarDatosDeAfp($request);

        $this->exigirCargoDelArea($request->cargo_id, $request->area_id);

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
            'sistema_pensiones'  => 'sometimes|nullable|in:AFP,ONP',
            'afp'                => 'nullable|in:Habitat,Integra,Prima,Profuturo',
            'cuspp'              => 'nullable|regex:/^[0-9]{11}$/|required_if:sistema_pensiones,AFP',
            'entidad_financiera' => 'nullable|string|max:100',
            'numero_cuenta'      => 'nullable|string|max:50',
            'cci'                => 'nullable|regex:/^[0-9]{20}$/',
            'tiene_hijos'        => 'nullable|boolean',
            'sueldo_base'        => 'nullable|numeric|min:0',
            'tipo_contrato'      => 'nullable|in:indeterminado,plazo_fijo,suplencia,practicas',
            'forma_pago'         => 'nullable|in:banco,efectivo,otro,honorarios',
            'sede_id'            => 'nullable|uuid|exists:sedes,id',
            // Email del usuario vinculado: se acepta editar aquí mismo porque no todos
            // tienen correo institucional al momento de ingresar. Se da de alta con uno
            // provisional y se actualiza cuando ya tienen el definitivo.
            'email'              => ['nullable', 'email', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'nivel_estudios'       => 'nullable|in:primaria,secundaria,tecnico,universitario,maestria,doctorado',
            'especialidad'         => 'nullable|string|max:150',
            'institucion_estudios' => 'nullable|string|max:150',
            'contacto_emergencia_nombre'    => 'nullable|string|max:150',
            'contacto_emergencia_telefono'  => 'nullable|regex:/^[0-9]+$/|max:15',
            'fecha_nacimiento'              => 'nullable|date|before:today',
        ], [
            'cci.regex' => 'El CCI son 20 dígitos, sin espacios ni guiones.',
        ]);

        $this->exigirCargoDelArea(
            $request->input('cargo_id', $empleado->cargo_id),
            $request->input('area_id', $empleado->area_id)
        );

        $this->limpiarDatosDeAfp($request);

        $empleado->update($request->except(['email', 'rol_id']));

        if ($request->filled('email') && $usuario) {
            $usuario->update(['email' => $request->email]);
        }

        $empleado->load('area', 'cargo', 'sede', 'usuario');
        return response()->json(['success' => true, 'data' => $empleado]);
    }

    /**
     * La AFP y el CUSPP solo tienen sentido con AFP.
     *
     * Quien pasa a ONP —o a no aportar a ninguna pensión, que es el caso del
     * jubilado que vuelve a dictar— tiene que quedarse sin ellos: si no, la
     * ficha guarda una AFP de alguien que ya no está en el sistema privado y
     * la boleta acaba enseñando un CUSPP que no viene a cuento.
     *
     * Solo actúa cuando el sistema de pensiones viene en la petición: una
     * edición parcial que ni lo menciona no debe borrar nada.
     */
    private function limpiarDatosDeAfp(Request $request): void
    {
        if (! $request->has('sistema_pensiones')) {
            return;
        }

        if ($request->input('sistema_pensiones') !== 'AFP') {
            $request->merge(['afp' => null, 'cuspp' => null]);
        }
    }

    /**
     * El cargo elegido tiene que valer en el área elegida.
     *
     * Un cargo acotado a Contabilidad no puede ponerse en Vigilancia. Los
     * cargos SIN áreas marcadas (Practicante, Voluntario Misionero) valen en
     * cualquiera: la relación acota, no obliga.
     *
     * Va en el backend y no solo en el desplegable porque una pantalla no es
     * una cerradura: por la API se podría mandar cualquier pareja.
     */
    private function exigirCargoDelArea(?string $cargoId, ?string $areaId): void
    {
        if (! $cargoId || ! $areaId) {
            return;
        }

        $cargo = Cargo::with('areas:id,nombre')->find($cargoId);

        if (! $cargo || $cargo->valeEnElArea($areaId)) {
            return;
        }

        $donde = $cargo->areas->pluck('nombre')->implode(', ');

        throw ValidationException::withMessages([
            'cargo_id' => ["\"{$cargo->nombre}\" no pertenece a esa área: es de {$donde}. "
                . 'Elige otro cargo, o agrégale el área desde Configuración → Cargos.'],
        ]);
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