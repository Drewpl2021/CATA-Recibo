<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Rol;
use App\Models\Sede;
use Carbon\Carbon;

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
        'rol'                          => ['titulo' => 'Rol', 'tipo' => 'catalogo', 'alias' => ['rol', 'perfil']],
        'area'                         => ['titulo' => 'Área', 'tipo' => 'catalogo', 'alias' => ['area']],
        'cargo'                        => ['titulo' => 'Cargo', 'tipo' => 'catalogo', 'alias' => ['cargo', 'puesto']],
        'sede'                         => ['titulo' => 'Sede', 'tipo' => 'catalogo', 'alias' => ['sede', 'local']],
        'fecha_ingreso'                => ['titulo' => 'Fecha de ingreso', 'tipo' => 'fecha', 'alias' => ['fecha de ingreso', 'fecha ingreso', 'ingreso', 'f ingreso']],
        'tipo_contrato'                => ['titulo' => 'Tipo de contrato', 'tipo' => 'opcion', 'alias' => ['tipo de contrato', 'tipo contrato', 'contrato', 'modalidad']],
        'fecha_fin_contrato'           => ['titulo' => 'Fin de contrato', 'tipo' => 'fecha', 'alias' => ['fin de contrato', 'fecha fin de contrato', 'fecha de fin de contrato', 'fin del contrato', 'vencimiento']],
        'sueldo_base'                  => ['titulo' => 'Sueldo base', 'tipo' => 'monto', 'alias' => ['sueldo base', 'sueldo', 'remuneracion basica', 'haber basico', 'basico']],
        'sistema_pensiones'            => ['titulo' => 'Sistema de pensión', 'tipo' => 'opcion', 'alias' => ['sistema de pension', 'sistema pensionario', 'pension', 'regimen pensionario']],
        'afp'                          => ['titulo' => 'AFP', 'tipo' => 'opcion', 'alias' => ['afp']],
        'cuspp'                        => ['titulo' => 'CUSPP', 'tipo' => 'digitos', 'alias' => ['cuspp']],
        'forma_pago'                   => ['titulo' => 'Forma de pago', 'tipo' => 'opcion', 'alias' => ['forma de pago', 'pago']],
        'entidad_financiera'           => ['titulo' => 'Banco', 'tipo' => 'texto', 'alias' => ['banco', 'entidad financiera']],
        'numero_cuenta'                => ['titulo' => 'N° de cuenta', 'tipo' => 'texto', 'alias' => ['n de cuenta', 'no de cuenta', 'nro de cuenta', 'numero de cuenta', 'cuenta', 'n cuenta']],
        'cci'                          => ['titulo' => 'CCI', 'tipo' => 'digitos', 'alias' => ['cci']],
        'tiene_hijos'                  => ['titulo' => 'Tiene hijos', 'tipo' => 'si_no', 'alias' => ['tiene hijos', 'hijos']],
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
     * Lo que NO se cambia desde el Excel a quien ya existe: el contrato se
     * renueva desde Contratos (que cierra el anterior y deja historial) y el
     * rol desde Usuarios (dar permisos no es un dato más de la ficha).
     */
    public const NO_SE_ACTUALIZAN = ['tipo_contrato', 'fecha_fin_contrato', 'fecha_ingreso', 'rol'];

    /** Títulos que acompañan a la ficha pero no se importan. */
    private const INFORMATIVAS = ['n', 'no', 'nro', 'numero', 'item', 'estado', 'edad'];

    private const OPCIONES = [
        'tipo_contrato' => [
            'indeterminado' => 'indeterminado', 'plazo fijo' => 'plazo_fijo', 'plazo_fijo' => 'plazo_fijo',
            'suplencia' => 'suplencia', 'practicas' => 'practicas', 'practica' => 'practicas',
        ],
        'sistema_pensiones' => [
            'afp' => 'AFP', 'spp' => 'AFP', 'onp' => 'ONP', 'snp' => 'ONP',
            'no aporta' => null, 'ninguno' => null, 'sin pension' => null, 'no' => null,
        ],
        'afp' => ['habitat' => 'Habitat', 'integra' => 'Integra', 'prima' => 'Prima', 'profuturo' => 'Profuturo'],
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
        'tipo_contrato'     => ['indeterminado' => 'Indeterminado', 'plazo_fijo' => 'Plazo fijo', 'suplencia' => 'Suplencia', 'practicas' => 'Prácticas'],
        'sistema_pensiones' => ['AFP' => 'AFP', 'ONP' => 'ONP'],
        'afp'               => ['Habitat' => 'Habitat', 'Integra' => 'Integra', 'Prima' => 'Prima', 'Profuturo' => 'Profuturo'],
        'forma_pago'        => ['banco' => 'Banco', 'efectivo' => 'Efectivo', 'honorarios' => 'Recibo por honorarios', 'otro' => 'Otro'],
        'nivel_estudios'    => ['primaria' => 'Primaria', 'secundaria' => 'Secundaria', 'tecnico' => 'Técnico', 'universitario' => 'Universitario', 'maestria' => 'Maestría', 'doctorado' => 'Doctorado'],
    ];

    private const ACEPTA = [
        'tipo_contrato'     => 'Indeterminado, Plazo fijo, Suplencia o Prácticas',
        'sistema_pensiones' => 'AFP, ONP o No aporta',
        'afp'               => 'Habitat, Integra, Prima o Profuturo',
        'forma_pago'        => 'Banco, Efectivo, Honorarios u Otro',
        'nivel_estudios'    => 'Primaria, Secundaria, Técnico, Universitario, Maestría o Doctorado',
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
     * que existir (área, cargo, sede, rol) y lo que tiene valores fijos,
     * escrito tal como lo acepta leer(). RR.HH. no ve el rol admin, porque
     * no lo puede dar.
     *
     * @return array<string, string[]> campo => valores, en el orden de CAMPOS
     */
    public static function valoresDeLista(bool $esAdmin): array
    {
        $nombres = fn (string $modelo) => $modelo::query()->orderBy('nombre')->pluck('nombre')->all();

        return [
            'rol'               => array_values(array_filter($nombres(Rol::class), fn ($rol) => $esAdmin || $rol !== 'admin')),
            'area'              => $nombres(Area::class),
            'cargo'             => $nombres(Cargo::class),
            'sede'              => $nombres(Sede::class),
            'tipo_contrato'     => array_values(self::LEGIBLE['tipo_contrato']),
            'sistema_pensiones' => [...array_values(self::LEGIBLE['sistema_pensiones']), 'No aporta'],
            'afp'               => array_values(self::LEGIBLE['afp']),
            'forma_pago'        => array_values(self::LEGIBLE['forma_pago']),
            'tiene_hijos'       => ['Sí', 'No'],
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
