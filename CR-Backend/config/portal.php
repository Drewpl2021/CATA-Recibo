<?php

/*
|--------------------------------------------------------------------------
| El módulo Portal: la API pública que lee cata.edu.pe
|--------------------------------------------------------------------------
|
| El portal del colegio (React) no tiene login ni base propia: todo lo que
| muestra lo pide a /api/portal/v1/…, siempre con GET y sin token. El
| contrato vive en el repositorio del portal (docs/API_CONTRACT.md, v2.12) y
| una copia del esquema en tests/Fixtures/portal.
|
| Todo sale de config() y no de env() en el código, por el `config:cache`
| de producción (ver CorsMiddleware).
|
*/

return [

    /*
     * Desde qué páginas se puede leer la API con el navegador. Es otro
     * dominio (cata.edu.pe), así que sin esta lista el navegador descarta
     * las respuestas. Separadas por comas, con esquema y sin barra final:
     *
     *   PORTAL_ORIGENES=https://cata.edu.pe,https://pruebas.cata.edu.pe
     *
     * En desarrollo, el `npm run dev` del portal (localhost:5173). Es una
     * lista aparte de FRONTEND_URL: la pantalla de RR.HH. y el portal son
     * dos sitios distintos y ninguno debe heredar el permiso del otro.
     */
    'origenes' => array_values(array_filter(array_map(
        fn ($origen) => rtrim(trim($origen), '/'),
        explode(',', (string) env('PORTAL_ORIGENES', 'http://localhost:5173'))
    ))),

    /*
     * Cuánto puede guardar el navegador (o un proxy) cada respuesta. El
     * contrato recomienda 60 s: lo que se publique en el panel tarda a lo
     * sumo un minuto en verse.
     */
    'cache_segundos' => (int) env('PORTAL_CACHE_SEGUNDOS', 60),

    /*
     * Peticiones por minuto y por IP. Una visita a Inicio hace unas ocho
     * (una por sección), así que 120 deja navegar con holgura y frena a un
     * script. Muchos alumnos salen a internet por la misma IP del colegio:
     * si en el laboratorio de cómputo se queda corto, se sube aquí.
     */
    'limite_por_minuto' => (int) env('PORTAL_LIMITE_POR_MINUTO', 120),

    /*
     * De dónde sirve el portal las imágenes subidas desde el panel. Tiene que
     * ser una dirección absoluta y https: el contrato hace que el portal
     * descarte cualquier respuesta con una URL http://. Por defecto, la ruta
     * de medios de esta misma API.
     */
    'url_medios' => env('PORTAL_URL_MEDIOS', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/api/portal/medios'),

];
