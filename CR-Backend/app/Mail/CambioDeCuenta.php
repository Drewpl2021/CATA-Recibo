<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El aviso por correo de un cambio de cuenta (sueldo o CTS): cuando se pide
 * y cuando RR.HH. lo resuelve.
 *
 * Es la defensa contra el fraude clásico de planillas: alguien entra con la
 * contraseña de otro y le cambia la cuenta para cobrar su sueldo. El correo
 * le llega al dueño de la cuenta, que se entera aunque no haya sido él. El
 * número va enmascarado: el correo no es lugar para la cuenta entera.
 */
class CambioDeCuenta extends Mailable
{
    use Queueable, SerializesModels;

    public string $enlace;

    /**
     * @param  string  $momento  pedida | aprobada | rechazada
     * @param  string  $destino  «de sueldo» o «CTS»
     */
    public function __construct(
        public string $nombreEmpleado,
        public string $momento,
        public string $destino,
        public string $entidad,
        public string $numeroEnmascarado,
        public ?string $respuesta = null,
    ) {
        $this->enlace = rtrim((string) config('app.frontend_url'), '/') . '/inicio/mis-solicitudes';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->momento) {
            'pedida'    => "Pediste cambiar tu cuenta {$this->destino}",
            'aprobada'  => "Tu cuenta {$this->destino} ya cambió",
            default     => "No se cambió tu cuenta {$this->destino}",
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.cambio-de-cuenta');
    }
}
