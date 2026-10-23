<?php

/*
|--------------------------------------------------------------------------
| Datos de la institución
|--------------------------------------------------------------------------
|
| El dominio de correo estaba escrito en duro dentro de App\Models\User, y
| este repositorio es público: cualquiera que lo abriera sabía a qué
| organización pertenece el sistema y qué forma tienen sus direcciones, que
| es justo por donde empieza un intento de phishing o de adivinar cuentas.
|
| Acá va vacío. Cada despliegue pone el suyo en el .env:
|
|     INSTITUCION_DOMINIO_CORREO=midominio.edu.pe
|
| Sin rellenarlo no se rompe nada: `es_institucional` responde false para
| todo el mundo, que es lo correcto cuando no hay un dominio con el que
| comparar.
|
*/

return [
    'dominio_correo' => env('INSTITUCION_DOMINIO_CORREO'),

    /*
    | El RUC que imprimen la boleta, la hoja de CTS y la de gratificación.
    | Es público (sale en cada boleta) y estaba escrito a mano en cada
    | plantilla: tres de ellas llevaban 20156630733, que ni siquiera pasa el
    | dígito verificador de SUNAT. Ahora hay un solo sitio, y una prueba
    | (RucDelColegioTest) comprueba el dígito.
    */
    'ruc' => env('INSTITUCION_RUC', '20156630731'),
];
