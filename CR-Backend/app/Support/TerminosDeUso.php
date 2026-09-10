<?php

namespace App\Support;

/**
 * Los términos que cada trabajador acepta antes de entrar por primera vez.
 *
 * Es la hoja que el colegio repartía impresa para que la firmaran, ahora
 * dentro de su cuenta. El texto vive aquí y no en la base porque es un
 * documento, no un dato: se lee, se versiona y se despliega con el sistema.
 *
 * REGLA AL CAMBIARLO: si se toca el texto, sube la VERSION. La versión que
 * cada uno aceptó queda guardada en su cuenta, y ahí está la gracia — decir
 * "aceptó los términos" sin decir cuáles no prueba nada. Al subirla, a quien
 * ya firmó la anterior se le vuelve a pedir la firma.
 */
class TerminosDeUso
{
    /** Fecha de la versión, que es como se nombran los documentos del colegio. */
    public const VERSION = '2026.1';

    public const TITULO = 'Entrega digital de boletas de pago';

    public const RESUMEN = 'Antes esto se firmaba en papel. Léelo y acéptalo para '
        . 'empezar a recibir tus boletas en tu cuenta y en tu correo.';

    /**
     * El documento, por secciones.
     *
     * Escrito para un docente, no para un abogado: frases cortas, y lo que
     * le toca hacer a cada parte dicho en voz activa.
     */
    public const SECCIONES = [
        [
            'titulo' => '1. De qué se trata',
            'texto'  => 'La Asociación Educativa Colegio Adventista "Túpac Amaru" te '
                . 'entregará tus boletas de pago en formato digital. Las vas a encontrar '
                . 'en tu cuenta de este sistema y también te llegará un aviso a tu correo '
                . 'cada vez que se emita una nueva. Ya no se imprimen ni se reparten en '
                . 'mano, salvo que las pidas.',
        ],
        [
            'titulo' => '2. Vale igual que la firma en papel',
            'texto'  => 'Aceptar aquí tiene el mismo valor que la firma que antes ponías '
                . 'en la hoja impresa. Queda registrada la fecha, la hora y la cuenta '
                . 'desde la que aceptaste, conforme a la Ley 27269 de Firmas y '
                . 'Certificados Digitales.',
        ],
        [
            'titulo' => '3. Revisar tu boleta',
            'texto'  => 'Cuando te llegue el aviso, entra y revísala. Si algo no cuadra '
                . '—un descuento que no reconoces, un monto que no es— avísale a Recursos '
                . 'Humanos dentro de los 30 días siguientes a la emisión. Pasado ese '
                . 'plazo la boleta se da por conforme, igual que antes con el papel.',
        ],
        [
            'titulo' => '4. Tu cuenta es tuya',
            'texto'  => 'Tu contraseña es personal e intransferible: no la compartas ni '
                . 'la anotes donde otro pueda verla. Tu boleta lleva tu sueldo y tus '
                . 'descuentos, y lo que se haga desde tu cuenta se considera hecho por '
                . 'ti. Si crees que alguien más entró, cámbiala y avisa a Recursos '
                . 'Humanos el mismo día.',
        ],
        [
            'titulo' => '5. Tus datos',
            'texto'  => 'El colegio trata tus datos personales solo para la gestión de '
                . 'planillas, boletas y documentos laborales, conforme a la Ley 29733 de '
                . 'Protección de Datos Personales. No se ceden a terceros ajenos a esa '
                . 'finalidad. Puedes pedir acceso, rectificación o actualización de tus '
                . 'datos escribiendo a Recursos Humanos.',
        ],
        [
            'titulo' => '6. Si prefieres el papel',
            'texto'  => 'Puedes pedir tu boleta impresa cuando la necesites —para un '
                . 'trámite bancario, por ejemplo— acercándote a Recursos Humanos. '
                . 'Aceptar la entrega digital no te quita ese derecho.',
        ],
    ];

    /** El documento entero, tal como lo consume la pantalla. */
    public static function documento(): array
    {
        return [
            'version'  => self::VERSION,
            'titulo'   => self::TITULO,
            'resumen'  => self::RESUMEN,
            'secciones' => self::SECCIONES,
        ];
    }
}
