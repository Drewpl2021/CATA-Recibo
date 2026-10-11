# Módulo Portal

La API pública de solo lectura que lee el portal del colegio (cata.edu.pe), servida por CR-Backend bajo `/api/portal`. Su contrato vive en el repositorio del portal (`Portal Web/docs/API_CONTRACT.md`, v2.12); una copia del esquema y de los ejemplos está en `CR-Backend/tests/Fixtures/portal/`.

## Etapa 1: la API de solo lectura (hecha)

- Los 26 endpoints del contrato 2.12 bajo `/api/portal/v1`, solo GET y sin token, en `routes/portal.php`.
- 25 tablas `portal_*`. Cada lista tiene su `clave` pública, `orden` y `estado` (publicado o borrador). Las imágenes van en `portal_imagenes`, una fila por uso, también dentro de los JSON (`{"imagenId": …}`).
- `php artisan portal:importar {escenario}` carga un escenario de los ejemplos del contrato. Reemplaza todo el contenido del portal, así que no corre sin `--force` si ya hay contenido o si es producción.
- Pruebas: `tests/Feature/Portal*Test.php`. Cada endpoint, en los seis escenarios, contra el JSON Schema y contra el ejemplo. Pasan en SQLite y en MySQL.
- Probada de punta a punta con el portal React (`VITE_API_URL`): todas las páginas, la API apagada y el escenario `vacio`.

### Imágenes subidas: `GET /api/portal/medios/{archivo}`

Sirve las imágenes que se suban desde el panel (disco `portal`, en `storage/app/portal`). Es la dirección que arma `Imagen::url()` para las que no son externas: `config('portal.url_medios')`, que por defecto es `APP_URL` + `/api/portal/medios`.

- `Cache-Control: public, max-age=31536000, immutable`: cada subida tiene su propio nombre y no se reutiliza.
- Se sirve desde el mismo dominio que RR.HH., por eso solo JPEG, PNG, WebP y GIF (nada de SVG, que puede llevar JavaScript), con el tipo tomado de la extensión, `nosniff` y una CSP `sandbox`.
- Solo los archivos registrados en `portal_imagenes`, con nombre plano: `[a-z0-9][a-z0-9_-]*.(jpg|jpeg|png|webp|gif)`. Sin carpetas ni `../`.
- Tiene su propio freno (`PORTAL_LIMITE_MEDIOS_POR_MINUTO`, 600), aparte del de la API.

## Etapa 2: el panel «Portal Web» (en curso, rama `feature/portal-panel`)

Regla de toda la etapa: **solo suma**. No cambia colores, estilos globales, pantallas, componentes compartidos ni el comportamiento de ningún módulo existente; reutiliza los que hay. Si algo existente necesitara cambiar, se pregunta antes.

### Etapa 0: la base (hecha)

- **Una sola puerta:** `Gate::define('editar-portal')` en `AppServiceProvider`, hoy solo el Administrador. En Angular, `soloAdmin` sobre `/inicio/portal-web`. Si otro rol tiene que editar, se cambia ahí y en ningún otro lado.
- **Rutas:** `routes/portal-panel.php`, bajo `/api/web-portal`, con la misma cadena que el sistema protegido (sesión, contraseña al día, términos) más `can:editar-portal`. No van bajo `/api/portal`: ese prefijo tiene el CORS y los errores del portal público. Responden como el sistema (`{ success, data }`).
- **Controlador base** (`Portal\Panel\ListaDelPortalController`): ver, crear (al final y en borrador), editar, ordenar (la lista entera), publicar o pasar a borrador, y eliminar. Cada lista solo declara su modelo, sus reglas y qué respuestas del portal cambian.
- **Validación de salida** (`ValidadorDeContrato`): cada cambio va en una transacción que solo se confirma si las respuestas públicas afectadas, armadas por sus propios controladores, siguen cumpliendo el JSON Schema del contrato (`resources/portal/api-contract.schema.json`). Si no, se deshace y responde 422 con el porqué. Por eso `opis/json-schema` pasó a dependencia de producción.
- **Menú:** grupo «Portal Web» › «Contenido del portal» (`/portal-web`), al final del menú y solo para Admin. Migración `agregar_modulo_portal_web` para las bases instaladas; `ModuloSeeder` para las nuevas.
- **Pantalla índice** (`features/portal-web`): las ocho categorías (General, Inicio, Proyectos, Nosotros, Propuesta Educativa, Matrícula, Logros, Contacto), con lo que se edita en cada una y cómo está hoy (`GET /web-portal/summary`). Usa las clases de Ajustes del sistema; sin estilos propios.

### Etapa 1: General (hecha)

- **Fichas:** `GET` y `PUT /web-portal/sections/{clave}` (`SeccionesController`). Cada ficha declara sus reglas en `App\Support\Portal\Fichas` (hoy, `FichaSitio`). El `PUT` trae la `version` leída: si alguien guardó entre medio, responde 409 y no pisa su trabajo.
- **Redes sociales:** `/web-portal/social-networks` (`RedesController`, sobre la base de las listas). Cada red una sola vez; dirección https, y la de WhatsApp, `https://wa.me/…`.
- **Pantalla** `/inicio/portal-web/general`: los datos del colegio en un formulario con los largos del contrato, y las redes en la tabla del sistema, con subir y bajar (botones, para el teclado), publicar o pasar a borrador, editar y eliminar.

### Etapa 2: las imágenes (hecha)

- **`POST /web-portal/images`** (archivo, `uso`, `alt` o `decorativa`) y **`PATCH /web-portal/images/{id}`** (cambiar el alt). `GET /web-portal/images/uses` dice para qué se sube cada una: proporción, mínimo recomendado, si es un recorte transparente, si lleva punto focal (`App\Support\Portal\UsosDeImagen`).
- **`ProcesadorDeImagenes`**: la abre con GD (solo JPEG, PNG o WebP de verdad; nada de SVG ni GIF; la extensión no cuenta), la endereza según su EXIF, la re-codifica a WebP (sin metadatos: ni GPS ni modelo de celular), lleva el lado más largo a 2560 px, busca los 400 KB bajando calidad y, si no alcanza, tamaño (nunca bajo el mínimo de su uso), conserva la transparencia, y la guarda como `uuid7.webp`. Devuelve avisos que no impiden guardar: más chica de lo recomendado, otra proporción, sin transparencia, muy pesada, alt largo.
- **Texto alternativo**: obligatorio, salvo que se marque como decorativa (alt vacío). No vale «foto», «imagen» ni un nombre de archivo.
- **`portal:limpiar-imagenes`** (`--simular`, `--horas=24`): borra las imágenes que nadie usa (ni una columna `*_imagen_id` ni un `{"imagenId"}` en un JSON) y los archivos sin fila, después de un día sueltas. **Falta programarlo** (una línea en `routes/console.php`, archivo existente: se pregunta antes).
- **Docker**: `exif` en `docker/php/Dockerfile`, para enderezar las fotos de celular.
- **Angular**: `app-selector-imagen` (`features/portal-web/compartido`), sobre el selector de archivos del sistema: pide el alt antes de subir, muestra la vista previa en el marco con la proporción del portal, los avisos, y en los banners el punto focal (tocando la vista previa o escrito en %). Lo usarán las categorías desde la etapa 3.

### Etapa 3: Contacto (hecha)

- **Fichas** `contacto` (título, frase, recorte del estudiante, correo general) y `solicitud` (textos del formulario y el mensaje de WhatsApp, general y por página). En las plantillas solo valen `{nombre}` y `{grado}`. `GET sections/{clave}` devuelve también `imagenes`: cada imagen de la ficha, completa, para la vista previa.
- **Sedes**: `/web-portal/campuses` (`SedesController`). Hasta 8 publicadas (tope de la base de las listas, con un mensaje claro). La `clave` pública se arma con el nombre al crearla y no cambia. Teléfono E.164 o nada; horario hasta 4 filas; mapa insertable solo de Google Maps u OpenStreetMap.
- **Pantalla** `/inicio/portal-web/contacto`: la cabecera con el selector de imágenes, las sedes con su modal (foto, horario, teléfono, mapa: se puede pegar el `<iframe>` de «Insertar un mapa» y se toma solo su dirección) y el formulario con una vista previa del mensaje.
- **Vista previa de las imágenes subidas**: el panel las pide por su ruta relativa (`/api/portal/medios/…`), porque el panel y la ruta de medios viven en el mismo servidor. La dirección pública (la del portal) sigue siendo la absoluta https.
- **Si `APP_URL` no es https**, el panel no deja publicar imágenes subidas: el portal descartaría la página (probado en `PortalPanelContactoTest`). El mensaje lo dice en palabras de quien edita.

### Etapa 4: Logros (hecha)

- **Ficha** `logros`: título (hasta 60) y frase (hasta 200) de la cabecera.
- **Cifras**: `/web-portal/stats?ambito=portada|logros|propuesta` (`CifrasController`). El `ambito` se elige al crearla y no cambia; el tope de 4 publicadas y el orden van por página. Número entero de 0 a 999 999, signo de hasta 3 caracteres, «qué cuenta» hasta 60. En Angular es un panel reutilizable, `<app-editor-cifras ambito="…">`, que usarán también Inicio y Propuesta.
- **Logros**: `/web-portal/achievements` (`LogrosController`), hasta 200 publicados. Título, categoría (las del contrato), año (vacío si no se conoce: no se inventa), nivel o grupo, detalle, foto (uso `logro`), destacado, fuente (nombre, y dirección https opcional; con dirección hace falta el nombre) y aclaración («Año por confirmar»).
- **Tope de las listas**: el mensaje ahora es neutro («Para publicar más, primero pasa a borrador lo que ya no deba verse»), sirve igual para sedes, cifras o logros. `publicadosJuntoA()` permite contar el tope por grupo (las cifras, por página) y `claveUnica()` arma la clave pública desde el nombre (sedes y cifras).
- **Pantalla** `/inicio/portal-web/logros`: cabecera, cifras y la tabla de logros con buscador (son muchos), subir y bajar, publicar o pasar a borrador, y el modal. Lo nuevo entra en borrador: se publica cuando el colegio lo confirma.

### Etapa 5: Matrícula (hecha)

- **Fichas**: `matricula-cabecera` (título, frase, año del proceso 2000–2100 o vacío, recorte), `matricula-vacantes` (fecha «Actualizado el…», que pone quien edita, y nota) y `matricula-fechas` (aviso sobre el cronograma).
- **Procesos**: `/web-portal/enrollment-processes` (`ProcesosMatriculaController`), hasta 4 publicados. Los pasos (hasta 8) y las listas (hasta 4, de 1 a 15 elementos) van dentro del proceso y se guardan enteros.
- **Vacantes**: `/web-portal/vacancies` (`VacantesController`), hasta 6. `disponibilidad` es el `estado` del contrato; con «agotadas» o «consultar» el número no se guarda.
- **Cronograma**: `/web-portal/enrollment-dates` (`FechasMatriculaController`), hasta 12. Siempre por fecha de inicio: **no tiene ruta de orden**. «Hasta» no puede ser antes de «desde» (al editar solo «hasta», se compara con el «desde» guardado).
- **Preguntas**: `/web-portal/faqs` (`PreguntasController`), hasta 15.
- **Grados del formulario**: `/web-portal/grade-options` (`GradosController`). El nivel no se repite (el contrato no les da id); de 1 a 8 opciones sin repetir, hasta 24 caracteres. El portal React las muestra como «{opción} de {nivel}» («Música de talleres»).
- **Base de las listas**: gancho `atributos()` para que los errores de adentro de una lista digan «título del paso» y no `pasos.0.titulo`.
- **Angular (Matrícula)**: `compartido/panel-de-lista.ts` (`PanelDeLista`) reúne cargar, publicar u ocultar, ordenar, eliminar y guardar; cada panel de Matrícula (`matricula/procesos|vacantes|fechas|preguntas|grados`) solo dice lo suyo. `compartido/lineas.ts`: las listas cortas que se escriben una por línea. La pantalla `/inicio/portal-web/matricula` los reúne bajo la cabecera, en el orden del portal.

### Etapa 6: Inicio (hecha)

- **Banners**: `/web-portal/banners` (`BannersController`), hasta 8 publicados. **Lo nuevo entra arriba** (gancho `nuevoAlPrincipio()` de la base de las listas: los demás bajan un lugar), porque el contrato los quiere del más reciente al más antiguo; se pueden mover. Afiche de escritorio obligatorio con su punto focal (`foco_x`, `foco_y`, los dos o ninguno), afiche de celular opcional (el panel avisa cuántos publicados no lo traen: el portal lo usa solo si todos), «el afiche ya trae el texto», esquina del botón y el botón.
- **El botón** (`AccionHero`): `App\Support\Portal\ReglasDeAccion` (reglas y forma de guardarlo, con solo los campos de su tipo) y, en Angular, `compartido/editor-accion` (`grupoDeAccion`, `llenarAccion`, `accionDelGrupo`). Lleva a una sección de Inicio, a una página del portal (`/…`, minúsculas sin tildes) o a una dirección https. Lo usará también la cabecera de Propuesta.
- **Cifras de Inicio**: el mismo `app-editor-cifras`, con `ambito="portada"`.
- **Fichas** `quienes-somos` (título, 1 a 6 párrafos de hasta 600, foto 4:5, hasta 8 símbolos) y `niveles` (título de la sección y enlace https a la propuesta).
- **Niveles de Inicio**: `/web-portal/home-levels` (`NivelesController`), hasta 4, foto 3:2. Es la lista de Inicio, no la de Propuesta.
- **Noticias**: `/web-portal/news` (`NoticiasController`). Siempre por fecha, de la más reciente a la más antigua: **sin ruta de orden**. El `slug` se arma con el título al crearla (único, -2, -3…) y no cambia. Antes de guardar se valida la primera página del portal y **la página donde cae la noticia** (`?limite=24&pagina=N`), probado con una noticia vieja que rompe el contrato.
- **Pantalla** `/inicio/portal-web/inicio`: carrusel, cifras, Quiénes somos, niveles y noticias, en el orden del portal. Las sedes, que Inicio también muestra, se editan en Contacto. `hoyEnLima()` pasó a `compartido/panel.ts`.
- **En local, las fotos subidas no se ven en el portal React**: la API las anuncia en https (lo exige el contrato) y `ng serve` sirve http. En producción no pasa.

### Etapa 7: Proyectos (hecha)

- **Ficha** `proyectos`: título (hasta 60) y frase (hasta 240) de la cabecera.
- **Categorías**: `/web-portal/project-categories` (`CategoriasProyectoController`), hasta 8, en el orden del filtro. **Sin borrador** (no hay ruta de estado: el portal las manda todas y oculta las vacías). Una categoría con proyectos no se elimina (422 que dice cuántos tiene). Su `clave` (la de `?categoria=…`) se arma al crearla y no cambia; al renombrarla se valida también el detalle de sus proyectos publicados, que muestra `categoriaNombre`.
- **Proyectos**: `/web-portal/projects` (`ProyectosController`), hasta 24 publicados. Tarjeta (título, lema, resumen, categoría, etapa, fecha, niveles, foto 3:2) y detalle: de 1 a 6 párrafos y hasta 8 secciones (`lista` o `numerada`, de 1 a 12 elementos con título opcional y texto). El `slug` se arma con el título, **se puede corregir mientras está en borrador y queda fijo al publicarlo**. Se valida la lista y, si está publicado, su detalle.
- **Galería**: `PUT /web-portal/projects/{id}/gallery` con `{ imagenes: [ids] }`, de 0 a 12, entera y en orden (sin repetir). Las fotos que salen quedan sueltas y las borra la limpieza. Eliminar el proyecto se lleva su galería.
- **Pantalla** `/inicio/portal-web/proyectos`: cabecera, categorías y proyectos. El modal del proyecto tiene los párrafos y las secciones con sus elementos (agregar, subir, bajar, quitar); la galería tiene su propio modal (botón de la cámara en la fila). El panel de categorías avisa por qué no se puede eliminar una con proyectos, antes de pedir confirmación.
- **En la base de paneles de Angular**: el gancho `alCargar()` (las categorías se pasan al selector de proyectos), `urlParaVer()` en `compartido/panel.ts`, y el aviso «Quedó en borrador» solo cuando de verdad quedó en borrador (las listas sin estado decían que sí).
- **Demo**: con Playwright (el del repositorio del portal), porque el navegador con extensión no estaba disponible. Las fotos de `tipico` apuntan a `medios.prueba.cata.edu.pe`, que no existe: en el panel salen como texto alternativo. Las que se suben sí se ven.

### Etapa 8: Propuesta Educativa (hecha)

- **Fichas** `propuesta-cabecera` (título hasta 70, frase hasta 180, de 0 a 3 figuras `recorte` o `foto` sin repetir imagen, botón principal obligatorio y secundario opcional, con `ReglasDeAccion`; los errores dicen de qué botón hablan), `propuesta-pilares` (la introducción: título y de 1 a 6 párrafos, o `null` para no mostrarla) y `propuesta-academia` (título, descripción y aviso).
- **Cifras**: el mismo `app-editor-cifras`, con `ambito="propuesta"`.
- **Pilares**: `/web-portal/pillars` (`PilaresController`), hasta 8. Texto hasta 400 y de 0 a 8 aspectos de hasta 30, sin repetir.
- **Niveles de Propuesta**: `/web-portal/proposal-levels` (`NivelesPropuestaController`), hasta 6. Hasta 6 párrafos, 8 rasgos (50) y 12 talleres (título 50, texto opcional 200), foto 3:2. Lista aparte de la de Inicio.
- **Universidades**: `/web-portal/universities` (`UniversidadesController`), hasta 8. **Solo se publica con al menos un ciclo** (el contrato lo pide); eliminarla se lleva sus ciclos, uno por uno, para la auditoría. Su `clave` (la de `?universidad=…`) no cambia.
- **Ciclos**: `/web-portal/cycles?universidad=…` (`CiclosController`), de 1 a 12 por universidad, **sin borrador** (se ven con su universidad). La universidad no cambia después de creado. Modalidades presencial y/o virtual (al menos una); precios de 0 a 99 999 con hasta 2 decimales (el virtual se borra si el ciclo no es virtual); fechas con el fin no antes del inicio, también al editar solo una. El último ciclo de una universidad publicada no se elimina.
- **Plataformas**: `/web-portal/platforms` (`PlataformasController`), hasta 8. Descripción 300, de 0 a 8 funciones (para quién 40, qué ofrece 200), enlace https opcional y aviso.
- **Mensajes de validación del módulo** (`App\Support\Portal\MensajesDeValidacion`): `lang/es/validation.php` del sistema no traduce `starts_with`, `decimal` ni `prohibited`, y una dirección http mostraba «validation.starts_with» también en las etapas anteriores. Se pasan desde la base de las listas y desde las fichas, sin tocar `lang/`.
- **Pantalla** `/inicio/portal-web/propuesta`: cabecera (figuras con su tipo, subir, bajar y quitar), cifras, pilares con su introducción, niveles, academia (textos, universidades y, debajo, los ciclos de la elegida: el botón de capas de cada fila la elige) y plataformas.
- **En la base de paneles de Angular**: el gancho `filtro()` de `PanelDeLista` (para una lista dentro de otra, como los ciclos) y `moverEn()` en `compartido/panel.ts` (estaba repetida en Quiénes somos y Proyectos).

### Etapa 9: Nosotros (parte A hecha; falta la B, el editor de bloques)

- **Ficha** `nosotros`: título (hasta 60) y frase (hasta 200) del índice.
- **Subpáginas**: `/web-portal/pages` (`PaginasController`). Título (60), frase (160) y la frase de su tarjeta en el índice (`resumen`, 140; vacía, la tarjeta muestra solo el nombre). **La dirección se elige de las 16 que el portal sabe abrir** (`App\Support\Portal\PaginasDeNosotros`, la misma lista que `src/sitio/mapa.ts`) y no cambia; al crearla se guardan su grupo y su nombre para el menú del contrato 2.13, y entra sin bloques («se publicará pronto»). **Esta parte no toca los bloques**: un `PUT` que los traiga los ignora. Sin ruta de orden en la pantalla: el lugar en el menú lo pone el portal.
- **En borrador, la dirección de la subpágina responde 404, pero el menú del portal la sigue nombrando** (está escrito en su código hasta la 2.13), así que lleva a «Página no encontrada». La pantalla lo advierte.
- **Niveles de Personal Docente**: `/web-portal/teacher-levels` (`NivelesDocentesController`), hasta 6 publicados, se publiquen o no con docentes. Su `clave` (`?nivel=…`) no cambia. Con docentes no se elimina (422 que dice cuántos tiene); en borrador, sus docentes no salen.
- **Docentes**: `/web-portal/teachers?nivel=…` (`DocentesController`), hasta 150 publicados: nombre (80), cargo o área (80), nivel y foto cuadrada (uso `persona`). **Se ordenan dentro de su nivel** (el orden pide `?nivel=`); quien cambia de nivel pasa al final del nuevo. Se escriben en el panel: no salen de la ficha de RR.HH.
- **Pantalla** `/inicio/portal-web/nosotros`: el índice, las subpáginas (la tabla dice cuántos bloques tiene cada una) y Personal Docente: los niveles y, debajo, los docentes del nivel elegido, con buscador (el botón de personas de cada fila lo elige). Avisa antes de pedir confirmación si un nivel tiene docentes.

### Programar la limpieza de imágenes

`portal:limpiar-imagenes` **no está programado**: no se toca `routes/console.php`, que es del sistema. Mientras no se programe, **las imágenes sueltas se acumulan** en `storage/app/portal` (cada foto reemplazada, cada registro eliminado y cada subida que no se llegó a guardar). No rompen nada ni se ven en el portal, pero ocupan disco: conviene correr el comando a mano de vez en cuando, primero simulando:

```bash
php artisan portal:limpiar-imagenes --simular   # dice qué borraría
php artisan portal:limpiar-imagenes             # borra las sueltas hace más de 24 horas
```

Para programarlo hay dos formas. Basta una.

**1. Con el programador de Laravel**, si algún día se decide tocar `routes/console.php`. Una línea, junto a las demás tareas diarias:

```php
Schedule::command('portal:limpiar-imagenes')->dailyAt('03:00');
```

Así corre con las demás: en Docker, el contenedor `reloj`; sin Docker, el cron que ejecuta `schedule:run`.

**2. Con el cron de cPanel (GoDaddy)**, sin tocar el código. En cPanel › *Avanzado* › **Trabajos de cron** (*Cron Jobs*):

- *Configuración común*: **Una vez al día** (o a mano: minuto `0`, hora `3`, día `*`, mes `*`, día de la semana `*`).
- *Comando* (cambiar `USUARIO` y la ruta por la del sistema en el hosting):

  ```bash
  cd /home/USUARIO/ruta/a/CR-Backend && /usr/local/bin/php artisan portal:limpiar-imagenes >> storage/logs/limpieza-portal.log 2>&1
  ```

Antes de guardarlo, conviene comprobar dos cosas en la *Terminal* de cPanel:

- **Qué `php` usar**: `which php` y `php -v`. Tiene que ser la misma versión que corre el sistema (8.3 o más); en cPanel suele estar en `/usr/local/bin/php` o en `/opt/cpanel/ea-php83/root/usr/bin/php`.
- **Que el comando funcione** corriéndolo una vez a mano con `--simular`.

La hora del cron es la del servidor de GoDaddy, que puede no ser la de Lima: a las 3:00 del servidor no importa la diferencia, porque la tarea solo borra lo suelto hace más de un día. La salida queda en `storage/logs/limpieza-portal.log`.

## Pendiente para la etapa 2 (el panel)

1. **Programar la limpieza de imágenes** (ver arriba). Mientras tanto, correrla a mano de vez en cuando.
2. **`APP_URL` en producción debe ser https** (o `PORTAL_URL_MEDIOS` apuntar a una dirección https). El contrato hace que el portal descarte cualquier respuesta con una URL `http://`. Hoy el `.env.example` de la raíz trae una IP con http.
3. **El panel no debe permitir guardar bloques que rompan el contrato** (tipo desconocido, lista sin ítems, carta sin firma, audio en un formato que el portal no reproduce, organigrama de más de 5 niveles). La API los omite y los anota en el registro (`App\Support\Portal\Bloques`), pero eso es la última red, no la validación.
4. **El contrato 2.13** (§7 del contrato): `GET /v1/navegacion`, `GET /v1/portada/cierre`, `etiquetas` en `/v1/solicitud` y `sitio.nombreEnLineas`, para que el menú, los títulos de sección y el cierre de Inicio salgan del panel. `portal_paginas` ya tiene `grupo` y `nombre` para el menú de Nosotros.
5. **Quién edita.** Los modelos ya registran cada cambio en la auditoría; falta decidir si basta el Administrador o si hace falta un rol nuevo (tipo «TIC»), y dar de alta el módulo en el menú.
6. **Portal React, «Síguenos»:** en las tarjetas de las redes, los nombres largos se cortan («FACEBOO…», «INSTAGRA…») a pantallas anchas. Es del repositorio del portal; se arregla al terminar el panel.
7. **Portal React, selector de grado:** muestra cada opción como «{opción} de {nivel}» («3 años de inicial»), así que un nivel que no es escolar queda raro («Música de talleres»). La solución va del lado del portal React; se ve junto con el contrato 2.13, en la etapa 10.
8. **«Actualizado el…» de las vacantes es manual, a propósito** (decisión del 2026-10-11): si se moviera solo, corregir una coma daría a entender que las vacantes se revisaron ese día.
