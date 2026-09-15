<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Empleado;
use App\Models\User;
use App\Services\AltaDeEmpleado;
use App\Support\ColumnasDeEmpleado;
use App\Support\ExpedienteDigital;
use App\Support\LectorDeCeldas;
use App\Support\ModelosDeImportacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Importar empleados desde un Excel: altas y actualizaciones por DNI.
 *
 * Igual que la importación de conceptos, el archivo se lee en el navegador y
 * aquí solo llegan títulos y celdas. Tres pasos —reconocer, previsualizar,
 * aplicar— y el último vuelve a revisar todo y guarda todo o nada.
 *
 * Cómo decide qué hacer con cada fila:
 *
 *   DNI nuevo       alta completa, exactamente como Nuevo Empleado
 *                   (AltaDeEmpleado): ficha, cuenta y contrato inicial.
 *   DNI existente   actualiza SOLO las celdas con algo escrito. Vacía no
 *                   toca nada: borrar una columna por error no le vacía la
 *                   ficha a todo el personal.
 *
 * Las hojas de vida van aparte (hojaDeVida): una celda no puede llevar un
 * archivo, así que se suben en lote con el DNI en el nombre del archivo.
 */
class ImportacionEmpleadosController extends Controller
{
    private const MAX_FILAS    = 2000;
    private const MAX_COLUMNAS = 60;

    /**
     * GET /importacion-empleados/modelo — el Excel vacío para llenar, con
     * listas desplegables e instrucciones. A RR.HH. no le ofrece el rol admin.
     */
    public function modelo(Request $request)
    {
        return ModelosDeImportacion::empleados($request->user()->rol?->nombre === 'admin')
            ->descargar('Modelo de empleados.xlsx');
    }

    /** POST /importacion-empleados/reconocer — qué dato de la ficha es cada columna. */
    public function reconocer(Request $request)
    {
        $datos = $request->validate([
            'columnas'   => 'required|array|min:1|max:' . self::MAX_COLUMNAS,
            'columnas.*' => 'nullable|string|max:255',
        ], [
            'columnas.required' => 'El archivo no tiene títulos de columna.',
            'columnas.max'      => 'El archivo tiene más de ' . self::MAX_COLUMNAS . ' columnas.',
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'columnas' => ColumnasDeEmpleado::reconocer($datos['columnas']),
                'campos'   => ColumnasDeEmpleado::paraPantalla(),
            ],
        ]);
    }

    /** POST /importacion-empleados/previsualizar — qué pasaría. No guarda nada. */
    public function previsualizar(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->paraPantalla($this->analizar($request)),
        ]);
    }

    /** POST /importacion-empleados/aplicar — lo guarda todo, o nada. */
    public function aplicar(Request $request)
    {
        $r = $this->analizar($request);

        if ($r['errores']) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo tiene errores. Corrígelos y vuelve a revisarlo: no se guardó nada.',
                'data'    => $this->paraPantalla($r),
            ], 422);
        }

        if (! $r['filas']) {
            return response()->json([
                'success' => false,
                'message' => 'No hay nada que cambiar: los trabajadores del archivo ya están así en el sistema.',
                'data'    => $this->paraPantalla($r),
            ], 422);
        }

        DB::transaction(function () use ($r) {
            foreach ($r['filas'] as $fila) {
                if ($fila['modo'] === 'alta') {
                    AltaDeEmpleado::crear($fila['_datos']);
                    continue;
                }

                $empleado = $fila['_empleado'];
                $campos   = $fila['_campos'];
                $correo   = $campos['email'] ?? null;
                unset($campos['email']);

                if ($campos) {
                    $empleado->update($campos);
                }
                if ($correo !== null && $empleado->usuario) {
                    $empleado->usuario->update(['email' => $correo]);
                }
            }
        });

        $resumen = $this->paraPantalla($r)['resumen'];

        Auditoria::registrar(
            'importó',
            'empleado',
            null,
            "Importó empleados desde Excel: {$resumen['altas']} altas y {$resumen['actualizaciones']} actualizaciones",
            [
                'archivo'         => $r['archivo'],
                'altas'           => $resumen['altas'],
                'actualizaciones' => $resumen['actualizaciones'],
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'resumen' => $resumen,
                'mensaje' => "Listo: {$resumen['altas']} alta(s) y {$resumen['actualizaciones']} actualización(es).",
            ],
        ]);
    }

    /**
     * POST /importacion-empleados/hoja-de-vida — un CV del lote.
     *
     * Se manda de a uno para que un archivo pesado no tumbe a los demás, y
     * para poder decir cuál falló. El trabajador se busca por el DNI.
     */
    public function hojaDeVida(Request $request)
    {
        $request->validate([
            'dni'     => 'required|string|max:20',
            'archivo' => ExpedienteDigital::REGLA_ARCHIVO,
        ], [
            'archivo.mimes' => 'La hoja de vida tiene que ser PDF, Word o una imagen (JPG o PNG).',
            'archivo.max'   => 'El archivo pasa de 5 MB.',
        ]);

        $claves   = LectorDeCeldas::dni($request->input('dni'))['claves'];
        $empleado = Empleado::whereIn('dni', $claves ?: ['-'])->first();

        if (! $empleado) {
            return response()->json([
                'success' => false,
                'message' => "No hay ningún trabajador con el DNI {$request->input('dni')}.",
            ], 422);
        }

        $documento = ExpedienteDigital::guardar($empleado->id, ExpedienteDigital::HOJA_DE_VIDA, $request->file('archivo'));

        return response()->json([
            'success' => true,
            'data'    => [
                'documento' => $documento,
                'dni'       => $empleado->dni,
                'nombre'    => trim($empleado->nombre . ' ' . $empleado->apellido),
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────

    /** Revisa el archivo entero y arma lo que habría que hacer. Nunca escribe. */
    private function analizar(Request $request): array
    {
        $datos = $request->validate([
            'archivo'           => 'nullable|string|max:150',
            'columnas'          => 'required|array|min:1|max:' . self::MAX_COLUMNAS,
            'columnas.*.indice' => 'required|integer|min:0|max:' . (self::MAX_COLUMNAS - 1),
            'columnas.*.titulo' => 'nullable|string|max:255',
            'columnas.*.campo'  => ['nullable', Rule::in(array_keys(ColumnasDeEmpleado::CAMPOS))],
            'filas'             => 'required|array|min:1|max:' . self::MAX_FILAS,
            'filas.*.numero'    => 'required|integer|min:1',
            'filas.*.celdas'    => 'present|array|max:' . self::MAX_COLUMNAS,
            'cvs'               => 'nullable|array|max:' . self::MAX_FILAS,
            'cvs.*'             => 'string|max:20',
        ], [
            'filas.required' => 'El archivo no tiene filas con datos.',
            'filas.max'      => 'El archivo tiene más de ' . self::MAX_FILAS . ' filas: pártelo en dos.',
        ]);

        $r = [
            'archivo' => $datos['archivo'] ?? null,
            'errores' => [], 'advertencias' => [], 'filas' => [],
            'filas_leidas' => 0, 'sin_cambios' => 0, 'cvs' => 0, 'cvs_sin_trabajador' => [],
        ];

        // ── Las columnas ─────────────────────────────────────────
        $mapa    = [];
        $titulos = [];
        foreach ($datos['columnas'] as $columna) {
            $campo = $columna['campo'] ?? null;
            if (! $campo) {
                continue;
            }
            $nombreCampo = ColumnasDeEmpleado::CAMPOS[$campo]['titulo'];
            if (isset($mapa[$campo])) {
                $r['errores'][] = $this->aviso(null, null, $nombreCampo, "Dos columnas son «{$nombreCampo}»: deja una sola.");
                continue;
            }
            $mapa[$campo]    = (int) $columna['indice'];
            $titulos[$campo] = trim((string) ($columna['titulo'] ?? '')) ?: $nombreCampo;
        }

        if (! isset($mapa['dni'])) {
            $r['errores'][] = $this->aviso(null, null, null, 'Marca cuál es la columna del DNI: sin ella no se sabe de quién es cada fila.');
        } elseif (count($mapa) < 2) {
            $r['errores'][] = $this->aviso(null, null, null, 'Asigna al menos una columna además del DNI.');
        }
        if ($r['errores']) {
            return $r;
        }

        // ── Lo que ya hay en el sistema ──────────────────────────
        $catalogos   = ColumnasDeEmpleado::catalogos();
        $rolEmpleado = $catalogos['rol']['empleado']['id'] ?? null;
        $esAdmin     = $request->user()->rol?->nombre === 'admin';

        $existentes = Empleado::with('usuario:id,empleado_id,email')->get()
            ->keyBy(fn (Empleado $e) => LectorDeCeldas::claveDni($e->dni));
        $correos = User::whereNotNull('email')->get(['email', 'empleado_id'])
            ->mapWithKeys(fn (User $u) => [mb_strtolower($u->email) => $u->empleado_id])->all();
        $cvs = collect($datos['cvs'] ?? [])->map(fn ($d) => LectorDeCeldas::claveDni($d))->unique()->values();

        $reglasAlta   = $this->sinConsultas(AltaDeEmpleado::reglas());
        $reglasCambio = $this->comoOpcionales($reglasAlta);
        $atributos    = ColumnasDeEmpleado::atributos();

        $vistosDni    = [];
        $vistosCorreo = [];
        $bloqueadas   = [];

        // ── Fila por fila ────────────────────────────────────────
        foreach ($datos['filas'] as $fila) {
            $numero = (int) $fila['numero'];
            $celdas = array_values($fila['celdas'] ?? []);

            if (LectorDeCeldas::filaVacia($celdas)) {
                continue;
            }
            $r['filas_leidas']++;

            $dni = LectorDeCeldas::dni($celdas[$mapa['dni']] ?? null);
            if ($dni['vacio']) {
                $r['errores'][] = $this->aviso($numero, null, $titulos['dni'], 'Falta el DNI.');
                continue;
            }

            $clave = collect($dni['claves'])->first(fn ($c) => $existentes->has($c)) ?? $dni['claves'][count($dni['claves']) - 1];
            if (! preg_match('/^[0-9]{8}$/', $clave)) {
                $r['errores'][] = $this->aviso($numero, $dni['texto'], $titulos['dni'], "«{$dni['texto']}» no es un DNI: son 8 cifras.");
                continue;
            }
            if (isset($vistosDni[$clave])) {
                $r['errores'][] = $this->aviso($numero, $clave, $titulos['dni'], "Este DNI ya aparece en la fila {$vistosDni[$clave]}: cada trabajador va una sola vez.");
                continue;
            }
            $vistosDni[$clave] = $numero;

            // Cada celda con algo escrito, ya leída.
            $valores  = [];
            $conError = false;
            foreach ($mapa as $campo => $indice) {
                if ($campo === 'dni') {
                    continue;
                }
                $crudo = $celdas[$indice] ?? null;
                if (LectorDeCeldas::vacia($crudo)) {
                    continue;
                }
                $lectura = ColumnasDeEmpleado::leer($campo, $crudo, $catalogos);
                if ($lectura['omitir']) {
                    continue;
                }
                if ($lectura['error']) {
                    $r['errores'][] = $this->aviso($numero, $clave, $titulos[$campo], $lectura['error']);
                    $conError = true;
                    continue;
                }
                $valores[$campo] = $lectura['valor'];
            }
            if ($conError) {
                continue;
            }

            $existente = $existentes->get($clave);
            $tieneCv   = $cvs->contains($clave);

            if (! $existente) {
                $this->filaDeAlta($r, $numero, $clave, $valores, $catalogos, $rolEmpleado, $esAdmin, $reglasAlta, $atributos, $correos, $vistosCorreo, $tieneCv);
            } else {
                $this->filaDeCambio($r, $numero, $clave, $valores, $existente, $catalogos, $reglasCambio, $atributos, $correos, $vistosCorreo, $bloqueadas, $tieneCv);
            }
        }

        // ── Lo que conviene saber ────────────────────────────────
        if ($bloqueadas) {
            $nombres = implode(', ', array_map(fn ($c) => '«' . ColumnasDeEmpleado::CAMPOS[$c]['titulo'] . '»', array_keys($bloqueadas)));
            $r['advertencias'][] = $this->aviso(null, null, null,
                "{$nombres} no se cambian a quien ya existe: el contrato se renueva desde Contratos y el rol se cambia desde Usuarios. Esas celdas se ignoran.");
        }
        if (collect($r['filas'])->contains('modo', 'alta')) {
            $r['advertencias'][] = $this->aviso(null, null, null,
                'Cada trabajador nuevo entra con su DNI como contraseña provisional, y el sistema le pide cambiarla la primera vez.');
        }

        foreach ($cvs as $dniCv) {
            if (isset($vistosDni[$dniCv]) || $existentes->has($dniCv)) {
                $r['cvs']++;
            } else {
                $r['cvs_sin_trabajador'][] = $dniCv;
            }
        }
        if ($r['cvs_sin_trabajador']) {
            $r['advertencias'][] = $this->aviso(null, null, null,
                'Estas hojas de vida no son de ningún trabajador (ni del archivo ni del sistema) y no se subirán: DNI ' . implode(', ', $r['cvs_sin_trabajador']) . '.');
        }

        return $r;
    }

    /** Un trabajador nuevo: tiene que traer todo lo que pide el alta. */
    private function filaDeAlta(array &$r, int $numero, string $dni, array $valores, array $catalogos, ?string $rolEmpleado,
        bool $esAdmin, array $reglas, array $atributos, array $correos, array &$vistosCorreo, bool $tieneCv): void
    {
        $faltan = array_values(array_filter(ColumnasDeEmpleado::REQUERIDOS_ALTA, fn ($c) => ! array_key_exists($c, $valores)));
        if ($faltan) {
            $nombres = implode(', ', array_map(fn ($c) => ColumnasDeEmpleado::CAMPOS[$c]['titulo'], $faltan));
            $r['errores'][] = $this->aviso($numero, $dni, null, "Es un trabajador nuevo, y para darlo de alta falta: {$nombres}.");
            return;
        }

        $datos = ['dni' => $dni];
        foreach ($valores as $campo => $valor) {
            $datos[ColumnasDeEmpleado::atributoDe($campo)] = $valor;
        }
        // Sin columna de Rol, entra como lo que es casi todo el personal.
        $datos['rol_id'] = $datos['rol_id'] ?? $rolEmpleado;
        $datos = AltaDeEmpleado::limpiarAfp($datos);

        $problemas = [];
        if (($catalogos['porId'][$datos['rol_id']] ?? '') === 'admin' && ! $esAdmin) {
            $problemas[] = 'Solo un Administrador puede dar el rol de Administrador.';
        }

        $validador = Validator::make($datos, $reglas, AltaDeEmpleado::mensajes(), $atributos);
        if ($validador->fails()) {
            $problemas = array_merge($problemas, $validador->errors()->all());
        }

        $correo = mb_strtolower((string) $datos['email']);
        if (array_key_exists($correo, $correos)) {
            $problemas[] = "El correo {$datos['email']} ya lo usa otra cuenta.";
        } elseif (isset($vistosCorreo[$correo])) {
            $problemas[] = "El correo {$datos['email']} ya está en la fila {$vistosCorreo[$correo]}.";
        }

        if ($problema = AltaDeEmpleado::problemaCargoArea($datos['cargo_id'] ?? null, $datos['area_id'] ?? null)) {
            $problemas[] = $problema;
        }

        if ($problemas) {
            foreach ($problemas as $problema) {
                $r['errores'][] = $this->aviso($numero, $dni, null, $problema);
            }
            return;
        }

        $vistosCorreo[$correo] = $numero;

        $resumen = array_values(array_filter(['area', 'cargo', 'sede', 'tipo_contrato', 'sueldo_base', 'email'], fn ($c) => array_key_exists($c, $valores)));

        $r['filas'][] = [
            'fila'    => $numero,
            'dni'     => $dni,
            'nombre'  => trim($valores['apellido'] . ', ' . $valores['nombre']),
            'modo'    => 'alta',
            'cv'      => $tieneCv,
            'cambios' => array_map(fn ($c) => [
                'campo'   => $c,
                'titulo'  => ColumnasDeEmpleado::CAMPOS[$c]['titulo'],
                'antes'   => null,
                'despues' => ColumnasDeEmpleado::mostrar($c, $valores[$c], $catalogos),
            ], $resumen),
            '_datos'  => $datos,
        ];
    }

    /** Alguien que ya existe: solo lo escrito, y solo lo que de verdad cambia. */
    private function filaDeCambio(array &$r, int $numero, string $dni, array $valores, Empleado $existente, array $catalogos,
        array $reglas, array $atributos, array $correos, array &$vistosCorreo, array &$bloqueadas, bool $tieneCv): void
    {
        foreach (ColumnasDeEmpleado::NO_SE_ACTUALIZAN as $campo) {
            if (array_key_exists($campo, $valores)) {
                unset($valores[$campo]);
                $bloqueadas[$campo] = true;
            }
        }

        $campos = [];
        foreach ($valores as $campo => $valor) {
            $campos[ColumnasDeEmpleado::atributoDe($campo)] = $valor;
        }
        $campos = AltaDeEmpleado::limpiarAfp($campos);

        if (! $campos) {
            $r['sin_cambios']++;
            return;
        }

        // Si pasa a AFP y no trae la AFP o el CUSPP, valen los que ya tenía.
        $paraValidar = $campos;
        if (($campos['sistema_pensiones'] ?? null) === 'AFP') {
            $paraValidar += ['afp' => $existente->afp, 'cuspp' => $existente->cuspp];
        }

        $problemas = [];
        $validador = Validator::make($paraValidar, $reglas, AltaDeEmpleado::mensajes(), $atributos);
        if ($validador->fails()) {
            $problemas = $validador->errors()->all();
        }

        if (isset($campos['email'])) {
            $correo = mb_strtolower($campos['email']);
            if (! $existente->usuario) {
                $problemas[] = 'Este trabajador no tiene cuenta de acceso: el correo se le asigna desde Usuarios.';
            } elseif (array_key_exists($correo, $correos) && $correos[$correo] !== $existente->id) {
                $problemas[] = "El correo {$campos['email']} ya lo usa otra cuenta.";
            } elseif (isset($vistosCorreo[$correo])) {
                $problemas[] = "El correo {$campos['email']} ya está en la fila {$vistosCorreo[$correo]}.";
            }
        }

        if ($problema = AltaDeEmpleado::problemaCargoArea($campos['cargo_id'] ?? $existente->cargo_id, $campos['area_id'] ?? $existente->area_id)) {
            $problemas[] = $problema;
        }

        if ($problemas) {
            foreach ($problemas as $problema) {
                $r['errores'][] = $this->aviso($numero, $dni, null, $problema);
            }
            return;
        }

        $cambios   = [];
        $queCambia = [];
        foreach ($campos as $atributo => $nuevo) {
            $campo  = ColumnasDeEmpleado::campoDeAtributo($atributo);
            $actual = $atributo === 'email' ? $existente->usuario?->email : $existente->getAttribute($atributo);

            if (ColumnasDeEmpleado::iguales($campo, $actual, $nuevo)) {
                continue;
            }

            $queCambia[$atributo] = $nuevo;
            $cambios[] = [
                'campo'   => $campo,
                'titulo'  => ColumnasDeEmpleado::CAMPOS[$campo]['titulo'],
                'antes'   => ColumnasDeEmpleado::mostrar($campo, $actual, $catalogos),
                'despues' => ColumnasDeEmpleado::mostrar($campo, $nuevo, $catalogos),
            ];
        }

        if (! $cambios) {
            $r['sin_cambios']++;
            return;
        }

        if (isset($queCambia['email'])) {
            $vistosCorreo[mb_strtolower($queCambia['email'])] = $numero;
        }

        $r['filas'][] = [
            'fila'      => $numero,
            'dni'       => $dni,
            'nombre'    => trim($existente->apellido . ', ' . $existente->nombre),
            'modo'      => 'actualizar',
            'cv'        => $tieneCv,
            'cambios'   => $cambios,
            '_empleado' => $existente,
            '_campos'   => $queCambia,
        ];
    }

    /** Lo que ve la pantalla: sin los modelos ni las claves internas. */
    private function paraPantalla(array $r): array
    {
        $filas = collect($r['filas']);

        return [
            'resumen' => [
                'filas_leidas'       => $r['filas_leidas'],
                'altas'              => $filas->where('modo', 'alta')->count(),
                'actualizaciones'    => $filas->where('modo', 'actualizar')->count(),
                'sin_cambios'        => $r['sin_cambios'],
                'errores'            => count($r['errores']),
                'advertencias'       => count($r['advertencias']),
                'cvs'                => $r['cvs'],
                'cvs_sin_trabajador' => $r['cvs_sin_trabajador'],
            ],
            'filas' => $filas->map(fn ($f) => array_filter($f, fn ($clave) => ! str_starts_with($clave, '_'), ARRAY_FILTER_USE_KEY))->values()->all(),
            'errores'      => $r['errores'],
            'advertencias' => $r['advertencias'],
        ];
    }

    /**
     * Las reglas del alta sin las que consultan la base fila por fila
     * (unique, exists): los DNI, los correos y los catálogos ya se revisan
     * aquí contra lo cargado en memoria. Con 500 filas eran mil consultas.
     */
    private function sinConsultas(array $reglas): array
    {
        return array_map(
            fn ($regla) => is_string($regla)
                ? implode('|', array_filter(explode('|', $regla), fn ($p) => ! str_starts_with($p, 'unique:') && ! str_starts_with($p, 'exists:') && $p !== 'unique'))
                : $regla,
            $reglas
        );
    }

    /** Para quien ya existe, nada es obligatorio: se valida solo lo que trae. */
    private function comoOpcionales(array $reglas): array
    {
        return array_map(
            fn ($regla) => is_string($regla)
                ? implode('|', array_map(
                    fn ($p) => $p === 'required' ? 'sometimes' : $p,
                    array_filter(explode('|', $regla), fn ($p) => ! str_starts_with($p, 'required_unless:'))
                ))
                : $regla,
            $reglas
        );
    }

    private function aviso(?int $fila, ?string $dni, ?string $columna, string $mensaje): array
    {
        return ['fila' => $fila, 'dni' => $dni, 'columna' => $columna, 'mensaje' => $mensaje];
    }
}
