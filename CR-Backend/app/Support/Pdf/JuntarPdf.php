<?php

namespace App\Support\Pdf;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Junta varios PDF en uno solo, página por página y con su tamaño, para
 * leerlos o imprimirlos de corrido (las boletas del año, por ejemplo).
 *
 * El PDF que sale es una COPIA para leer: al juntar, la firma digital de
 * cada documento no viaja (vale solo sobre el archivo que se firmó). Los
 * originales firmados se bajan en el .zip.
 */
final class JuntarPdf
{
    /**
     * @param  array<string, string>  $pdfs  nombre => bytes del PDF, en el orden en que van
     * @return array{pdf: string, fallaron: list<string>}  el PDF y los nombres que no se pudieron leer
     */
    public static function juntar(array $pdfs, string $titulo = 'Documentos'): array
    {
        $salida = new Fpdi();
        $salida->SetTitle($titulo, true);
        $salida->SetCreator('CATA-Recibo', true);
        $fallaron = [];

        foreach ($pdfs as $nombre => $bytes) {
            try {
                $paginas = $salida->setSourceFile(StreamReader::createByString($bytes));
                for ($n = 1; $n <= $paginas; $n++) {
                    $plantilla = $salida->importPage($n);
                    $tamano = $salida->getTemplateSize($plantilla);
                    $salida->AddPage($tamano['orientation'], [$tamano['width'], $tamano['height']]);
                    $salida->useTemplate($plantilla);
                }
            } catch (\Throwable $e) {
                report($e);
                $fallaron[] = $nombre;
            }
        }

        if ($salida->PageNo() === 0) {
            return ['pdf' => '', 'fallaron' => $fallaron];
        }

        return ['pdf' => $salida->Output('S'), 'fallaron' => $fallaron];
    }
}
