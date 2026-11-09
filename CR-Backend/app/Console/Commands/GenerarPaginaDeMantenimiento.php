<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Arma docker/web/mantenimiento.html: la página que muestra el nginx del
 * Docker cuando PHP no responde (se está reiniciando o actualizando). Sale
 * de la misma plantilla que las páginas de error de Laravel
 * (resources/views/errors/plantilla.blade.php), así se ven idénticas.
 *
 *   php artisan sistema:pagina-mantenimiento
 *
 * Se corre a mano cuando cambia la plantilla; el archivo va en el repo.
 */
class GenerarPaginaDeMantenimiento extends Command
{
    protected $signature = 'sistema:pagina-mantenimiento';

    protected $description = 'Arma la página fija de mantenimiento del nginx (docker/web/mantenimiento.html)';

    public function handle(): int
    {
        $html = view('errors.plantilla', [
            'codigo'       => '503',
            'titulo'       => 'En mantenimiento',
            'mensajes'     => ['CATA-Recibo se está actualizando o reiniciando, y vuelve en unos minutos.', 'Tus boletas, hojas y documentos están a salvo.'],
            'boton'        => ['texto' => 'Volver a intentar', 'url' => '/'],
            'inicio'       => '/',
            'recargarCada' => 30,
        ])->render();

        // El aviso va dentro del <head>: antes del <!DOCTYPE> no conviene poner nada.
        $html = preg_replace('/<head>/', "<head>\n    <!-- Generado con «php artisan sistema:pagina-mantenimiento» desde resources/views/errors/plantilla.blade.php: no editar a mano. -->", $html, 1);
        $destino = base_path('../docker/web/mantenimiento.html');
        file_put_contents($destino, $html);

        $this->info('Listo: ' . realpath($destino) . ' (' . number_format(strlen($html) / 1024, 1) . ' KB).');

        return self::SUCCESS;
    }
}
