<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El correo del "olvidé mi contraseña".
 *
 * Lleva un enlace de un solo uso al frontend; el token viaja en la URL y
 * caduca a los 60 minutos (config/auth.php, passwords.users.expire).
 */
class RestablecerPassword extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $nombre;
    public string $enlace;
    public int $minutosValidez;

    public function __construct(string $nombre, string $email, string $token)
    {
        $this->nombre = $nombre;

        $base = rtrim(config('app.frontend_url'), '/');
        $this->enlace = $base . '/restablecer-password?token=' . $token . '&email=' . urlencode($email);

        $this->minutosValidez = (int) config('auth.passwords.users.expire', 60);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Restablece tu contraseña — Colegio Adventista Túpac Amaru');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.restablecer-password');
    }
}
