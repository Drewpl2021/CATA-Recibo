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
    /**
     * Fecha de la versión, que es como se nombran los documentos del colegio.
     *
     * 2026.2: pasa a ser el Convenio de entrega digital de documentos
     * laborales (docs/4. Convenio…docx), en lenguaje sencillo: mismas
     * cláusulas y mismos plazos, para que lo que se acepta aquí y lo que se
     * firma en papel no se contradigan. Agrega la firma digital del colegio,
     * la conformidad con contraseña y la vigencia.
     */
    public const VERSION = '2026.2';

    public const TITULO = 'Convenio de entrega digital de documentos laborales';

    public const RESUMEN = 'Es el convenio que antes se firmaba en papel: léelo y acéptalo '
        . 'para recibir tus boletas y documentos laborales en tu cuenta y en tu correo.';

    /**
     * El convenio, por cláusulas.
     *
     * Escrito para un docente, no para un abogado: frases cortas, y lo que
     * le toca hacer a cada parte dicho en voz activa. Dice lo mismo que el
     * Word del convenio: si se cambia uno, se cambia el otro.
     */
    public const SECCIONES = [
        [
            'titulo' => '1. De qué se trata',
            'texto'  => 'La Asociación Educativa Colegio Adventista "Túpac Amaru" (RUC 20156630731), '
                . 'representada por su Director General, te entregará tus boletas de pago y los demás '
                . 'documentos laborales en formato digital, en tu cuenta de este sistema (CATA-Recibo), '
                . 'en lugar de imprimirlos.',
        ],
        [
            'titulo' => '2. Por qué es válido',
            'texto'  => 'La ley permite que el colegio firme tus boletas con firma digital y te las entregue '
                . 'por medios electrónicos (D.S. 001-98-TR modificado por el D.S. 009-2011-TR, y D. Leg. 1310). '
                . 'El colegio firma con un certificado digital de una entidad acreditada ante el INDECOPI, '
                . 'que vale igual que su firma a mano (Ley 27269).',
        ],
        [
            'titulo' => '3. Cómo te llega cada boleta',
            'texto'  => 'El colegio firma tu boleta y recién entonces te llega: la ves en tu cuenta y recibes '
                . 'un aviso aquí y en tu correo. Al revisarla, das tu conformidad con tu contraseña; queda '
                . 'registrada la fecha, la hora, desde dónde la diste y el código del documento que recibiste. '
                . 'Cada boleta lleva un código QR para verificar que es auténtica, y si alguien la alterara, '
                . 'la firma digital dejaría de valer.',
        ],
        [
            'titulo' => '4. Lo que hace el colegio',
            'texto'  => 'Pone información veraz en tus documentos, guarda en reserva su firma digital, mantiene '
                . 'el sistema disponible y tus documentos respaldados, te avisa si cambia la forma de entrar, '
                . 'se asegura de que solo tú veas lo tuyo y te da una copia impresa cuando la pidas.',
        ],
        [
            'titulo' => '5. Lo que haces tú',
            'texto'  => 'Dale a Recursos Humanos tu correo y tu celular, y avísale si cambian. Revisa el sistema cuando te llegue un '
                . 'aviso. Si algo de tu boleta no cuadra —un descuento que no reconoces, un monto que no es—, '
                . 'avísale a Recursos Humanos dentro de los 30 días siguientes a que te llegue; pasado ese plazo '
                . 'se da por conforme. Tu contraseña es personal: no la compartas, cierra tu sesión al terminar '
                . 'y, si crees que alguien más entró, cámbiala y avisa a Recursos Humanos el mismo día.',
        ],
        [
            'titulo' => '6. Tus datos',
            'texto'  => 'El colegio usa tus datos personales solo para planillas, boletas y documentos laborales, '
                . 'conforme a la Ley 29733 de Protección de Datos Personales, y no los cede a terceros salvo '
                . 'que la ley lo exija. Puedes pedir acceso, corrección o actualización de tus datos a '
                . 'Recursos Humanos.',
        ],
        [
            'titulo' => '7. Vigencia',
            'texto'  => 'Este convenio vale desde que lo aceptas y mientras trabajes en el colegio. Aceptarlo aquí '
                . 'con tu cuenta vale igual que firmarlo en papel: queda registrada la versión, la fecha y la hora. '
                . 'Si lo prefieres, también puedes firmarlo impreso en Recursos Humanos.',
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
