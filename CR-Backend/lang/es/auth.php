<?php

/*
|--------------------------------------------------------------------------
| Mensajes de autenticación
|--------------------------------------------------------------------------
|
| 'failed' y 'password' dicen exactamente lo mismo a propósito. Laravel usa
| el primero cuando no encuentra al usuario y el segundo cuando la contraseña
| no coincide; si los textos fueran distintos, comparar las dos respuestas
| revelaría qué correos están registrados (enumeración de usuarios).
|
| El mismo texto está en AuthController::CREDENCIALES_INVALIDAS, que es el
| que responde el login de la API. Si cambias uno, cambia el otro.
|
*/

return [
    'failed' => 'Credenciales incorrectas. Verifica tus datos e intenta nuevamente.',
    'password' => 'Credenciales incorrectas. Verifica tus datos e intenta nuevamente.',
    'throttle' => 'Demasiados intentos de acceso. Intenta de nuevo en :seconds segundos.',
];
