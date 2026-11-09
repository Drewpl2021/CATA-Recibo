<?php

namespace App\Console\Commands;

use App\Support\Firma\FirmaDelColegio;
use Illuminate\Console\Command;

/**
 * Borra las copias sin firmar de las boletas y hojas que ya tienen todas
 * las firmas del colegio. Desde el 9 nov 2026 eso pasa solo al firmar; esto
 * limpia las de antes. Se puede correr las veces que se quiera: lo que ya
 * se limpió no se toca.
 *
 *   php artisan documentos:quitar-copias-sin-firma
 */
class QuitarCopiasSinFirma extends Command
{
    protected $signature = 'documentos:quitar-copias-sin-firma';

    protected $description = 'Borra la copia sin firmar de los documentos que ya tienen todas las firmas del colegio';

    public function handle(): int
    {
        ['borradas' => $borradas, 'bytes' => $bytes] = FirmaDelColegio::quitarCopiasSinFirma();

        $this->info($borradas
            ? "Se borraron {$borradas} copia(s) sin firmar: " . number_format($bytes / 1048576, 1) . ' MB liberados.'
            : 'No había copias sin firmar que borrar.');

        return self::SUCCESS;
    }
}
