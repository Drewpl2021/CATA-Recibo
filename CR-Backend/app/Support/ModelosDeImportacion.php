<?php

namespace App\Support;

use App\Models\Empleado;
use App\Models\Planilla;

/**
 * Los Excel modelo de las dos importaciones, para quien no tiene un archivo
 * propio o no sabe cómo armarlo.
 *
 * Cada modelo trae, en la primera hoja, justo los títulos que el sistema
 * reconoce sin preguntar; en la segunda, las instrucciones en castellano
 * llano. La importación lee solo la primera hoja, así que las instrucciones
 * no se cuelan como datos.
 */
final class ModelosDeImportacion
{
    /** Hasta qué fila llegan las listas desplegables. */
    private const FILAS_CON_AYUDA = 1000;

    private const HOJA_LISTAS = 'Listas';

    /**
     * Empleados: los mismos títulos que "Descargar empleados", vacío, con
     * listas para todo lo que tiene que existir o tiene valores fijos.
     */
    public static function empleados(): LibroExcel
    {
        $campos = ColumnasDeEmpleado::CAMPOS;
        $listas = ColumnasDeEmpleado::valoresDeLista();

        $titulos = [];
        $estiloTitulos = [];
        $estiloColumnas = [];
        $anchos = [];
        $validaciones = [];
        $filasListas = [[]];
        $hastaFila = self::FILAS_CON_AYUDA + 1;

        foreach (array_keys($campos) as $c => $campo) {
            $definicion = $campos[$campo];
            $titulos[] = $definicion['titulo'];
            $estiloTitulos[$c] = $campo === 'dni' || in_array($campo, ColumnasDeEmpleado::REQUERIDOS_ALTA, true)
                ? LibroExcel::TITULO
                : LibroExcel::TITULO_OPCIONAL;

            // Texto: el DNI no pierde su cero, y "05/03/1990" no se convierte
            // en 3 de mayo en una computadora configurada en inglés.
            $estiloColumnas[$c] = match ($definicion['tipo']) {
                'dni', 'digitos', 'fecha' => LibroExcel::TEXTO,
                'monto'                   => LibroExcel::MONTO,
                default                   => in_array($campo, ['numero_cuenta'], true) ? LibroExcel::TEXTO : LibroExcel::NORMAL,
            };

            $anchos[$c] = match (true) {
                in_array($campo, ['direccion', 'email', 'institucion_estudios', 'contacto_emergencia_nombre'], true) => 30,
                in_array($campo, ['area', 'cargo', 'especialidad'], true) => 26,
                default => max(13, mb_strlen($definicion['titulo']) + 4),
            };

            // Un catálogo vacío (ninguna sede aún) no lleva lista: la dejaría sin opciones.
            if (! empty($listas[$campo])) {
                $columnaLista = count($filasListas[0]);
                $filasListas[0][$columnaLista] = $definicion['titulo'];
                foreach ($listas[$campo] as $i => $valor) {
                    $filasListas[$i + 1][$columnaLista] = $valor;
                }

                $letra = LibroExcel::columna($c);
                $validaciones[] = LibroExcel::lista(
                    "{$letra}2:{$letra}{$hastaFila}",
                    LibroExcel::rangoDeHoja(self::HOJA_LISTAS, $columnaLista, 2, count($listas[$campo]) + 1),
                    $definicion['titulo'],
                    "Elige un valor de la lista de «{$definicion['titulo']}»."
                );
            }
        }

        // Cada fila de la hoja de listas con todas sus columnas en orden,
        // aunque una lista sea más corta que otra.
        $anchoListas = count($filasListas[0]);
        $filasListas = array_map(
            fn ($fila) => array_replace(array_fill(0, $anchoListas, null), $fila),
            $filasListas
        );

        $libro = new LibroExcel();
        $libro->hoja('Empleados', [$titulos], [
            'anchos' => $anchos,
            'estiloColumnas' => $estiloColumnas,
            'estiloFilas' => [0 => $estiloTitulos],
            'altoFilas' => [0 => 32],
            'congelarPrimeraFila' => true,
            'validaciones' => $validaciones,
        ]);

        $requeridos = array_map(fn ($campo) => $campos[$campo]['titulo'], ColumnasDeEmpleado::REQUERIDOS_ALTA);
        $conLista = array_map(fn ($campo) => $campos[$campo]['titulo'], array_keys($listas));

        self::instrucciones($libro, 'Cómo llenar el modelo de empleados', [
            'Cada fila es un trabajador' => [
                'Con un DNI que el sistema todavía no tiene, se da de alta al trabajador: su ficha, su cuenta y su contrato. Entra con su DNI como contraseña provisional y el sistema le pide cambiarla.',
                'Con un DNI que ya existe, solo se cambian las celdas que tengan algo escrito. Una celda vacía no borra nada.',
                'Todos entran como trabajadores. Si a alguien le toca entrar a RR.HH. o a Administración, eso se le cambia después desde Usuarios.',
            ],
            'Obligatorio para un trabajador nuevo (títulos en azul oscuro)' => [
                'DNI, ' . implode(', ', $requeridos) . '.',
                'Si el contrato es Plazo fijo, Suplencia o Prácticas, pon también el Fin de contrato.',
                'Los títulos en azul claro son opcionales.',
            ],
            'Cómo escribir cada dato' => [
                'Fechas: día/mes/año, por ejemplo 15/03/1990.',
                'DNI: 8 cifras. CUSPP: 11 cifras, obligatorio si aporta a una AFP. Teléfono y CCI: solo números.',
                'Sueldo base: el monto, por ejemplo 2500 o 2500.50.',
                implode(', ', $conLista) . ': elígelos de la lista que aparece en la celda.',
            ],
            'Lo que el Excel no le cambia a quien ya existe' => [
                'Tipo de contrato, fin de contrato y fecha de ingreso. Eso se cambia desde Contratos, para que quede el historial.',
            ],
            'Las hojas de vida' => [
                'No van dentro del Excel. Guarda cada una con el DNI del trabajador en el nombre (por ejemplo 42558107.pdf) y súbelas junto con el Excel, en la misma pantalla.',
            ],
            'Al subirlo' => [
                'Deja los títulos en la primera fila y guarda el archivo como .xlsx.',
                'Antes de guardar nada, el sistema te muestra qué va a pasar con cada trabajador.',
            ],
        ]);

        $libro->hoja(self::HOJA_LISTAS, $filasListas, [
            'oculta' => true,
            'estiloFilas' => [0 => LibroExcel::SUBTITULO],
            'anchos' => array_fill(0, $anchoListas, 26),
        ]);

        return $libro;
    }

    /**
     * Conceptos del mes: la gente que tiene planilla en ese mes, ya puesta,
     * y una columna por cada concepto que se puede importar.
     */
    public static function conceptos(int $mes, int $anio): LibroExcel
    {
        $periodo = Meses::nombre($mes) . ' ' . $anio;

        $trabajadores = Planilla::with('empleado:id,dni,nombre,apellido')
            ->where('mes', $mes)->where('anio', $anio)
            ->get()->pluck('empleado')->filter()->unique('id');
        $conPlanillas = $trabajadores->isNotEmpty();
        if (! $conPlanillas) {
            $trabajadores = Empleado::where('estado', 'activo')->get(['id', 'dni', 'nombre', 'apellido']);
        }
        $trabajadores = $trabajadores->sortBy(fn ($e) => mb_strtolower($e->apellido . ' ' . $e->nombre))->values();

        $conceptos = (new ReconocedorDeColumnas())->catalogoEditable();

        $titulos = ['N°', 'DNI', 'Apellidos y Nombres'];
        $estiloTitulos = [LibroExcel::TITULO, LibroExcel::TITULO, LibroExcel::TITULO];
        $anchos = [6, 12, 34];
        $estiloColumnas = [1 => LibroExcel::TEXTO];
        $colorDeTipo = [
            'bonificacion' => LibroExcel::TITULO,
            'descuento'    => LibroExcel::TITULO_DESCUENTO,
            'adelanto'     => LibroExcel::TITULO_ADELANTO,
            'aportacion'   => LibroExcel::TITULO_APORTACION,
        ];

        foreach ($conceptos as $concepto) {
            $c = count($titulos);
            $titulos[] = $concepto['nombre'];
            $estiloTitulos[$c] = $colorDeTipo[$concepto['tipo']] ?? LibroExcel::TITULO;
            $estiloColumnas[$c] = LibroExcel::MONTO;
            $anchos[$c] = min(30, max(14, mb_strlen($concepto['nombre']) * 0.8));
        }

        $filas = [$titulos];
        foreach ($trabajadores as $i => $e) {
            $filas[] = [$i + 1, (string) $e->dni, trim($e->apellido . ', ' . $e->nombre, ', ')];
        }

        $validaciones = [];
        if (count($titulos) > 3) {
            $hastaFila = max(self::FILAS_CON_AYUDA, $trabajadores->count()) + 1;
            $validaciones[] = LibroExcel::montoEntre(
                'D2:' . LibroExcel::columna(count($titulos) - 1) . $hastaFila,
                0,
                999999.99,
                'Monto',
                'Escribe solo el monto, por ejemplo 120 o 35.50. Un 0 le quita el concepto; vacía no cambia nada.'
            );
        }

        $libro = new LibroExcel();
        $libro->hoja('Conceptos', $filas, [
            'anchos' => $anchos,
            'estiloColumnas' => $estiloColumnas,
            'estiloFilas' => [0 => $estiloTitulos],
            'altoFilas' => [0 => 45],
            'congelarPrimeraFila' => true,
            'validaciones' => $validaciones,
        ]);

        $tiposPresentes = array_unique(array_column($conceptos, 'tipo'));
        $colores = array_filter([
            in_array('bonificacion', $tiposPresentes, true) ? 'azul los ingresos' : null,
            in_array('descuento', $tiposPresentes, true) ? 'rojo los descuentos' : null,
            in_array('adelanto', $tiposPresentes, true) ? 'marrón los adelantos' : null,
            in_array('aportacion', $tiposPresentes, true) ? 'gris los aportes del empleador' : null,
        ]);

        self::instrucciones($libro, "Cómo llenar el modelo de conceptos de {$periodo}", [
            'Quiénes están' => [$conPlanillas
                ? "La lista trae a los {$trabajadores->count()} trabajadores que tienen planilla en {$periodo}."
                : "{$periodo} todavía no tiene planillas, así que la lista trae al personal activo. Genera la planilla del mes antes de importar."],
            'En cada celda de monto' => [
                'Vacía: no cambia lo que ya tenga el trabajador.',
                '0: le quita ese concepto.',
                'Un monto: se lo pone, por ejemplo 120 o 35.50.',
            ],
            'Las columnas' => [
                'Hay una columna por cada concepto que se puede importar, con el color de su tipo: ' . implode(', ', $colores) . '.',
                'Puedes borrar las columnas que no uses. También puedes agregar columnas con los títulos que ustedes usan (por ejemplo «Movilidad»): el sistema te propone a qué concepto corresponden.',
                'Para que la boleta diga de qué es un monto, agrega al lado una columna «Detalle» con el nombre de esa columna, por ejemplo «Detalle movilidad».',
                'No agregues columnas de ' . implode(', ', ConceptosDePago::NO_EDITABLES) . ': esos montos los calcula el sistema.',
            ],
            'Al subirlo' => [
                "Elige {$periodo} en la pantalla, deja los títulos en la primera fila y guarda el archivo como .xlsx.",
                'Antes de guardar nada, el sistema te muestra cómo queda cada planilla.',
            ],
        ]);

        return $libro;
    }

    /** La hoja "Instrucciones": un título y secciones con sus párrafos. */
    private static function instrucciones(LibroExcel $libro, string $titulo, array $secciones): void
    {
        $filas = [[$titulo], []];
        $estilos = [0 => LibroExcel::ENCABEZADO];
        $altos = [0 => 24];

        foreach ($secciones as $subtitulo => $parrafos) {
            $estilos[count($filas)] = LibroExcel::SUBTITULO;
            $filas[] = [$subtitulo];
            foreach ($parrafos as $parrafo) {
                $estilos[count($filas)] = LibroExcel::PARRAFO;
                // Una línea cada ~100 caracteres en una columna de 110 de ancho.
                $altos[count($filas)] = 15 * max(1, (int) ceil(mb_strlen($parrafo) / 100));
                $filas[] = [$parrafo];
            }
            $filas[] = [];
        }

        $libro->hoja('Instrucciones', $filas, [
            'anchos' => [0 => 110],
            'estiloFilas' => $estilos,
            'altoFilas' => $altos,
        ]);
    }
}
