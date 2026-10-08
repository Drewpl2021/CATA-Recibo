<?php

namespace App\Support;

use App\Models\Configuracion;

/**
 * Boletas con firma digital del colegio (ReFirma de RENIEC).
 *
 * Con esto encendido, la boleta no le llega al trabajador al emitirla: RR.HH.
 * baja las emitidas, quien firma por el colegio las firma con su DNIe en
 * ReFirma, y al subirlas al sistema (BoletasFirmadasController) recién se le
 * avisa y la puede ver. Es UN solo PDF para el colegio y para el trabajador:
 * después de firmado nadie lo vuelve a tocar, ni siquiera su conformidad (se
 * guarda aparte, en el documento).
 *
 * Quién firma no se configura: el nombre y el DNI salen del certificado de
 * cada firma. Así, si cambia la persona, no hay nada que actualizar.
 */
final class FirmaDigitalDeBoletas
{
    public const AJUSTE = 'boleta_firma_digital';

    /** Cuántas firmas del colegio necesita cada boleta: 1 o 2. */
    public const AJUSTE_FIRMAS = 'boleta_firmas_requeridas';

    public static function activa(): bool
    {
        return Configuracion::activo(self::AJUSTE, false);
    }

    public static function requeridas(): int
    {
        return Configuracion::numero(self::AJUSTE_FIRMAS, 1) >= 2 ? 2 : 1;
    }
}
