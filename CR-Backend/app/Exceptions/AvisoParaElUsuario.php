<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un problema que el usuario puede entender y corregir: «la clave del
 * certificado no es correcta», «el Excel no tiene la columna DNI».
 *
 * Es la ÚNICA excepción cuyo mensaje se muestra tal cual. Antes los
 * controladores atrapaban cualquier RuntimeException y devolvían su
 * mensaje, y así llegaban a la pantalla cosas internas como «El objeto 12
 * del PDF no es un diccionario» o «El servidor no tiene APP_KEY». Todo lo
 * demás va al registro del servidor y el usuario ve el mensaje genérico con
 * su código de referencia (ver bootstrap/app.php).
 *
 * Hereda de RuntimeException para que el código que ya atrapaba esa clase
 * siga funcionando mientras se revisa.
 */
class AvisoParaElUsuario extends RuntimeException
{
}
