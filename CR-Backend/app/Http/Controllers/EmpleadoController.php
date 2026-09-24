<?php
namespace App\Http\Controllers;
use App\Models\Contrato;
use Carbon\Carbon;
use App\Models\Empleado;
use App\Models\User;
use App\Models\Rol;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Traits\ListadoPaginado;
use App\Support\LibroExcel;
use App\Traits\ExportaExcel;
use App\Models\Cargo;
use App\Services\AltaDeEmpleado;
use Illuminate\Validation\ValidationException;

class EmpleadoController extends Controller
{
    use ListadoPaginado;
    use ExportaExcel;

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

        // Los filtros de la tabla: área, sede, cargo, contrato, pensión y,
        // para la pantalla de boletas, cómo va la del mes.
        $this->aplicarFiltrosDePersonal($request, $ordenado);

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
     * Los filtros de la tabla del personal.
     *
     * Hasta ahora solo había un buscador por texto: para "los de Jerusalén
     * con plazo fijo" o "a quién le falta la boleta de este mes" había que
     * bajar la lista a Excel y filtrar ahí. Son las preguntas que RR.HH. se
     * hace todos los meses, así que las contesta el servidor: filtra sobre
     * TODO el personal y no sobre la página que se está viendo, y las cifras
     * de la cabecera se recalculan con el mismo filtro.
     *
     * Los tres últimos (planilla, boleta) necesitan mes y año: son el estado
     * de ESE mes, no del trabajador.
     */
    /**
     * Los filtros puestos, escritos como los lee una persona.
     *
     * @return array<string, string|null>
     */
    private function filtrosDelReporte(Request $request): array
    {
        $mes  = $request->input('mes');
        $anio = $request->input('anio');
        $delMes = $mes && $anio ? \App\Support\Meses::nombre((int) $mes) . ' ' . $anio : null;

        return [
            'Estado'            => match ($request->input('estado')) {
                'activo'   => 'Solo activos',
                'inactivo' => 'Solo cesados',
                default    => null,
            },
            'Sede'              => $this->nombreDeCatalogo(\App\Models\Sede::class, $request->input('sede_id')),
            'Área'              => $this->nombreDeCatalogo(\App\Models\Area::class, $request->input('area_id')),
            'Cargo'             => $this->nombreDeCatalogo(\App\Models\Cargo::class, $request->input('cargo_id')),
            'Tipo de contrato'  => $request->input('tipo_contrato'),
            'Sistema de pensión' => match ($request->input('sistema_pensiones')) {
                'ninguno' => 'No aporta a ninguna',
                null, ''  => null,
                default   => $request->input('sistema_pensiones'),
            },
            'Forma de pago'     => $request->input('forma_pago'),
            'Sin sueldo puesto' => $request->boolean('sin_sueldo') ? 'Sí' : null,
            'Ingresó desde'     => $request->input('ingreso_desde'),
            'Ingresó hasta'     => $request->input('ingreso_hasta'),
            'Planilla del mes'  => match ($request->input('planilla')) {
                'con' => 'Con planilla en ' . ($delMes ?? 'el mes elegido'),
                'sin' => 'Sin planilla en ' . ($delMes ?? 'el mes elegido'),
                default => null,
            },
            'Boleta del mes'    => match ($request->input('boleta')) {
                'con'         => 'Con boleta en ' . ($delMes ?? 'el mes elegido'),
                'sin'         => 'Sin boleta en ' . ($delMes ?? 'el mes elegido'),
                'sin_firmar'  => 'Con boleta sin firmar en ' . ($delMes ?? 'el mes elegido'),
                default       => null,
            },
            'Búsqueda'          => $request->input('search'),
        ];
    }

    /**
     * Pone el contrato de acuerdo con lo que dice la ficha.
     *
     * Se compara contra el CONTRATO VIGENTE, no contra lo que tenía la ficha
     * antes: así, una ficha que ya se había ido por su lado —el caso que
     * destapó esto— se arregla sola la próxima vez que se guarde.
     *
     * Tres casos:
     *   · No tiene contrato todavía → se le crea el que dice su ficha, desde
     *     su fecha de ingreso.
     *   · Mismo tipo → a lo más cambió la fecha de término, y se actualiza.
     *   · Otro tipo → se cierra el de ahora y empieza uno nuevo hoy, igual
     *     que al renovar desde Contratos. El anterior queda en su historial.
     */
    private function moverContratoSiCambio(Request $request, Empleado $empleado): void
    {
        if (! $request->filled('tipo_contrato')) {
            return;
        }

        $tipo    = $request->input('tipo_contrato');
        $fin     = $request->input('fecha_fin_contrato') ?: null;
        $llevaFin = $tipo !== 'indeterminado';
        $vigente = $empleado->contratoVigente()->first();

        if (! $vigente) {
            Contrato::create([
                'empleado_id'   => $empleado->id,
                'tipo_contrato' => $tipo,
                'fecha_inicio'  => $empleado->fecha_ingreso,
                'fecha_fin'     => $llevaFin ? $fin : null,
                'estado'        => 'vigente',
                'observaciones' => 'Creado desde la ficha del trabajador.',
            ]);

            return;
        }

        if ($vigente->tipo_contrato === $tipo) {
            $finViejo = $vigente->fecha_fin ? Carbon::parse($vigente->fecha_fin)->toDateString() : null;

            if ($llevaFin && $fin && $fin !== $finViejo) {
                $vigente->update(['fecha_fin' => $fin]);
            }

            return;
        }

        // Un contrato con plazo sin fecha de término no es un contrato: hay
        // que saber cuándo acaba, y por eso se pide antes de moverlo.
        if ($llevaFin && ! $fin) {
            abort(422, 'Para cambiarlo a ese tipo de contrato hace falta la fecha de término.');
        }

        DB::transaction(function () use ($vigente, $empleado, $tipo, $fin, $llevaFin) {
            $hoy = now()->toDateString();

            $vigente->update([
                'estado'     => 'finalizado',
                // Si ya tenía una fecha de término pasada, se respeta; si no,
                // se cierra hoy, que es cuando de verdad dejó de regir.
                'fecha_fin'  => $vigente->fecha_fin && Carbon::parse($vigente->fecha_fin)->lt(now())
                    ? $vigente->fecha_fin
                    : $hoy,
                'motivo_fin' => $vigente->motivo_fin ?: 'otro',
                'observaciones' => trim(($vigente->observaciones ? $vigente->observaciones . ' ' : '')
                    . 'Cerrado al cambiarle el tipo de contrato desde la ficha.'),
            ]);

            Contrato::create([
                'empleado_id'   => $empleado->id,
                'tipo_contrato' => $tipo,
                'fecha_inicio'  => $hoy,
                'fecha_fin'     => $llevaFin ? $fin : null,
                'estado'        => 'vigente',
                'observaciones' => 'Creado al cambiarle el tipo de contrato desde la ficha.',
            ]);
        });
    }

    private function aplicarFiltrosDePersonal(Request $request, Builder $query): void
    {
        $request->validate([
            'estado'            => 'nullable|in:activo,inactivo',
            'area_id'           => 'nullable|uuid|exists:areas,id',
            'cargo_id'          => 'nullable|uuid|exists:cargos,id',
            'sede_id'           => 'nullable|uuid|exists:sedes,id',
            'tipo_contrato'     => 'nullable|in:indeterminado,plazo_fijo,suplencia,practicas',
            // "ninguno" no es un valor de la columna: es no aportar a ninguna
            // pensión, que en la base es NULL.
            'sistema_pensiones' => 'nullable|in:AFP,ONP,ninguno',
            'forma_pago'        => 'nullable|in:banco,efectivo',
            'sin_sueldo'        => 'nullable|boolean',
            'ingreso_desde'     => 'nullable|date',
            'ingreso_hasta'     => 'nullable|date',
            'planilla'          => 'nullable|in:con,sin',
            'boleta'            => 'nullable|in:con,sin,sin_firmar',
            'mes'               => 'nullable|integer|min:1|max:12',
            'anio'              => 'nullable|integer|min:2000',
        ]);

        // Con el prefijo de la tabla a propósito: areas, cargos y sedes
        // también tienen `estado`, y en cuanto una consulta las junta MySQL
        // no sabe de cuál se le habla.
        foreach (['estado', 'area_id', 'cargo_id', 'sede_id', 'tipo_contrato', 'forma_pago'] as $campo) {
            if ($request->filled($campo)) {
                $query->where('empleados.' . $campo, $request->input($campo));
            }
        }

        $pension = $request->input('sistema_pensiones');
        if ($pension === 'ninguno') {
            $query->whereNull('empleados.sistema_pensiones');
        } elseif ($pension) {
            $query->where('empleados.sistema_pensiones', $pension);
        }

        // Sin sueldo no se le puede armar planilla: la generación lo salta y
        // la persona se queda sin cobrar sin que nadie se dé cuenta.
        if ($request->boolean('sin_sueldo')) {
            $query->where(fn (Builder $q) => $q->whereNull('sueldo_base')->orWhere('sueldo_base', '<=', 0));
        }

        if ($request->filled('ingreso_desde')) {
            $query->whereDate('fecha_ingreso', '>=', $request->input('ingreso_desde'));
        }

        if ($request->filled('ingreso_hasta')) {
            $query->whereDate('fecha_ingreso', '<=', $request->input('ingreso_hasta'));
        }

        $this->filtrarPorElMes($request, $query);
    }

    /**
     * Cómo va el mes de cada quien: si tiene planilla armada y si ya se le
     * emitió (y firmó) su boleta.
     *
     * Es lo que se pregunta en la pantalla de boletas mientras se emite:
     * "¿a quién le falta?". Sin mes y año no se aplica, porque la respuesta
     * depende del periodo que se esté armando.
     */
    private function filtrarPorElMes(Request $request, Builder $query): void
    {
        $mes  = (int) $request->input('mes');
        $anio = (int) $request->input('anio');

        if (! $mes || ! $anio) {
            return;
        }

        $planillasDelMes = \App\Models\Planilla::where('mes', $mes)->where('anio', $anio);

        if ($request->filled('planilla')) {
            $ids = (clone $planillasDelMes)->select('empleado_id');
            $request->input('planilla') === 'con'
                ? $query->whereIn('empleados.id', $ids)
                : $query->whereNotIn('empleados.id', $ids);
        }

        if ($request->filled('boleta')) {
            $boletas = \App\Models\Documento::where('tipo', 'boleta')
                ->whereIn('planilla_id', (clone $planillasDelMes)->select('id'));

            match ($request->input('boleta')) {
                'con' => $query->whereIn('empleados.id', (clone $boletas)->select('empleado_id')),
                'sin' => $query->whereNotIn('empleados.id', (clone $boletas)->select('empleado_id')),
                // Emitida pero sin firmar: es la lista a la que hay que ir a
                // recordarle, distinta de "no se le emitió".
                'sin_firmar' => $query->whereIn(
                    'empleados.id',
                    (clone $boletas)->where('estado_firma', '!=', 'firmado')->select('empleado_id')
                ),
            };
        }
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
        $query = Empleado::with('area:id,nombre', 'cargo:id,nombre', 'sede:id,nombre', 'usuario.rol', 'contratoVigente')
            ->orderBy('apellido')
            ->orderBy('nombre');

        $this->aplicarBusqueda(
            $request,
            $query,
            ['nombre', 'apellido', 'dni', 'cargo.nombre', 'area.nombre', 'usuario.email']
        );

        // Los mismos filtros de la pantalla: si arriba se filtró por sede o
        // por tipo de contrato, el archivo sale con esa misma gente.
        $this->aplicarFiltrosDePersonal($request, $query);

        $empleados = $query->get();

        $cabecera = [
            'N°', 'DNI', 'Apellidos', 'Nombres', 'Fecha de nacimiento',
            'Teléfono', 'Dirección', 'Correo', 'Rol',
            'Área', 'Cargo', 'Sede', 'Fecha de ingreso', 'Estado', 'Fecha de cese', 'Tipo de contrato', 'Fin de contrato',
            'Sueldo base', 'Sistema de pensión', 'AFP', 'CUSPP',
            'Forma de pago', 'Banco', 'N° de cuenta', 'CCI',
            'Tiene hijos', 'Nivel de estudios', 'Especialidad', 'Institución donde estudió',
            'Contacto de emergencia', 'Teléfono del contacto',
        ];

        $fecha = fn ($valor) => $valor ? \Carbon\Carbon::parse($valor)->format('d/m/Y') : '';

        $filas = [];
        foreach ($empleados as $i => $e) {
            $filas[] = [
                $i + 1,
                (string) $e->dni,
                $e->apellido,
                $e->nombre,
                $fecha($e->fecha_nacimiento),
                (string) ($e->telefono ?? ''),
                $e->direccion ?? '',
                $e->usuario->email ?? 'Sin cuenta',
                $e->usuario->rol->nombre ?? '',
                $e->area->nombre ?? '',
                $e->cargo->nombre ?? '',
                $e->sede->nombre ?? '',
                $fecha($e->fecha_ingreso),
                // Como lo lee la planilla, y como lo acepta la importación.
                $e->estado === 'inactivo' ? 'Cesado' : 'Activo',
                $fecha($e->fecha_cese),
                // Del contrato vigente, no de la copia suelta de la ficha, que
                // envejece. Y con su fin: sin él, un plazo fijo descargado no se
                // podía volver a importar.
                $e->contratoVigente->tipo_contrato ?? $e->tipo_contrato ?? '',
                $fecha($e->contratoVigente?->fecha_fin),
                // Número de verdad, no texto: así se puede sumar y filtrar sin
                // convertir nada, y la importación lo vuelve a leer igual.
                $e->sueldo_base !== null ? round((float) $e->sueldo_base, 2) : null,
                // Vacío no es un olvido: es "no aporta a ninguna pensión".
                $e->sistema_pensiones ?: 'No aporta',
                $e->afp ?? '',
                (string) ($e->cuspp ?? ''),
                $e->forma_pago ?? '',
                $e->entidad_financiera ?? '',
                (string) ($e->numero_cuenta ?? ''),
                (string) ($e->cci ?? ''),
                $e->tiene_hijos ? 'Sí' : 'No',
                $e->nivel_estudios ?? '',
                $e->especialidad ?? '',
                $e->institucion_estudios ?? '',
                $e->contacto_emergencia_nombre ?? '',
                (string) ($e->contacto_emergencia_telefono ?? ''),
            ];
        }

        // Texto donde el formato importa —un DNI que empieza por cero, una
        // fecha que Excel no debe reinterpretar según el idioma— y número
        // donde se suma.
        $comoTexto = [
            'DNI', 'Fecha de nacimiento', 'Teléfono', 'Fecha de ingreso', 'Fecha de cese', 'Fin de contrato',
            'CUSPP', 'N° de cuenta', 'CCI', 'Teléfono del contacto',
        ];
        $estiloColumnas = [];
        foreach ($cabecera as $i => $titulo) {
            $estiloColumnas[$i] = match (true) {
                in_array($titulo, $comoTexto, true) => LibroExcel::TEXTO,
                $titulo === 'Sueldo base'           => LibroExcel::MONTO,
                default                             => LibroExcel::NORMAL,
            };
        }

        $libro = new LibroExcel();
        $this->hojaDeReporte($libro, 'Empleados', $cabecera, $filas, $estiloColumnas);
        // Al final: la importación lee la primera hoja, que es la de datos.
        $this->hojaDeFiltros($libro, $this->filtrosDelReporte($request), $request->user()?->name);

        return $libro->descargar($this->nombreExcelSeguro('Empleados ' . now()->format('Y-m-d')));
    }

    public function store(Request $request)
    {
        // Las reglas viven en AltaDeEmpleado: las usa también la importación
        // desde Excel, y así las dos altas piden exactamente lo mismo.
        $request->validate(AltaDeEmpleado::reglas(), AltaDeEmpleado::mensajes());

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
        $empleado = AltaDeEmpleado::crear($request->all());

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
            'fecha_cese'         => 'nullable|date',
            'sistema_pensiones'  => 'sometimes|nullable|in:AFP,ONP',
            'afp'                => 'nullable|in:Habitat,Integra,Prima,Profuturo',
            // Mismo formato que el alta: 12 caracteres, con letras y números.
            'cuspp'              => 'nullable|regex:/^[A-Za-z0-9]{12}$/|required_if:sistema_pensiones,AFP',
            'entidad_financiera' => 'nullable|string|max:100',
            'numero_cuenta'      => 'nullable|string|max:50',
            'cci'                => 'nullable|regex:/^[0-9]{20}$/',
            'tiene_hijos'        => 'nullable|boolean',
            'sueldo_base'        => 'nullable|numeric|min:0',
            'tipo_contrato'      => 'nullable|in:indeterminado,plazo_fijo,suplencia,practicas',
            // No es columna del empleado: es la fecha de término de su
            // contrato, y se usa para moverlo cuando acá se cambia el tipo.
            'fecha_fin_contrato' => 'nullable|date',
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

        // Quien vuelve a estar activo ya no tiene fecha de cese.
        if ($request->input('estado') === 'activo') {
            $request->merge(['fecha_cese' => null]);
        }

        $empleado->update($request->except(['email', 'rol_id']));

        // El contrato manda sobre la ficha: es el papel que firma la persona
        // y el que miran las vacaciones, la boleta y los reportes. Si acá se
        // cambió el tipo, hay que mover también el contrato.
        $this->moverContratoSiCambio($request, $empleado);

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
        $empleado->update(['estado' => 'inactivo', 'fecha_cese' => $empleado->fecha_cese ?? now()->toDateString()]);
        $empleado->quitarAcceso();

        return response()->json(['success' => true, 'data' => ['message' => 'Empleado desactivado correctamente.']]);
    }
}