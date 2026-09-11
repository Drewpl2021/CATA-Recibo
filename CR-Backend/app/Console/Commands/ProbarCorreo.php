<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda un correo de prueba, AHORA y sin pasar por la cola.
 *
 *   php artisan correo:probar tu-correo@cata.edu.pe
 *
 * Existe porque el correo falla en silencio: los avisos de boleta y el
 * "olvidé mi contraseña" van a la cola, y si el SMTP está mal configurado el
 * error acaba en un log que nadie mira, mientras la pantalla dice "te
 * enviamos un enlace". Esto lo manda en el momento y enseña el error tal
 * cual, que es lo que hace falta para arreglarlo.
 */
class ProbarCorreo extends Command
{
    protected $signature = 'correo:probar {destino : A quién mandarle la prueba}';

    protected $description = 'Manda un correo de prueba en el momento para comprobar la configuración';

    public function handle(): int
    {
        $destino = (string) $this->argument('destino');
        $mailer  = (string) config('mail.default');
        $config  = (array) config("mail.mailers.{$mailer}", []);

        $this->line("  Mailer:    <info>{$mailer}</info>");

        if ($mailer === 'log') {
            $this->warn('  Con MAIL_MAILER=log el correo NO sale del servidor: se escribe en storage/logs.');
            $this->line('  Configura el SMTP en el .env (ver .env.example) y vuelve a probar.');
            return self::FAILURE;
        }

        if ($mailer === 'smtp') {
            $this->line('  Servidor:  ' . ($config['host'] ?? '—') . ':' . ($config['port'] ?? '—') . '  esquema ' . ($config['scheme'] ?? 'smtp'));
            $this->line('  Usuario:   ' . ($config['username'] ?? '—'));

            if (($config['scheme'] ?? null) === 'tls') {
                $this->error('  MAIL_SCHEME=tls no es válido: usa "smtp" (puerto 587) o "smtps" (puerto 465).');
                return self::FAILURE;
            }
        }

        $this->line("  Remitente: " . config('mail.from.address'));
        $this->line("  Destino:   {$destino}");
        $this->newLine();

        try {
            Mail::raw(
                "Este es un correo de prueba de CATA-Recibo.\n\n"
                . "Si lo estás leyendo, el correo está bien configurado: los avisos de "
                . "boleta y los enlaces de \"olvidé mi contraseña\" van a llegar.",
                fn ($m) => $m->to($destino)->subject('CATA-Recibo: prueba de correo')
            );
        } catch (Throwable $e) {
            $this->error('  No se pudo enviar:');
            $this->line('  ' . $e->getMessage());
            $this->newLine();
            $this->line('  Lo más común: la contraseña no es una "contraseña de aplicación",');
            $this->line('  el puerto no coincide con el esquema, o el servidor bloquea la salida al 587.');
            return self::FAILURE;
        }

        $this->info("  Enviado. Revisa la bandeja de {$destino} (y la carpeta de spam).");

        return self::SUCCESS;
    }
}
