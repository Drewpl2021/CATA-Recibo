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
];
