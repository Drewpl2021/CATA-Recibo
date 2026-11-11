<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El aviso por correo de que una hoja que emite el sistema (la de CTS, la
 * de gratificación) ya está firmada por el colegio y espera la conformidad
 * del trabajador. Como el de la boleta, con la misma plantilla.
 */
class HojaLista extends Mailable
{
    use Queueable, SerializesModels;

    /** Adonde lleva el botón: Mis Boletas (sus hojas) o Mis Documentos (sus constancias). */
    public string $enlace;

    /**
     * @param  string  $queEs    «hoja de CTS», «hoja de gratificación»
     * @param  string  $periodo  «noviembre 2026», «julio 2026»
     */
    public function __construct(
        public string $nombreEmpleado,
        public string $queEs,
        public string $periodo,
        /** «hoja» (lista, Revísala) o «certificado» (listo, Revísalo). */
        public bool $femenino = true,
        /** false: una constancia, que no pide conformidad y está en Mis Documentos. */
        public bool $conConformidad = true,
    ) {
        $this->enlace = rtrim((string) config('app.frontend_url'), '/') . ($conConformidad ? '/inicio/mis-boletas' : '/inicio/mis-documentos');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu ' . $this->queEs . ' — ' . $this->periodo);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.hoja-lista');
    }
}
