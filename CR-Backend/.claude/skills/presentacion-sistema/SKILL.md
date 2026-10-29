---
name: presentacion-sistema
description: Cómo armar una presentación (PowerPoint .pptx) de CATA-Recibo que muestre el sistema completo y cómo funciona, para el Consejo Directivo, la universidad, RR.HH. o cualquier público. Úsala siempre que se pida una presentación, un ppt, diapositivas, una exposición o una demo del sistema. Incluye cómo evaluar el enfoque, sacar capturas reales con los datos personales difuminados, la plantilla con los colores del colegio y la revisión visual.
---

# Presentación del sistema CATA-Recibo

Esta skill es el método que funcionó con la presentación al Consejo Directivo
(octubre de 2026). Úsala **junto con la skill pptx** (`anthropic-skills:pptx`):
de allí salen las reglas de pptxgenjs, `apply_theme.js` y `validate.py`; esta
pone lo propio del colegio.

## Lo que aprendimos (no repetir)

- **No es un deck de "lo que pedimos".** Una primera versión centrada en pedir
  aprobaciones, con diapositivas simples, fue rechazada: "muy básico". El
  usuario quiere **mostrar todo lo que tiene el sistema y cómo funciona**,
  módulo por módulo, con las pantallas reales. Si además hay que pedir algo,
  va al final y en una sola diapositiva, y solo si el usuario lo pide.
- **Nunca entrar con la cuenta de un trabajador real** para una captura (el
  sistema lo bloquea, y con razón). Se usa un Administrador temporal
  (`scripts/usuario-temporal.php`) y se borra al terminar.
- **Nada de datos personales legibles**: nombres, DNI y montos de cada persona
  van difuminados; los totales del colegio sí se muestran. La diapositiva lo
  dice ("Datos de personas difuminados").
- En Git Bash, `MSYS_NO_PATHCONV=1` al pasar rutas como `/inicio/empleados`, o
  se vuelven rutas de Windows y la captura sale de otra pantalla.

## 1. Evaluar antes de armar

Antes de escribir una sola diapositiva, decidir y decirle al usuario en dos
líneas:

- **Público**: Consejo Directivo (decide, poco tiempo, no técnico), RR.HH.
  (usa el sistema), universidad (evalúa el trabajo de prácticas), personal
  (aprende a usarlo). Cambia el tono y lo que se muestra.
- **Duración**: 1.5–2 min por diapositiva. 29 diapositivas ≈ 40 min; para 20
  min, juntar módulos o dejar fuera los de configuración.
- **Qué se lleva el público**: una frase. Con el Consejo fue "el sistema ya
  hace todo esto, con la ley y con seguridad".
- Si falta un dato que solo el usuario sabe (fecha, nombre del expositor,
  costos), ponerlo en las notas y avisarlo al entregar; no inventarlo.

## 2. Datos reales

Las cifras salen de la base, no se escriben de memoria (por tinker en
`cata-recibo-app-1`): trabajadores activos y totales, sedes y áreas, planillas
y neto del último mes, boletas emitidas, módulos (`modulos`), pruebas
(`php artisan test`), commits (`git log --oneline | wc -l`). Las leyes citadas
tienen que ser las que el sistema aplica de verdad (ver `docs/`).

## 3. Capturas

1. Chrome con `--remote-debugging-port=9222` (perfil aparte, `--headless=new`).
2. Usuario temporal: `scripts/usuario-temporal.php` → token; `/api/me` →
   `me.json`.
3. `scripts/capturas.mjs`: entra a cada pantalla a 1440×860 con escala 2,
   cambia el nombre de la cabecera a "Recursos Humanos", difumina lo personal
   y guarda `ppt/cap/*.png`. Actualizar en la lista `P` los ids de planilla y
   trabajador del momento.
4. Pasar a JPG de 2000 px (calidad ~86): con 26 capturas el pptx queda en
   ~4.5 MB en vez de 30.
5. Revisar una hoja de contactos con todas antes de seguir: que no se lea un
   nombre ni un DNI, y que ninguna pantalla haya quedado en blanco o cargando.
6. Borrar el usuario temporal.

## 4. Armar

Copiar `scripts/presentacion.js` (la del Consejo) y adaptarla. Ya trae:

- **Tema del colegio**: azul `1B4282` dominante, `0E2650` para fondos
  oscuros, dorado `F4B41A` solo como acento; Cambria en títulos, Calibri en el
  texto (vienen con Office y la revisión las ve iguales).
- **Layouts**: `OSCURO` (portada, separadores, cierre), `CONTENIDO` (título
  arriba) y `MODULO` (título a la izquierda), con logo y número de diapositiva
  en el layout.
- **Piezas**: `marco()` pone la captura dentro de una ventana de navegador con
  su dirección; `telefono()` la pone en un celular; `circulo()` es el motivo
  repetido (ícono en un círculo azul o dorado); `separador()` abre cada sección
  con su número; `modulo()` arma un módulo: nombre, mensaje, 3 puntos y una o
  dos capturas en cascada.
- **Estructura que funcionó**: portada → para quién es → mapa de lo que maneja
  → cifras reales → secciones (Personal, Planilla y boletas, Beneficios de ley,
  Portal y administración, Seguridad y calidad), cada una con su separador y
  sus módulos → cierre "CATA-Recibo hoy" + preguntas.
- **Notas del orador** en cada diapositiva, en palabras sencillas: lo que se
  dice mientras se muestra.

Para generar hace falta, en la carpeta de trabajo: `npm install pptxgenjs
react-icons react react-dom sharp`, los logos de `recursos/` y una copia de
`apply_theme.js` de la skill pptx (no se versiona en el repositorio: es de
Anthropic).

## 5. Revisar (obligatorio)

1. `python <skill pptx>/scripts/office/validate.py deck.pptx` → "All
   validations PASSED".
2. `scripts/exportar-pdf.ps1` (PowerPoint por COM; aquí no hay LibreOffice) →
   `pdftoppm -jpeg -r 60 deck.pdf slide` → mirar **todas** las diapositivas,
   en una hoja de contactos y luego las dudosas en grande.
3. Buscar: texto cortado o encimado, capturas que tapan texto, títulos que
   cambian de lugar, márgenes menores a 0.5", contraste bajo. Corregir en el
   generador y volver a revisar las cambiadas.

## 6. Entregar

- El `.pptx` y su PDF de vista previa en `docs/` con nombre claro
  (`CATA-Recibo - Presentación al <público>.pptx`).
- **No subirlo a GitHub sin preguntar**: el repositorio es público.
- Al entregar, decir en pocas líneas: la estructura, la duración estimada, qué
  datos faltan (fecha, expositor, costos) y qué no se pudo mostrar.
