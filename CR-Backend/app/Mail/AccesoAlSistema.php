<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * «Tu acceso a CATA-Recibo»: el enlace para crear la primera contraseña (o
 * una nueva, cuando RR.HH. le restablece el acceso). Un solo uso, vence a las
 * 72 horas (config/auth.php, passwords.invitaciones). Ver AccesoPorCorreo.
 */
class AccesoAlSistema extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $nombre;
    public string $email;
    public string $enlace;
    public int $horasValidez;
    /** Si RR.HH. le restableció el acceso (ya tenía contraseña) y no es su primera vez. */
    public bool $restablecer;

    public function __construct(string $nombre, string $email, string $token, bool $restablecer = false)
    {
        $this->nombre = $nombre;
        $this->email = $email;
        $this->restablecer = $restablecer;

        $base = rtrim(config('app.frontend_url'), '/');
        // Después de «#», no de «?»: el navegador nunca manda esa parte al
        // servidor, así que el token no queda en los registros del hosting.
        // La pantalla además lo borra de la barra apenas lo lee.
        $this->enlace = $base . '/crear-contrasena#token=' . rawurlencode($token) . '&email=' . rawurlencode($email);

        $this->horasValidez = intdiv((int) config('auth.passwords.invitaciones.expire', 4320), 60);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->restablecer
            ? 'Crea una contraseña nueva para CATA-Recibo'
            : 'Tu acceso a CATA-Recibo — Colegio Adventista Túpac Amaru');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.acceso-al-sistema');
    }
}
