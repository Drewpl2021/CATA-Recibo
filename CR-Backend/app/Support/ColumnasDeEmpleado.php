<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Rol;
use App\Models\Sede;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Los datos de la ficha de un trabajador que se pueden traer desde un Excel.
 *
 * Los títulos son los mismos que salen en "Descargar empleados", a propósito:
 * RR.HH. descarga la lista, la corrige en Excel y la vuelve a subir. Además
 * se aceptan los nombres con que se suelen escribir a mano ("Celular",
 * "Puesto", "Sueldo").
 *
 * A diferencia de los conceptos de pago, aquí el vocabulario es cerrado: una
 * ficha no inventa campos nuevos, así que no hay alias que aprender. Y área,
 * cargo, sede y rol tienen que EXISTIR: si no coinciden se sugiere el más
 * parecido, pero nunca se crean solos —un error de tipeo llenaría el sistema
 * de áreas basura—.
 */
final class ColumnasDeEmpleado
{
    /**
     * campo => título (el de la descarga), nombres aceptados ya normalizados,
     * y cómo se lee la celda.
     */
    public const CAMPOS = [
        'dni'                          => ['titulo' => 'DNI', 'tipo' => 'dni', 'alias' => ['dni', 'documento', 'nro documento', 'numero de documento', 'n documento', 'doc']],
        'apellido'                     => ['titulo' => 'Apellidos', 'tipo' => 'texto', 'alias' => ['apellidos', 'apellido']],
        'nombre'                       => ['titulo' => 'Nombres', 'tipo' => 'texto', 'alias' => ['nombres', 'nombre']],
        'fecha_nacimiento'             => ['titulo' => 'Fecha de nacimiento', 'tipo' => 'fecha', 'alias' => ['fecha de nacimiento', 'fecha nacimiento', 'nacimiento', 'f nacimiento']],
        'telefono'                     => ['titulo' => 'Teléfono', 'tipo' => 'digitos', 'alias' => ['telefono', 'celular', 'movil', 'telefono celular']],
        'direccion'                    => ['titulo' => 'Dirección', 'tipo' => 'texto', 'alias' => ['direccion', 'domicilio']],
        'email'                        => ['titulo' => 'Correo', 'tipo' => 'correo', 'alias' => ['correo', 'email', 'correo electronico', 'e mail']],
        'area'                         => ['titulo' => 'Área', 'tipo' => 'catalogo', 'alias' => ['area']],
        'cargo'                        => ['titulo' => 'Cargo', 'tipo' => 'catalogo', 'alias' => ['cargo', 'puesto']],
        'sede'                         => ['titulo' => 'Sede', 'tipo' => 'catalogo', 'alias' => ['sede', 'local']],
        'fecha_ingreso'                => ['titulo' => 'Fecha de ingreso', 'tipo' => 'fecha', 'alias' => ['fecha de ingreso', 'fecha ingreso', 'ingreso', 'f ingreso']],
        // Para registrar a quien ya se fue y guardar sus boletas de antes.
        'estado'                       => ['titulo' => 'Estado', 'tipo' => 'opcion', 'alias' => ['estado', 'situacion', 'condicion', 'estado laboral']],
        'fecha_cese'                   => ['titulo' => 'Fecha de cese', 'tipo' => 'fecha', 'alias' => ['fecha de cese', 'fecha cese', 'cese', 'fecha de baja', 'fecha de salida', 'fecha de retiro']],
        'tipo_contrato'                => ['titulo' => 'Tipo de contrato', 'tipo' => 'opcion', 'alias' => ['tipo de contrato', 'tipo contrato', 'contrato', 'modalidad']],
        'fecha_fin_contrato'           => ['titulo' => 'Fin de contrato', 'tipo' => 'fecha', 'alias' => ['fin de contrato', 'fecha fin de contrato', 'fecha de fin de contrato', 'fin del contrato', 'vencimiento']],
        'sueldo_base'                  => ['titulo' => 'Sueldo base', 'tipo' => 'monto', 'alias' => ['sueldo base', 'sueldo', 'remuneracion basica', 'haber basico', 'basico']],
        'sistema_pensiones'            => ['titulo' => 'Sistema de pensión', 'tipo' => 'opcion', 'alias' => ['sistema de pension', 'sistema pensionario', 'pension', 'regimen pensionario']],
        'afp'                          => ['titulo' => 'AFP', 'tipo' => 'opcion', 'alias' => ['afp']],
        // Flujo (la normal desde 2013) o Mixta (de antes: la AFP cobra su
        // comisión directo del fondo, así que no se le descuenta nada de
        // comisión en planilla). Vacía = Flujo, que es el único esquema
        // que existe para quien se afilió de 2013 en adelante.
        'tipo_comision_afp'            => ['titulo' => 'Tipo de comisión AFP', 'tipo' => 'opcion', 'alias' => ['tipo de comision afp', 'tipo comision afp', 'comision afp', 'tipo comision', 'regimen de comision']],
        // Texto y no dígitos: el CUSPP lleva letras (052281JHPMM4).
        'cuspp'                        => ['titulo' => 'CUSPP', 'tipo' => 'texto', 'alias' => ['cuspp']],
        'forma_pago'                   => ['titulo' => 'Forma de pago', 'tipo' => 'opcion', 'alias' => ['forma de pago', 'pago']],
        'entidad_financiera'           => ['titulo' => 'Banco', 'tipo' => 'texto', 'alias' => ['banco', 'entidad financiera']],
        'numero_cuenta'                => ['titulo' => 'N° de cuenta', 'tipo' => 'texto', 'alias' => ['n de cuenta', 'no de cuenta', 'nro de cuenta', 'numero de cuenta', 'cuenta', 'n cuenta']],
        'cci'                          => ['titulo' => 'CCI', 'tipo' => 'digitos', 'alias' => ['cci']],
        'tiene_hijos'                  => ['titulo' => 'Tiene hijos', 'tipo' => 'si_no', 'alias' => ['tiene hijos', 'hijos']],
        // El Diezmo se le aplica a TODO el personal por defecto (10% del
        // sueldo, ver PaymentConceptSeeder): esta columna es la excepción.
        // Vacía o "Sí" = se le sigue aplicando; solo "No" lo excluye.
        'aplica_diezmo'                => ['titulo' => 'Diezmo', 'tipo' => 'si_no', 'alias' => ['diezmo', 'aplica diezmo', 'descuento diezmo']],
        'nivel_estudios'               => ['titulo' => 'Nivel de estudios', 'tipo' => 'opcion', 'alias' => ['nivel de estudios', 'estudios', 'grado de instruccion']],
        'especialidad'                 => ['titulo' => 'Especialidad', 'tipo' => 'texto', 'alias' => ['especialidad', 'profesion']],
        'institucion_estudios'         => ['titulo' => 'Institución donde estudió', 'tipo' => 'texto', 'alias' => ['institucion donde estudio', 'institucion', 'universidad']],
        'contacto_emergencia_nombre'   => ['titulo' => 'Contacto de emergencia', 'tipo' => 'texto', 'alias' => ['contacto de emergencia', 'nombre del contacto de emergencia', 'contacto emergencia']],
        'contacto_emergencia_telefono' => ['titulo' => 'Teléfono del contacto', 'tipo' => 'digitos', 'alias' => ['telefono del contacto', 'telefono del contacto de emergencia', 'telefono de emergencia']],
    ];

    /** Lo que hace falta para dar de alta a alguien nuevo (además del DNI). */
    public const REQUERIDOS_ALTA = [
        'apellido', 'nombre', 'fecha_nacimiento', 'telefono', 'direccion', 'email',
        'area', 'cargo', 'sede', 'fecha_ingreso', 'sueldo_base', 'tipo_contrato',
    ];

    /**
     * Lo que hace falta para registrar a alguien que YA SE FUE. De un
     * trabajador de hace años RR.HH. no suele tener ni el teléfono ni el
     * correo, y exigirlos era dejar sus boletas sin dónde guardarse.
     */
    public const REQUERIDOS_ALTA_CESADO = ['apellido', 'nombre', 'fecha_ingreso', 'fecha_cese'];

    /**
     * Lo que NO se cambia desde el Excel a quien ya existe: el contrato se
     * renueva desde Contratos, que cierra el anterior y deja historial.
     */
    public const NO_SE_ACTUALIZAN = ['tipo_contrato', 'fecha_fin_contrato', 'fecha_ingreso'];

    /** Títulos que acompañan a la ficha pero no se importan. */
    private const INFORMATIVAS = ['n', 'no', 'nro', 'numero', 'item', 'edad'];

    private const OPCIONES = [
        // "Cesado" es como lo dice la planilla; "inactivo" es como lo guarda el sistema.
        'estado' => [
            'activo' => 'activo', 'activa' => 'activo', 'vigente' => 'activo',
            'cesado' => 'inactivo', 'cesada' => 'inactivo', 'cese' => 'inactivo', 'inactivo' => 'inactivo', 'inactiva' => 'inactivo',
            'de baja' => 'inactivo', 'baja' => 'inactivo', 'dado de baja' => 'inactivo', 'retirado' => 'inactivo', 'retirada' => 'inactivo',
        ],
        'tipo_contrato' => [
            // "Plazo indeterminado" es como lo escribe RR.HH. a veces —las
            // mismas dos palabras de "Plazo fijo", solo que al revés—, así
            // que es un sinónimo seguro. "Contratado"/"Contrato" también:
            // confirmado que en el colegio significa Plazo fijo, con su
            // "Fin de contrato" aparte, en su propia columna (la fecha de
            // verdad, no la palabra).
            'indeterminado' => 'indeterminado', 'plazo indeterminado' => 'indeterminado',
            'plazo fijo' => 'plazo_fijo', 'plazo_fijo' => 'plazo_fijo',
            'contratado' => 'plazo_fijo', 'contrato' => 'plazo_fijo',
            'suplencia' => 'suplencia', 'practicas' => 'practicas', 'practica' => 'practicas',
        ],
        'sistema_pensiones' => [
            'afp' => 'AFP', 'spp' => 'AFP', 'onp' => 'ONP', 'snp' => 'ONP',
            'no aporta' => null, 'ninguno' => null, 'sin pension' => null, 'no' => null,
        ],
        'afp' => ['habitat' => 'Habitat', 'integra' => 'Integra', 'prima' => 'Prima', 'profuturo' => 'Profuturo'],
        'tipo_comision_afp' => [
            'flujo' => 'flujo', 'por flujo' => 'flujo', 'comision por flujo' => 'flujo',
            'mixta' => 'mixta', 'mixto' => 'mixta', 'comision mixta' => 'mixta',
        ],
        'forma_pago' => [
            'banco' => 'banco', 'deposito' => 'banco', 'transferencia' => 'banco', 'efectivo' => 'efectivo',
            'honorarios' => 'honorarios', 'recibo por honorarios' => 'honorarios', 'otro' => 'otro',
        ],
        'nivel_estudios' => [
            'primaria' => 'primaria', 'secundaria' => 'secundaria', 'tecnico' => 'tecnico',
            'universitario' => 'universitario', 'universitaria' => 'universitario',
            'maestria' => 'maestria', 'doctorado' => 'doctorado',
        ],
    ];

    /** Cómo se lee cada valor guardado, y qué se acepta al escribirlo. */
    private const LEGIBLE = [
        'estado'            => ['activo' => 'Activo', 'inactivo' => 'Cesado'],
        'tipo_contrato'     => ['indeterminado' => 'Indeterminado', 'plazo_fijo' => 'Plazo fijo', 'suplencia' => 'Suplencia', 'practicas' => 'Prácticas'],
        'sistema_pensiones' => ['AFP' => 'AFP', 'ONP' => 'ONP'],
        'afp'               => ['Habitat' => 'Habitat', 'Integra' => 'Integra', 'Prima' => 'Prima', 'Profuturo' => 'Profuturo'],
        'tipo_comision_afp' => ['flujo' => 'Flujo', 'mixta' => 'Mixta'],
        'forma_pago'        => ['banco' => 'Banco', 'efectivo' => 'Efectivo', 'honorarios' => 'Recibo por honorarios', 'otro' => 'Otro'],
        'nivel_estudios'    => ['primaria' => 'Primaria', 'secundaria' => 'Secundaria', 'tecnico' => 'Técnico', 'universitario' => 'Universitario', 'maestria' => 'Maestría', 'doctorado' => 'Doctorado'],
    ];

    private const ACEPTA = [
        'estado'            => 'Activo o Cesado',
        'tipo_contrato'     => 'Indeterminado, Plazo fijo, Suplencia o Prácticas',
        'sistema_pensiones' => 'AFP, ONP o No aporta',
        'afp'               => 'Habitat, Integra, Prima o Profuturo',
        'tipo_comision_afp' => 'Flujo o Mixta',
        'forma_pago'        => 'Banco, Efectivo, Honorarios u Otro',
        'nivel_estudios'    => 'Primaria, Secundaria, Técnico, Universitario, Maestría o Doctorado',
    ];

    /**
     * Fechas que pueden no tener fin todavía. En "Fecha de cese" y "Fin de
     * contrato" es normal que RR.HH. escriba "Indeterminado" en vez de
     * dejarlo en blanco —es justo lo que significa un contrato o un
     * trabajador sin fecha de salida—, y eso no es un error de formato:
     * es la respuesta correcta a esa columna. En "Fecha de nacimiento" o
     * "Fecha de ingreso" ese mismo texto SÍ sería un error —todos tienen
     * una—, así que esto no se aplica ahí.
     */
    private const FECHAS_INDETERMINABLES = ['fecha_cese', 'fecha_fin_contrato'];

    /** Cómo se escribe "no tiene fecha" en esas dos columnas. */
    private const SIN_FECHA = [
        'indeterminado', 'indeterminada', 'plazo indeterminado', 'sin fecha',
        'no aplica', 'n/a', 'na', 'ninguna', 'ninguno', 'sigue laborando', 'actualmente laborando',
    ];

    /** El nombre de la columna en la tabla empleados. */
    public static function atributoDe(string $campo): string
    {
        return in_array($campo, ['area', 'cargo', 'sede', 'rol'], true) ? $campo . '_id' : $campo;
    }

    public static function campoDeAtributo(string $atributo): string
    {
        return in_array($atributo, ['area_id', 'cargo_id', 'sede_id', 'rol_id'], true) ? substr($atributo, 0, -3) : $atributo;
    }

    /** Para que los mensajes de validación digan "Sueldo base" y no "sueldo_base". */
    public static function atributos(): array
    {
        $salida = [];
        foreach (self::CAMPOS as $campo => $definicion) {
            $salida[self::atributoDe($campo)] = $definicion['titulo'];
        }

        return $salida;
    }

    /** Los campos, para el desplegable de "qué dato es esta columna". */
    public static function paraPantalla(): array
    {
        return collect(self::CAMPOS)->map(fn ($d, $campo) => [
            'campo'       => $campo,
            'titulo'      => $d['titulo'],
            'obligatorio' => $campo === 'dni' || in_array($campo, self::REQUERIDOS_ALTA, true),
        ])->values()->all();
    }

    /** Qué dato de la ficha es cada columna. */
    public static function reconocer(array $titulos): array
    {
        $usados = [];
        $salida = [];

        foreach (array_values($titulos) as $indice => $titulo) {
            $normal  = ReconocedorDeColumnas::normalizar((string) $titulo);
            $columna = ['indice' => $indice, 'titulo' => trim((string) $titulo), 'campo' => null, 'estado' => 'informativa', 'motivo' => ''];

            if ($normal === '') {
                $columna['estado'] = 'sin_titulo';
                $columna['motivo'] = 'La columna no tiene título: se ignora.';
            } elseif (str_contains($normal, 'apellido') && str_contains($normal, 'nombre')) {
                $columna['motivo'] = 'Los apellidos y los nombres tienen que venir en dos columnas separadas.';
            } elseif (in_array($normal, ['rol', 'perfil'], true)) {
                // La descarga de empleados trae esta columna; se ignora sin ruido.
                $columna['motivo'] = 'El rol no se importa: todos entran como «empleado». Se cambia desde Usuarios.';
            } else {
                [$campo, $origen] = self::campoDe($normal, (string) $titulo);

                if ($campo && isset($usados[$campo])) {
                    $columna['motivo'] = 'Ya hay otra columna de «' . self::CAMPOS[$campo]['titulo'] . '»: esta se ignora.';
                } elseif ($campo) {
                    $usados[$campo]     = true;
                    $columna['campo']   = $campo;
                    $columna['estado']  = 'reconocida';
                    $columna['motivo']  = $origen === 'exacto'
                        ? 'Dato de la ficha: «' . self::CAMPOS[$campo]['titulo'] . '».'
                        : 'Se parece a «' . self::CAMPOS[$campo]['titulo'] . '».';
                } else {
                    $columna['motivo'] = 'No es un dato de la ficha: se ignora. Si lo es, elige cuál.';
                }
            }

            $salida[] = $columna;
        }

        return $salida;
    }

    /** @return array{0: ?string, 1: ?string} campo y cómo se reconoció */
    private static function campoDe(string $normal, string $titulo): array
    {
        foreach (self::CAMPOS as $campo => $definicion) {
            if (in_array($normal, $definicion['alias'], true)) {
                return [$campo, 'exacto'];
            }
        }

        if (in_array($normal, self::INFORMATIVAS, true)) {
            return [null, null];
        }

        // Nunca adivina entre dos: con un rival cerca, no reconoce.
        $mejor = null;
        $primero = $segundo = 0.0;
        foreach (self::CAMPOS as $campo => $definicion) {
            $puntaje = max(array_map(fn ($alias) => ReconocedorDeColumnas::similitud($titulo, $alias), $definicion['alias']));
            if ($puntaje > $primero) {
                [$segundo, $primero, $mejor] = [$primero, $puntaje, $campo];
            } elseif ($puntaje > $segundo) {
                $segundo = $puntaje;
            }
        }

        return $mejor && $primero >= 0.85 && $segundo < 0.75 ? [$mejor, 'parecido'] : [null, null];
    }

    /**
     * Los valores de las listas desplegables del Excel modelo: lo que tiene
     * que existir (área, cargo, sede) y lo que tiene valores fijos, escrito
     * tal como lo acepta leer().
     *
     * @return array<string, string[]> campo => valores, en el orden de CAMPOS
     */
    public static function valoresDeLista(): array
    {
        $nombres = fn (string $modelo) => $modelo::query()->orderBy('nombre')->pluck('nombre')->all();

        return [
            'area'              => $nombres(Area::class),
            'cargo'             => $nombres(Cargo::class),
            'sede'              => $nombres(Sede::class),
            'estado'            => array_values(self::LEGIBLE['estado']),
            'tipo_contrato'     => array_values(self::LEGIBLE['tipo_contrato']),
            'sistema_pensiones' => [...array_values(self::LEGIBLE['sistema_pensiones']), 'No aporta'],
            'afp'               => array_values(self::LEGIBLE['afp']),
            'tipo_comision_afp' => array_values(self::LEGIBLE['tipo_comision_afp']),
            'forma_pago'        => array_values(self::LEGIBLE['forma_pago']),
            'tiene_hijos'       => ['Sí', 'No'],
            'aplica_diezmo'     => ['Sí', 'No'],
            'nivel_estudios'    => array_values(self::LEGIBLE['nivel_estudios']),
        ];
    }

    /**
     * Áreas, cargos, sedes y roles, por su nombre normalizado. Los roles
     * aceptan además cómo los llama la gente ("Recursos Humanos").
     */
    public static function catalogos(): array
    {
        $catalogos = ['area' => [], 'cargo' => [], 'sede' => [], 'rol' => [], 'porId' => []];

        foreach (['area' => Area::class, 'cargo' => Cargo::class, 'sede' => Sede::class, 'rol' => Rol::class] as $campo => $modelo) {
            foreach ($modelo::query()->get(['id', 'nombre']) as $fila) {
                $catalogos[$campo][ReconocedorDeColumnas::normalizar($fila->nombre)] = ['id' => $fila->id, 'nombre' => $fila->nombre];
                $catalogos['porId'][$fila->id] = $fila->nombre;
            }
        }

        $comoLosLlaman = [
            'recursos humanos' => 'rrhh', 'rr hh' => 'rrhh', 'administrador' => 'admin', 'administracion' => 'admin',
            'trabajador' => 'empleado', 'docente' => 'empleado', 'personal' => 'empleado',
        ];
        foreach ($comoLosLlaman as $alias => $rol) {
            if (isset($catalogos['rol'][$rol])) {
                $catalogos['rol'][$alias] = $catalogos['rol'][$rol];
            }
        }

        return $catalogos;
    }

    /**
     * Si el cargo o el área que trae el Excel no existen, se crean —a
     * diferencia de sede y rol, que son un puñado fijo de opciones reales
     * (cuatro locales, tres roles) y nunca se inventan solos. Cargo y Área
     * sí crecen con normalidad: un colegio abre una especialidad nueva, y
     * frenar TODA la importación por eso —cuando RR.HH. ya escribió algo
     * razonable— es más trabajo que confiar en lo que puso.
     *
     * No escribe en la base todavía: inventa el id que va a tener (el
     * mismo que usará el cargo/área de verdad si la importación se
     * confirma) y lo dejar visto en $catalogos, como si ya existiera —así
     * leer() no necesita saber que es nuevo, lo encuentra igual. $nuevos
     * es lo que el controlador usa para crearlos de verdad al aplicar, y
     * para avisarle a RR.HH. en la revisión qué se va a crear.
     *
     * El mismo nombre escrito en varias filas usa el MISMO id las veces
     * que aparezca: no se duplica un cargo por repetirlo.
     */
    public static function crearSiFalta(string $campo, ?string $texto, array &$catalogos, array &$nuevos, ?string $areaId = null): ?string
    {
        if ($texto === null || $texto === '') {
            return null;
        }

        $normal = ReconocedorDeColumnas::normalizar($texto);

        if (isset($catalogos[$campo][$normal])) {
            $id = $catalogos[$campo][$normal]['id'];
            // Ya existía, o ya lo había inventado otra fila de este mismo
            // archivo: si es un cargo y ahora aparece con un área distinta,
            // se le suma —"Docente de Inglés" puede valer en Primaria Y en
            // Secundaria si el Excel lo usa en las dos—.
            if ($campo === 'cargo' && $areaId && isset($nuevos['cargo'][$id])) {
                $nuevos['cargo'][$id]['areas'][$areaId] = true;
            }

            return $id;
        }

        $id     = (string) Str::uuid();
        $bonito = mb_convert_case(mb_strtolower($texto), MB_CASE_TITLE, 'UTF-8');

        $catalogos[$campo][$normal] = ['id' => $id, 'nombre' => $bonito];
        $catalogos['porId'][$id]    = $bonito;

        $nuevos[$campo][$id] = $campo === 'cargo'
            ? ['nombre' => $bonito, 'areas' => $areaId ? [$areaId => true] : []]
            : ['nombre' => $bonito];

        return $id;
    }

    /**
     * Lee una celda de un campo.
     *
     * @return array{valor: mixed, error: ?string, omitir: bool}
     */
    public static function leer(string $campo, mixed $crudo, array $catalogos): array
    {
        $definicion = self::CAMPOS[$campo];
        $texto      = LectorDeCeldas::texto($crudo) ?? '';
        $normal     = ReconocedorDeColumnas::normalizar($texto);
        $bien       = fn ($valor) => ['valor' => $valor, 'error' => null, 'omitir' => false];
        $mal        = fn (string $mensaje) => ['valor' => null, 'error' => $mensaje, 'omitir' => false];

        switch ($definicion['tipo']) {
            case 'correo':
                // "Sin cuenta" es lo que escribe la descarga cuando no tiene.
                if ($normal === 'sin cuenta') {
                    return ['valor' => null, 'error' => null, 'omitir' => true];
                }

                return filter_var($texto, FILTER_VALIDATE_EMAIL)
                    ? $bien(mb_strtolower($texto))
                    : $mal("«{$texto}» no es un correo válido.");

            case 'digitos':
                $digitos = LectorDeCeldas::digitos($crudo);

                return ctype_digit((string) $digitos) ? $bien($digitos) : $mal("«{$texto}» tiene que llevar solo números.");

            case 'fecha':
                if (in_array($campo, self::FECHAS_INDETERMINABLES, true) && in_array($normal, self::SIN_FECHA, true)) {
                    // No es un error: es la forma en que RR.HH. dice "todavía
                    // no tiene fecha" en una columna que puede no tenerla.
                    return ['valor' => null, 'error' => null, 'omitir' => true];
                }

                $fecha = LectorDeCeldas::fecha($crudo);

                return $fecha ? $bien($fecha) : $mal("«{$texto}» no es una fecha. Escríbela como día/mes/año, por ejemplo 15/03/2026.");

            case 'monto':
                $monto = LectorDeCeldas::monto($crudo);

                return $monto !== null ? $bien($monto) : $mal("«{$texto}» no es un monto.");

            case 'si_no':
                $valor = LectorDeCeldas::siNo($crudo);

                return $valor !== null ? $bien($valor) : $mal("En «{$definicion['titulo']}» escribe Sí o No.");

            case 'opcion':
                return array_key_exists($normal, self::OPCIONES[$campo])
                    ? $bien(self::OPCIONES[$campo][$normal])
                    : $mal("«{$texto}» no vale en «{$definicion['titulo']}». Se acepta: " . self::ACEPTA[$campo] . '.');

            case 'catalogo':
                if (isset($catalogos[$campo][$normal])) {
                    return $bien($catalogos[$campo][$normal]['id']);
                }

                // Sede y Rol son catálogos fijos y cortos —cuatro locales,
                // tres roles—, así que "Central" o "Osis" sueltos (sin el
                // "CATA" que todos dan por sabido) son casi siempre la
                // misma sede dicha corto, no un error. Si el texto cabe
                // DENTRO del nombre guardado (o al revés) y eso pasa con
                // una sola opción, se acepta directo: no hace falta pedirle
                // a RR.HH. que escriba el nombre completo si ya alcanza
                // para no confundirse con ninguna otra.
                if (in_array($campo, ['sede', 'rol'], true)) {
                    $contenidos = array_filter(
                        $catalogos[$campo],
                        fn ($item) => str_contains(ReconocedorDeColumnas::normalizar($item['nombre']), $normal)
                            || str_contains($normal, ReconocedorDeColumnas::normalizar($item['nombre']))
                    );
                    if (count($contenidos) === 1) {
                        return $bien(reset($contenidos)['id']);
                    }
                }

                $mejor   = null;
                $puntaje = 0.0;
                foreach ($catalogos[$campo] as $item) {
                    $parecido = ReconocedorDeColumnas::similitud($texto, $item['nombre']);
                    if ($parecido > $puntaje) {
                        [$puntaje, $mejor] = [$parecido, $item['nombre']];
                    }
                }
                $que = ['area' => 'ningún área', 'cargo' => 'ningún cargo', 'sede' => 'ninguna sede', 'rol' => 'ningún rol'][$campo];
                $sugerencia = $mejor && $puntaje >= 0.6 ? " ¿Quisiste decir «{$mejor}»?" : '';

                return $mal("No hay {$que} «{$texto}».{$sugerencia}");

            default:
                return $bien($texto);
        }
    }

    /** ¿El valor guardado y el del Excel son lo mismo? */
    public static function iguales(string $campo, mixed $actual, mixed $nuevo): bool
    {
        if ($actual === null || $actual === '') {
            return $nuevo === null || $nuevo === '';
        }

        return match (self::CAMPOS[$campo]['tipo']) {
            'fecha'  => Carbon::parse($actual)->format('Y-m-d') === $nuevo,
            'monto'  => $nuevo !== null && abs((float) $actual - (float) $nuevo) < 0.005,
            'si_no'  => (bool) $actual === (bool) $nuevo,
            'correo' => mb_strtolower((string) $actual) === mb_strtolower((string) $nuevo),
            default  => (string) $actual === (string) $nuevo,
        };
    }

    /** Un valor como lo lee una persona: "S/ 2,500.00", "15/03/2026", "Plazo fijo". */
    public static function mostrar(string $campo, mixed $valor, array $catalogos): string
    {
        if ($campo === 'sistema_pensiones' && ($valor === null || $valor === '')) {
            return 'No aporta';
        }
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match (self::CAMPOS[$campo]['tipo']) {
            'fecha'    => Carbon::parse($valor)->format('d/m/Y'),
            'monto'    => 'S/ ' . number_format((float) $valor, 2),
            'si_no'    => $valor ? 'Sí' : 'No',
            'catalogo' => $catalogos['porId'][$valor] ?? (string) $valor,
            'opcion'   => self::LEGIBLE[$campo][$valor] ?? (string) $valor,
            default    => (string) $valor,
        };
    }
}
