<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Un Excel (.xlsx) armado aquí mismo, para los modelos de importación.
 *
 * Sin librería a propósito: un .xlsx no es más que un zip con XML adentro, y
 * a un modelo le basta con títulos con color, anchos, columnas en formato
 * texto y listas desplegables. PHP trae ZipArchive (la imagen de Docker
 * instala la extensión `zip`).
 *
 * Las listas desplegables son lo que más ayuda: RR.HH. elige el área o el
 * cargo en vez de escribirlo, y la importación no tropieza con un tipeo.
 *
 *   $libro = new LibroExcel();
 *   $libro->hoja('Empleados', $filas, ['congelarPrimeraFila' => true, ...]);
 *   return $libro->descargar('Modelo de empleados.xlsx');
 */
final class LibroExcel
{
    // Los estilos de styles.xml, en el mismo orden que <cellXfs>.
    public const NORMAL            = 0;
    public const TITULO            = 1;  // cabecera azul institucional
    public const TITULO_OPCIONAL   = 2;  // cabecera azul claro
    public const TEXTO             = 3;  // formato texto: "04255810" conserva su cero
    public const MONTO             = 4;  // 1,250.00
    public const ENCABEZADO        = 5;  // el título de una hoja de instrucciones
    public const SUBTITULO         = 6;
    public const PARRAFO           = 7;  // texto largo, ajustado a la columna
    public const TITULO_DESCUENTO  = 8;
    public const TITULO_ADELANTO   = 9;
    public const TITULO_APORTACION = 10;

    private const TIPO_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** @var array<int, array> */
    private array $hojas = [];

    /** @var array<int, string> */
    private array $cadenas = [];

    /** @var array<string, int> */
    private array $indiceCadenas = [];

    /**
     * Agrega una hoja. La primera que se agrega es la que se abre, y la que
     * leen las importaciones.
     *
     * @param  array<int, array<int, string|int|float|null>>  $filas  desde la fila 1
     * @param  array{
     *     anchos?: array<int, float>,
     *     estiloColumnas?: array<int, int>,
     *     estiloFilas?: array<int, int|array<int, int>>,
     *     altoFilas?: array<int, float>,
     *     congelarPrimeraFila?: bool,
     *     oculta?: bool,
     *     validaciones?: array<int, array>,
     * }  $opciones
     *
     * - anchos: columna (desde 0) => ancho.
     * - estiloColumnas: el estilo de lo que se escriba en esa columna,
     *   aunque la celda esté vacía (así una fecha tecleada queda como texto).
     * - estiloFilas: fila (desde 0) => un estilo para toda la fila, o uno por
     *   columna.
     * - validaciones: las de lista() y montoEntre().
     */
    public function hoja(string $nombre, array $filas, array $opciones = []): self
    {
        $this->hojas[] = ['nombre' => mb_substr($nombre, 0, 31), 'filas' => $filas, 'opciones' => $opciones];

        return $this;
    }

    /**
     * Una lista desplegable: lo que se escriba en $rango tiene que ser uno de
     * los valores de $origen ("'Listas'!$A$2:$A$16").
     */
    public static function lista(string $rango, string $origen, string $titulo, string $error): array
    {
        return ['tipo' => 'list', 'rango' => $rango, 'formula1' => $origen, 'titulo' => $titulo, 'error' => $error];
    }

    /** Solo números entre $minimo y $maximo. */
    public static function montoEntre(string $rango, float $minimo, float $maximo, string $titulo, string $error): array
    {
        return [
            'tipo' => 'decimal', 'rango' => $rango, 'formula1' => (string) $minimo, 'formula2' => (string) $maximo,
            'titulo' => $titulo, 'error' => $error,
        ];
    }

    /** 0 → "A", 25 → "Z", 26 → "AA". */
    public static function columna(int $indice): string
    {
        $letras = '';
        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26) . $letras;
        }

        return $letras;
    }

    /** Un rango absoluto de otra hoja, para las listas: 'Listas'!$B$2:$B$16. */
    public static function rangoDeHoja(string $hoja, int $columna, int $desdeFila, int $hastaFila): string
    {
        $letra = self::columna($columna);

        return "'" . str_replace("'", "''", $hoja) . "'!\${$letra}\${$desdeFila}:\${$letra}\${$hastaFila}";
    }

    /** La descarga. El archivo temporal se borra apenas sale. */
    public function descargar(string $nombreArchivo)
    {
        return response()
            ->download($this->guardar(), $nombreArchivo, ['Content-Type' => self::TIPO_XLSX])
            ->deleteFileAfterSend(true);
    }

    /** Lo escribe en un archivo temporal y devuelve su ruta. */
    public function guardar(): string
    {
        if (! $this->hojas) {
            throw new RuntimeException('Un libro de Excel necesita al menos una hoja.');
        }

        $this->cadenas = [];
        $this->indiceCadenas = [];

        // Primero las hojas: al escribirlas se juntan los textos compartidos.
        $hojasXml = array_map(fn ($hoja, $i) => $this->hojaXml($hoja, $i === 0), $this->hojas, array_keys($this->hojas));

        $ruta = tempnam(sys_get_temp_dir(), 'libro');
        $zip  = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo armar el archivo de Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->tiposDeContenido());
        $zip->addFromString('_rels/.rels', self::XML
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->libroXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relacionesDelLibro());
        $zip->addFromString('xl/styles.xml', self::ESTILOS);
        $zip->addFromString('xl/sharedStrings.xml', $this->cadenasXml());
        foreach ($hojasXml as $i => $xml) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $xml);
        }
        $zip->close();

        return $ruta;
    }

    // ── El XML ──────────────────────────────────────────────────────

    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    private const NS = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

    private function hojaXml(array $hoja, bool $esLaPrimera): string
    {
        $o = $hoja['opciones'] + [
            'anchos' => [], 'estiloColumnas' => [], 'estiloFilas' => [], 'altoFilas' => [],
            'congelarPrimeraFila' => false, 'validaciones' => [],
        ];

        $vista = '<sheetView workbookViewId="0"' . ($esLaPrimera ? ' tabSelected="1"' : '') . '>';
        $vista .= $o['congelarPrimeraFila']
            ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
            : '';
        $vista .= '</sheetView>';

        // <cols>: ancho y estilo por columna, en orden y sin repetir.
        $columnas = array_unique(array_merge(array_keys($o['anchos']), array_keys($o['estiloColumnas'])));
        sort($columnas);
        $cols = '';
        foreach ($columnas as $c) {
            $n = $c + 1;
            $cols .= "<col min=\"{$n}\" max=\"{$n}\"";
            $cols .= ' width="' . ($o['anchos'][$c] ?? 12) . '" customWidth="1"';
            if (isset($o['estiloColumnas'][$c])) {
                $cols .= ' style="' . $o['estiloColumnas'][$c] . '"';
            }
            $cols .= '/>';
        }

        $datos = '';
        foreach ($hoja['filas'] as $f => $fila) {
            $r = $f + 1;
            $alto = isset($o['altoFilas'][$f]) ? ' ht="' . $o['altoFilas'][$f] . '" customHeight="1"' : '';
            $datos .= "<row r=\"{$r}\"{$alto}>";
            foreach ($fila as $c => $valor) {
                $estilo = $o['estiloFilas'][$f] ?? null;
                $estilo = is_array($estilo) ? ($estilo[$c] ?? null) : $estilo;
                $estilo ??= $o['estiloColumnas'][$c] ?? self::NORMAL;
                $datos .= $this->celda(self::columna($c) . $r, $valor, $estilo);
            }
            $datos .= '</row>';
        }

        $validaciones = '';
        foreach ($o['validaciones'] as $v) {
            $validaciones .= '<dataValidation type="' . $v['tipo'] . '"'
                . ($v['tipo'] === 'decimal' ? ' operator="between"' : '')
                . ' allowBlank="1" showErrorMessage="1"'
                . ' errorTitle="' . self::escapar($v['titulo']) . '" error="' . self::escapar($v['error']) . '"'
                . ' sqref="' . $v['rango'] . '">'
                . '<formula1>' . self::escapar($v['formula1']) . '</formula1>'
                . (isset($v['formula2']) ? '<formula2>' . self::escapar($v['formula2']) . '</formula2>' : '')
                . '</dataValidation>';
        }

        return self::XML . '<worksheet ' . self::NS . '>'
            . '<sheetViews>' . $vista . '</sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . ($cols ? "<cols>{$cols}</cols>" : '')
            . "<sheetData>{$datos}</sheetData>"
            . ($validaciones ? '<dataValidations count="' . count($o['validaciones']) . "\">{$validaciones}</dataValidations>" : '')
            . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            . '</worksheet>';
    }

    private function celda(string $referencia, string|int|float|null $valor, int $estilo): string
    {
        $s = $estilo ? " s=\"{$estilo}\"" : '';

        if ($valor === null || $valor === '') {
            return $estilo ? "<c r=\"{$referencia}\"{$s}/>" : '';
        }
        if (is_int($valor) || is_float($valor)) {
            return "<c r=\"{$referencia}\"{$s}><v>{$valor}</v></c>";
        }

        // Texto compartido: es lo que leen todas las librerías sin sorpresas.
        if (! isset($this->indiceCadenas[$valor])) {
            $this->indiceCadenas[$valor] = count($this->cadenas);
            $this->cadenas[] = $valor;
        }

        return "<c r=\"{$referencia}\"{$s} t=\"s\"><v>{$this->indiceCadenas[$valor]}</v></c>";
    }

    private function cadenasXml(): string
    {
        $items = array_map(fn ($t) => '<si><t xml:space="preserve">' . self::escapar($t) . '</t></si>', $this->cadenas);

        return self::XML . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' count="' . count($items) . '" uniqueCount="' . count($items) . '">' . implode('', $items) . '</sst>';
    }

    private function libroXml(): string
    {
        $hojas = '';
        foreach ($this->hojas as $i => $hoja) {
            $n = $i + 1;
            $oculta = ! empty($hoja['opciones']['oculta']) ? ' state="hidden"' : '';
            $hojas .= '<sheet name="' . self::escapar($hoja['nombre']) . "\" sheetId=\"{$n}\"{$oculta} r:id=\"rId{$n}\"/>";
        }

        return self::XML . '<workbook ' . self::NS . '>'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . "<sheets>{$hojas}</sheets>"
            . '</workbook>';
    }

    private function relacionesDelLibro(): string
    {
        $base = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
        $rel  = '';
        foreach (array_keys($this->hojas) as $i) {
            $n = $i + 1;
            $rel .= "<Relationship Id=\"rId{$n}\" Type=\"{$base}worksheet\" Target=\"worksheets/sheet{$n}.xml\"/>";
        }
        $total = count($this->hojas);
        $rel .= '<Relationship Id="rId' . ($total + 1) . "\" Type=\"{$base}styles\" Target=\"styles.xml\"/>";
        $rel .= '<Relationship Id="rId' . ($total + 2) . "\" Type=\"{$base}sharedStrings\" Target=\"sharedStrings.xml\"/>";

        return self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rel . '</Relationships>';
    }

    private function tiposDeContenido(): string
    {
        $tipo = 'application/vnd.openxmlformats-officedocument.spreadsheetml.';
        $hojas = '';
        foreach (array_keys($this->hojas) as $i) {
            $hojas .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . "\" ContentType=\"{$tipo}worksheet+xml\"/>";
        }

        return self::XML . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . "<Override PartName=\"/xl/workbook.xml\" ContentType=\"{$tipo}sheet.main+xml\"/>"
            . "<Override PartName=\"/xl/styles.xml\" ContentType=\"{$tipo}styles+xml\"/>"
            . "<Override PartName=\"/xl/sharedStrings.xml\" ContentType=\"{$tipo}sharedStrings+xml\"/>"
            . $hojas
            . '</Types>';
    }

    /** Escapa para XML y quita los caracteres de control que Excel no abre. */
    private static function escapar(string $texto): string
    {
        $limpio = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto) ?? '';

        return htmlspecialchars($limpio, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Los colores salen de la paleta del sistema: el azul institucional
     * (#1B4282) para los títulos, y tonos apagados para distinguir los tipos
     * de concepto sin que la hoja parezca un semáforo.
     */
    private const ESTILOS = self::XML
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
        . '<fonts count="4">'
        .   '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><b/><sz val="15"/><color rgb="FF1B4282"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><b/><sz val="11"/><color rgb="FF1B4282"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="7">'
        .   '<fill><patternFill patternType="none"/></fill>'
        .   '<fill><patternFill patternType="gray125"/></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF1B4282"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF5B7DB1"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF9B2C2C"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF8A5A12"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF4A5568"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        .   '<border><left/><right/><top/><bottom/><diagonal/></border>'
        .   '<border><left style="thin"><color rgb="FFD0D7E2"/></left><right style="thin"><color rgb="FFD0D7E2"/></right>'
        .     '<top style="thin"><color rgb="FFD0D7E2"/></top><bottom style="thin"><color rgb="FFD0D7E2"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="11">'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .   '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .   '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .   '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        .   '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        .   '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        .   '<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .   '<xf numFmtId="0" fontId="1" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .   '<xf numFmtId="0" fontId="1" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}
