<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoletaGenerada extends Mailable
{
    use Queueable, SerializesModels;

    public string $nombreEmpleado;
    public string $mesNombre;
    public int $anio;
    public string $numeroBoleta;

    public function __construct(string $nombreEmpleado, string $mesNombre, int $anio, string $numeroBoleta)
    {
        $this->nombreEmpleado = $nombreEmpleado;
        $this->mesNombre      = $mesNombre;
        $this->anio           = $anio;
        $this->numeroBoleta   = $numeroBoleta;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Boleta de Pago — {$this->mesNombre} {$this->anio}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.boleta-generada',
        );
    }
}