// Manual de usuario de CATA-Recibo, versión 2.
// Uso (con `npm i docx`): node manual-de-usuario.js <carpeta de capturas> <salida.docx>
const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType, Table, TableRow, TableCell,
  WidthType, ShadingType, BorderStyle, LevelFormat, PageBreak, Header, Footer, PageNumber,
  VerticalAlign, ImageRun, TableOfContents,
} = require('docx');

const CAPTURAS = process.argv[2];
const SALIDA = process.argv[3] || 'Manual-de-usuario-CATA-Recibo.docx';

// ── Paleta del sistema: azul institucional + dorado ─────────────────
const AZUL = '1B4282';
const AZUL_OSCURO = '143567';
const DORADO = 'B8860B';
const GRIS_TEXTO = '2B2B2B';
const GRIS_SUAVE = '6B7280';
const LINEA = 'D9DEE8';
const FONDO_ALT = 'F2F5FA';
const FONDO_AVISO = 'FFF7E0';
const FUENTE = 'Calibri';
const ANCHO_UTIL = 9720; // twips: carta menos márgenes

// ── Ayudantes de texto ──────────────────────────────────────────────
/** Texto con **negritas** marcadas así. */
function corridas(texto, base = {}) {
  return texto.split(/(\*\*[^*]+\*\*)/g).filter(Boolean).map((t) => t.startsWith('**')
    ? new TextRun({ text: t.slice(2, -2), bold: true, size: 22, font: FUENTE, color: GRIS_TEXTO, ...base })
    : new TextRun({ text: t, size: 22, font: FUENTE, color: GRIS_TEXTO, ...base }));
}
const h1 = (text) => new Paragraph({ text, heading: HeadingLevel.HEADING_1, pageBreakBefore: true,
  border: { bottom: { color: DORADO, space: 4, style: BorderStyle.SINGLE, size: 12 } } });
const h2 = (text) => new Paragraph({ text, heading: HeadingLevel.HEADING_2 });
const h3 = (text) => new Paragraph({ text, heading: HeadingLevel.HEADING_3 });
const p = (text) => new Paragraph({ spacing: { after: 160, line: 276 }, children: corridas(text) });
const espacio = (h = 80) => new Paragraph({ spacing: { after: h }, children: [] });

function nota(text, titulo = 'Nota') {
  return new Paragraph({
    spacing: { before: 60, after: 200, line: 264 }, indent: { left: 260 },
    border: { left: { color: DORADO, space: 8, style: BorderStyle.SINGLE, size: 18 } },
    children: [new TextRun({ text: `${titulo}: `, bold: true, size: 21, font: FUENTE, color: AZUL_OSCURO }),
      ...corridas(text, { size: 21, italics: true })],
  });
}
function importante(text) {
  return new Paragraph({
    spacing: { before: 80, after: 220, line: 264 }, indent: { left: 200, right: 200 },
    shading: { type: ShadingType.CLEAR, fill: FONDO_AVISO },
    border: { left: { color: 'D97706', space: 8, style: BorderStyle.SINGLE, size: 24 } },
    children: [new TextRun({ text: 'Importante: ', bold: true, size: 21, font: FUENTE, color: '92400E' }), ...corridas(text, { size: 21 })],
  });
}
const bullets = (items) => items.map((text) => new Paragraph({
  numbering: { reference: 'bullets', level: 0 }, spacing: { after: 90, line: 264 }, children: corridas(text) }));
const pasos = (items) => items.map((text, i) => new Paragraph({
  spacing: { after: 100, line: 264 }, indent: { left: 360, hanging: 360 },
  children: [new TextRun({ text: `${i + 1}.\t`, bold: true, size: 22, font: FUENTE, color: AZUL_OSCURO }), ...corridas(text)] }));
const quienVe = (texto) => new Paragraph({ spacing: { before: 40, after: 200 }, children: [
  new TextRun({ text: '¿Quién ve esto?  ', bold: true, size: 20, font: FUENTE, color: AZUL_OSCURO }),
  new TextRun({ text: texto, size: 20, font: FUENTE, color: GRIS_SUAVE, italics: true })] });

// ── Imágenes ────────────────────────────────────────────────────────
function tamPng(buf) { return { w: buf.readUInt32BE(16), h: buf.readUInt32BE(20) }; }
let numFigura = 0;
function img(archivo, pie, anchoPx = 620, recorteAlto = null) {
  const ruta = path.join(CAPTURAS, archivo);
  if (!fs.existsSync(ruta)) { console.warn('Falta la captura', archivo); return []; }
  const data = fs.readFileSync(ruta);
  const { w, h } = tamPng(data);
  const alto = Math.round(anchoPx * h / w);
  numFigura++;
  return [
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 120, after: 60 }, keepNext: true,
      children: [new ImageRun({ type: 'png', data, transformation: { width: anchoPx, height: recorteAlto ?? alto },
        outline: { type: 'solidFill', solidFillType: 'rgb', value: LINEA } })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 240 },
      children: [new TextRun({ text: `Figura ${numFigura}. ${pie}`, size: 18, italics: true, font: FUENTE, color: GRIS_SUAVE })] }),
  ];
}

// ── Tablas ──────────────────────────────────────────────────────────
function celda(text, { header = false, width, shade, bold = false } = {}) {
  return new TableCell({
    width: width ? { size: width, type: WidthType.DXA } : undefined, verticalAlign: VerticalAlign.CENTER,
    shading: header ? { type: ShadingType.CLEAR, fill: AZUL } : shade ? { type: ShadingType.CLEAR, fill: shade } : undefined,
    margins: { top: 80, bottom: 80, left: 110, right: 110 },
    children: [new Paragraph({ children: corridas(String(text), { size: 19, bold: header || bold, color: header ? 'FFFFFF' : GRIS_TEXTO }) })],
  });
}
function tabla(cabecera, filas, anchos) {
  const total = anchos.reduce((a, b) => a + b, 0);
  const escala = ANCHO_UTIL / total;
  const an = anchos.map((x) => Math.round(x * escala));
  return [new Table({
    width: { size: ANCHO_UTIL, type: WidthType.DXA }, columnWidths: an,
    rows: [
      new TableRow({ tableHeader: true, children: cabecera.map((t, i) => celda(t, { header: true, width: an[i] })) }),
      ...filas.map((f, r) => new TableRow({ children: f.map((t, i) => celda(t, { width: an[i], shade: r % 2 ? FONDO_ALT : undefined })) })),
    ],
  }), espacio(200)];
}

// ════════════════════════════════════════════════════════════════════
// CONTENIDO
// ════════════════════════════════════════════════════════════════════
const portada = [
  new Paragraph({ spacing: { before: 1400 }, children: [] }),
  ...(fs.existsSync(path.join(CAPTURAS, 'logo.png')) ? [new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 300 },
    children: [new ImageRun({ type: 'png', data: fs.readFileSync(path.join(CAPTURAS, 'logo.png')), transformation: { width: 130, height: 130 } })] })] : []),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 120 },
    children: [new TextRun({ text: 'MANUAL DE USUARIO', bold: true, size: 52, font: FUENTE, color: AZUL_OSCURO })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 360 },
    children: [new TextRun({ text: 'Sistema CATA-Recibo', bold: true, size: 40, font: FUENTE, color: DORADO })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 40 },
    children: [new TextRun({ text: 'Planillas, boletas de pago y documentos del personal', size: 24, font: FUENTE, color: GRIS_SUAVE, italics: true })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 1100 },
    children: [new TextRun({ text: 'Asociación Educativa Colegio Adventista Túpac Amaru — Juliaca', size: 24, font: FUENTE, color: GRIS_SUAVE })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 40 },
    children: [new TextRun({ text: 'Versión 2 — completa', size: 20, font: FUENTE, color: GRIS_SUAVE })] }),
  new Paragraph({ alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: '5 de octubre de 2026', size: 20, font: FUENTE, color: GRIS_SUAVE })] }),
];

const indice = [
  new Paragraph({ children: [new PageBreak()] }),
  new Paragraph({ spacing: { after: 200 }, children: [new TextRun({ text: 'Contenido', bold: true, size: 32, font: FUENTE, color: AZUL_OSCURO })] }),
  new TableOfContents('Contenido', { hyperlink: true, headingStyleRange: '1-2' }),
  p('Si el índice aparece vacío, en Word haz clic derecho sobre él y elige «Actualizar campos».'),
];

// ── 1 ───────────────────────────────────────────────────────────────
const cap1 = [
  h1('1. Introducción'),
  p('CATA-Recibo es el sistema con el que el colegio lleva su personal, arma las **planillas** de cada mes, emite las **boletas de pago** y guarda los **documentos** de cada trabajador. Reemplaza el papel: la boleta que antes se imprimía y se firmaba a mano ahora se emite, se revisa y se firma dentro del sistema, y queda guardada con su fecha.'),
  p('El manual está ordenado por lo que cada persona hace. Primero lo que usa todo el personal: entrar, ver y firmar su boleta, sus documentos y sus vacaciones. Después lo que usa Recursos Humanos cada mes, y al final lo que solo toca el Administrador. No hace falta leerlo entero: busca la sección que te corresponde.'),
  h2('1.1 Qué trae esta versión'),
  p('Esta versión reemplaza a la del 30 de setiembre de 2026. Lo nuevo es:'),
  ...bullets([
    '**Cálculos como el PLAME del colegio.** Se comprobó contra el PLAME de setiembre 2026 y salen iguales 93 de 94 trabajadores en sueldo, bonificación, asignación, ONP/AFP, Renta de 5ta, diezmo, EsSalud y neto (sección 9).',
    '**Renta de 5ta como el Excel de RR.HH.** Un solo cálculo del año y el mismo monto de marzo a diciembre (sección 9.9).',
    '**Bonificación por cargo en la ficha** del trabajador: cada planilla la trae sola (sección 6.3).',
    '**Montos de ley por año** en Ajustes del sistema: UIT, sueldo mínimo, asignación familiar, ONP, EsSalud y AFP. Nada está escrito a mano en el sistema (sección 14.5).',
    '**La boleta impresa igual a la boleta física del colegio**, con todas sus filas aunque estén en cero, y el nombre oficial del año arriba (sección 10.4).',
    '**Planillas de años anteriores** y varios meses de una vez, para dejar registro de lo que se pagó antes del sistema (sección 8.2).',
    '**Cerrar y abrir una planilla** ya pagada, para que nadie le mueva cifras (sección 8.5).',
  ]),
  h2('1.2 Las tres cuentas'),
  ...tabla(['Cuenta', 'Qué puede hacer'], [
    ['Administrador', 'Todo: además de lo de RR.HH., las cuentas de usuario, los roles, el menú, la Auditoría y los Ajustes del sistema (montos de ley, años anteriores).'],
    ['RR.HH.', 'Lleva el personal: empleados, contratos, planillas, boletas, documentos y vacaciones de todo el colegio.'],
    ['Empleado (trabajador)', 'Ve y firma sus propias boletas, sus documentos y sus vacaciones. No ve nada del resto del personal.'],
  ], [2200, 7500]),
];

// ── 2 ───────────────────────────────────────────────────────────────
const cap2 = [
  h1('2. Cómo entrar al sistema'),
  h2('2.1 Iniciar sesión'),
  quienVe('Todo el personal'),
  ...img('01-login.png', 'Pantalla de inicio de sesión.', 560),
  ...pasos([
    'Abre la dirección del sistema en el navegador (Chrome, Edge o Firefox).',
    'Escribe tu **correo** en el primer campo.',
    'Escribe tu **contraseña**. El ícono del ojo la muestra, por si quieres revisar lo que escribiste.',
    'Presiona **Ingresar**.',
  ]),
  nota('Si tienes activado Bloq Mayús, el sistema te avisa antes de entrar: una contraseña con las letras al revés no es la misma contraseña.'),
  h2('2.2 El primer ingreso'),
  p('Las cuentas nacen con una **contraseña provisional: el DNI del trabajador**. La primera vez que entras, el sistema no te deja ir a ninguna otra pantalla hasta completar dos pasos:'),
  ...pasos([
    '**Leer y aceptar los términos de uso** (la «Entrega digital de boletas de pago»). Es la misma hoja que antes se firmaba en papel; aceptarla vale igual y queda registrada con la fecha y la hora.',
    '**Poner una contraseña propia**: al menos **8 caracteres, con letras y números**, y que no sea tu DNI. La provisional deja de servir en cuanto confirmas la nueva.',
  ]),
  ...img('37-terminos.png', 'Los términos de uso: se leen, se marca la casilla y «Aceptar y continuar».', 520),
  ...img('36-cambiar-clave.png', 'Poner la contraseña propia en el primer ingreso.', 520),
  nota('Si los términos cambian de versión más adelante, el sistema te los vuelve a pedir la próxima vez que entres.'),
  h2('2.3 Olvidé mi contraseña'),
  quienVe('Todo el personal'),
  ...pasos([
    'En la pantalla de inicio de sesión presiona **Recupérala por correo**.',
    'Escribe el correo con el que entras y confirma.',
    'Revisa tu correo: llega un enlace para poner una contraseña nueva. El enlace vence pasado un tiempo; si ya no sirve, pide otro.',
    'Escribe la contraseña nueva y confirma.',
  ]),
  ...img('02-olvide.png', 'Recuperar la contraseña por correo.', 520),
  nota('Si no llega el correo, revisa la carpeta de spam. Si sigue sin llegar, avisa a RR.HH.: puede que tu correo esté mal escrito en el sistema.'),
  h2('2.4 Cerrar sesión'),
  p('Abajo a la izquierda, **Cerrar sesión**. Por seguridad, si pasas **dos horas sin usar el sistema**, la sesión se cierra sola; mientras trabajas no se cae, porque cada acción reinicia la cuenta.'),
];

// ── 3 ───────────────────────────────────────────────────────────────
const cap3 = [
  h1('3. La pantalla y el menú'),
  p('A la izquierda está el **menú**, que se arma solo según tu cuenta: nadie ve una pantalla a la que no tiene acceso. Arriba está la **cabecera** con la ruta de donde estás y cuatro botones:'),
  ...bullets([
    '**Luna / sol**: cambia entre modo claro y oscuro. Se recuerda en tu computadora.',
    '**Bombilla — Primeros pasos**: una guía corta de lo que te toca hacer primero según tu cuenta. El número indica cuántos pasos te faltan.',
    '**Campana — Notificaciones**: avisos como «tienes una boleta por firmar». Al presionar uno te lleva directo a lo que avisa.',
    '**Tu nombre — Mi perfil**: tus datos (DNI, área, sede, contrato, fecha de ingreso), tu **foto** (puedes subirla o cambiarla) y el botón para **cambiar tu contraseña**.',
  ]),
  p('El menú tiene cuatro grupos:'),
  ...bullets([
    '**Inicio**: el Panel de Control (Administrador y RR.HH.).',
    '**Boletas y Finanzas**: Empleados, Planillas, Boletas, Documentos, Contratos y Vacaciones (Administrador y RR.HH.).',
    '**Configuración**: Áreas, Cargos, Tipos de Contrato, Sedes, Periodos y Conceptos de Pago; y solo para el Administrador: Usuarios, Roles, Módulos, Módulos Padre, Auditoría y Ajustes del sistema.',
    '**Mi Espacio**: Mis Boletas, Mis Documentos y Mis Vacaciones. Lo tiene toda cuenta con ficha de trabajador.',
  ]),
  ...tabla(['Pantalla', 'Administrador', 'RR.HH.', 'Empleado'], [
    ['Panel de Control', 'Sí', 'Sí', 'No'],
    ['Empleados (y su importación desde Excel)', 'Sí', 'Sí', 'No'],
    ['Planillas (e importar conceptos)', 'Sí', 'Sí', 'No'],
    ['Boletas (emisión)', 'Sí', 'Sí', 'No'],
    ['Documentos del personal e Historial de boletas', 'Sí', 'Sí', 'No'],
    ['Contratos', 'Sí', 'Sí', 'No'],
    ['Vacaciones (aprobar)', 'Sí', 'Sí', 'No'],
    ['Áreas, Cargos, Tipos de Contrato, Sedes, Periodos, Conceptos de Pago', 'Sí', 'Sí', 'No'],
    ['Usuarios, Roles, Módulos, Módulos Padre', 'Sí', 'No', 'No'],
    ['Auditoría', 'Sí', 'No', 'No'],
    ['Ajustes del sistema', 'Sí', 'No', 'No'],
    ['Mis Boletas, Mis Documentos, Mis Vacaciones', 'Sí*', 'Sí*', 'Sí'],
  ], [5200, 1500, 1500, 1500]),
  p('* Si la cuenta está vinculada a una ficha de trabajador.'),
  p('Todas las listas tienen un **buscador** arriba, y muchas tienen **Filtros** (por área, cargo, sede, tipo de contrato o estado) y **chips** para filtrar con un clic. Las listas largas van por páginas.'),
];

// ── 4 ───────────────────────────────────────────────────────────────
const cap4 = [
  h1('4. Mi Espacio — para todo el personal'),
  p('Es la parte del sistema donde cada quien ve **lo suyo**, nada del resto del personal.'),
  h2('4.1 Mis Boletas'),
  quienVe('Todo el personal'),
  p('Aparecen, año por año, las boletas que te emitió el colegio. Cada fila dice el mes, el **neto a pagar** y cuatro momentos: cuándo se te **avisó** (y a qué correo), cuándo la **revisaste**, cuándo la **descargaste** y cuándo la **firmaste**. Arriba se cuentan las del año, las firmadas y las que te faltan firmar.'),
  ...img('33-mis-boletas.png', 'Mis Boletas: la boleta de octubre, con su aviso enviado y pendiente de firma.'),
  h3('Ver y firmar una boleta'),
  ...pasos([
    'Presiona el ícono de la **boleta** (el primero en Acciones) para verla. Revisa los montos: ingresos, descuentos, aportes del colegio y el neto a pagar.',
    'Si está correcta, presiona el ícono de **firma** (el verde).',
    'Escribe la **contraseña con la que entras al sistema** y presiona **Firmar boleta**. Esa contraseña es tu firma: confirma que la revisaste y estás de acuerdo.',
    'Ya firmada, aparece el botón **Descargar** para bajarla en PDF. Si registraste tu firma dibujada (sección 4.2), sale estampada en la boleta.',
  ]),
  ...img('38-mis-boletas-firmar.png', 'La boleta abierta para revisarla. «Fírmala y podrás descargarla».'),
  ...img('39-firmar-boleta.png', 'Firmar la boleta con la contraseña de la cuenta.', 520),
  importante('No puedes descargar una boleta sin firmarla. Si un monto no te cuadra, **no la firmes**: avisa a RR.HH. para que lo revise antes.'),
  h2('4.2 Mis Documentos'),
  quienVe('Todo el personal'),
  p('Tus boletas y los documentos a tu nombre: hoja de vida, contrato, certificados, copia del DNI. Desde aquí:'),
  ...bullets([
    '**Ver y firmar** los documentos que lo piden (igual que la boleta).',
    '**Subir un documento** tuyo: tu hoja de vida, tu foto, la copia de tu DNI o un certificado.',
    '**Registrar mi firma**: dibujas tu firma con el dedo o el mouse, como en papel. Esa firma es la que sale impresa en tus boletas cuando las firmas. Puedes borrarla y repetirla las veces que quieras.',
  ]),
  ...img('34-mis-documentos.png', 'Mis Documentos.'),
  ...img('40-registrar-firma.png', 'Dibujar la firma que saldrá en la boleta.', 540),
  h2('4.3 Mis Vacaciones'),
  quienVe('Todo el personal con contrato a plazo indeterminado'),
  p('Arriba, los **días que te quedan**. Debajo, tus solicitudes con su estado: pendiente, aprobada o rechazada.'),
  ...pasos([
    'Presiona **Solicitar vacaciones**.',
    'Elige la fecha de inicio y la de fin, y escribe el motivo si quieres.',
    'Confirma. Queda **Pendiente** hasta que RR.HH. la apruebe o la rechace.',
  ]),
  ...img('35-mis-vacaciones.png', 'Mis Vacaciones.'),
  nota('Solo piden vacaciones quienes tienen **contrato a plazo indeterminado**. A los contratados, de suplencia o prácticas no se les da descanso: al terminar el contrato se les pagan las **Vacaciones Truncas**. Cada trabajador tiene **30 días al año** una vez cumplido su año de servicio; antes, la parte proporcional.'),
];

// ── 5 ───────────────────────────────────────────────────────────────
const cap5 = [
  h1('5. Panel de Control'),
  quienVe('Administrador y RR.HH.'),
  p('Es lo primero que ve RR.HH. al entrar: el resumen del mes. Arriba se cambia el **mes** (flechas o calendario) y la **sede**; el botón verde **Exportar** baja el resumen en Excel; el ícono del pastel avisa los **cumpleaños** del mes.'),
  ...img('03-panel.png', 'Panel de Control de octubre 2026.'),
  ...bullets([
    '**Pendientes**: cuántos trabajadores tienen planilla pero aún no boleta emitida, y cuántas boletas del mes faltan firmar. Al presionar, te lleva a la lista.',
    '**Cifras**: personal activo, nómina del mes (neto total), porcentaje de boletas emitidas y contratos por vencer en 30 días.',
    '**Gráficos**: remuneración por área, sistema de pensiones del personal, avance de firmas, tendencia de la nómina mes a mes, tipos de contrato, contratos por vencer (60 días), a dónde se va la nómina (sueldos, bonificaciones, descuentos, adelantos, aportes), personal por sede, antigüedad y los conceptos que más pesan.',
  ]),
];

// ── 6 ───────────────────────────────────────────────────────────────
const cap6 = [
  h1('6. Empleados'),
  quienVe('Administrador y RR.HH.'),
  p('El padrón del personal. Cada trabajador tiene una **ficha** con sus datos personales, laborales, de planilla, complementarios y de acceso. Al dar de alta a alguien, el sistema le crea al mismo tiempo su **cuenta de acceso**.'),
  ...img('04-empleados.png', 'Lista de Empleados con sus botones: ver ficha, editar y dar de baja.'),
  ...bullets([
    '**Nuevo Empleado**: alta de uno en uno (sección 6.1).',
    '**Importar empleados**: altas y cambios de muchos a la vez desde Excel (sección 6.4).',
    '**Descargar empleados**: toda la lista en Excel, con los filtros que tengas puestos. Sirve también para corregir datos y volver a subirla.',
    'En cada fila: **ver ficha** (persona), **editar** (lápiz) y **dar de baja** (papelera).',
  ]),
  h2('6.1 Dar de alta a un trabajador'),
  ...pasos([
    'Presiona **Nuevo Empleado**.',
    '**Personales**: escribe el DNI y presiona **Buscar**: el sistema trae nombres y apellidos del padrón. Completa fecha de nacimiento, teléfono y dirección. (El DNI acepta 8 dígitos, o 9 para carné de extranjería.)',
    '**Laborales**: área, cargo (solo salen los del área elegida), sede, fecha de ingreso, tipo de contrato y, si el contrato tiene plazo, su **fecha de término**. Luego el **sueldo base** y la **bonificación por cargo** si la tiene.',
    '**Planilla**: sistema de pensión (ONP, AFP o ninguno), AFP y tipo de comisión (flujo o mixta), CUSPP, banco, número de cuenta, CCI, forma de pago, si **tiene hijos** (asignación familiar) y si se le aplica el **diezmo**.',
    '**Complementarios**: estudios, especialidad, institución, contacto de emergencia.',
    '**Acceso**: el correo de su cuenta. **Toda cuenta nueva nace como Empleado**; si alguien debe ser RR.HH. o Administrador, se cambia después en Usuarios.',
    'Guarda. Su contraseña inicial es su **DNI**.',
  ]),
  ...img('05-empleado-nuevo.png', 'Nuevo Empleado: un asistente de cinco pasos.'),
  h2('6.2 Ver y editar la ficha'),
  p('**Ver ficha** muestra todo sin poder cambiarlo. **Editar** abre el mismo asistente con los datos cargados. El paso **Laborales** muestra además sus contratos.'),
  ...img('07-empleado-laborales.png', 'Editar: paso Laborales, con el sueldo base y la bonificación por cargo.'),
  h2('6.3 Bonificación por cargo'),
  p('Es lo que cobra cada mes por su función (coordinación, jefatura, dirección): la «Bonificación por Función» del PLAME. Se escribe **una vez en la ficha**, en Laborales, y **cada planilla nueva la trae sola**, por los mismos días trabajados que el sueldo. Suma a la base de ONP/AFP, EsSalud y diezmo, y a la Renta de 5ta. Vacío o 0 si no tiene.'),
  nota('Si cambias la bonificación de alguien después de armar su planilla del mes, entra a su planilla y presiona **Recalcular sueldo** (sección 8.4).'),
  h2('6.4 Importar empleados desde Excel'),
  p('Para dar de alta o actualizar a muchos de una vez. Tiene cuatro pasos: **Subir archivos → Reconocer columnas → Revisar cambios → Listo**.'),
  ...img('08-importar-empleados.png', 'Importar empleados desde Excel.'),
  ...pasos([
    'Si no tienes el archivo, presiona **Descargar modelo**: trae los títulos que el sistema reconoce, listas para elegir área, cargo o sede, y una hoja de instrucciones.',
    'Arrastra tu Excel (.xlsx). El archivo se lee en tu computadora; no se sube entero al servidor.',
    'El sistema reconoce cada columna por su título (acepta los nombres de ustedes: «Sueldo», «Bonificación por función», «Hijos»…). Revisa y corrige si algo quedó mal.',
    'En **Revisar cambios** ves qué pasará con cada fila: altas, actualizaciones, errores y avisos. Nada se guarda todavía.',
    'Si todo está bien, confirma. Se guarda todo o nada.',
  ]),
  ...bullets([
    'Con un **DNI nuevo** se da de alta. Con uno que **ya existe**, solo se cambian las celdas que tengan algo escrito: **una celda vacía no borra nada**.',
    'Para registrar a alguien que **ya se fue**, pon **Cesado** en Estado y su fecha de cese.',
    'A quien está en **ONP** (o no aporta) se le escribe **No aplica** en AFP, Tipo de comisión AFP y CUSPP: así el Excel queda lleno y se le borra cualquier dato de AFP que tuviera. El CUSPP acepta la Ñ.',
    'La **fecha de cese** de un contratado que sigue trabajando es el fin programado de su contrato: no lo da de baja. Quien manda es la columna Estado.',
    'El tipo de contrato y la fecha de ingreso de alguien que ya existe no se cambian desde el Excel: se hace desde Contratos.',
  ]),
  h2('6.5 Dar de baja'),
  p('La papelera de la fila **da de baja** al trabajador: queda como Cesado con la fecha de hoy, pierde el acceso al sistema y su contrato vigente se cierra. **No se borra nada**: su expediente, sus boletas y sus contratos se conservan.'),
];

// ── 7 ───────────────────────────────────────────────────────────────
const cap7 = [
  h1('7. Contratos'),
  quienVe('Administrador y RR.HH.'),
  p('El vínculo laboral de cada trabajador. **Cada uno tiene un solo contrato vigente a la vez.** Al registrar uno nuevo, el sistema cierra solo el anterior, así que **Nuevo Contrato** es, en la práctica, renovar.'),
  ...img('09-contratos.png', 'Contratos: tipo, desde, hasta, estado y motivo de fin.'),
  ...bullets([
    'Filtra por empleado o por estado (vigente, finalizado).',
    'Con el ícono de **subir** adjuntas el **contrato firmado** escaneado: queda en su expediente.',
    'Eliminar un contrato es una baja lógica: deja de listarse pero no se pierde.',
    'Los tipos de contrato (Contratado, Contrato/Parcial, Plazo indeterminado, Prácticas) se administran en Configuración → Tipos de Contrato. Solo el plazo indeterminado da derecho a pedir vacaciones.',
  ]),
];

// ── 8 ───────────────────────────────────────────────────────────────
const cap8 = [
  h1('8. Planillas'),
  quienVe('Administrador y RR.HH.'),
  p('Una **planilla** es un grupo con nombre de trabajadores que se pagan juntos en un mes («Planilla General», «Docentes de secundaria», «TIC»). Dentro, cada trabajador tiene **su** planilla del mes: su sueldo y sus líneas de concepto, que son exactamente lo que sale en su boleta.'),
  ...img('10-planillas.png', 'Planillas de octubre 2026: una planilla general con 96 trabajadores.'),
  ...bullets([
    'Arriba eliges el **mes** y el **año** que quieres ver.',
    '**Nueva planilla**: crearla y, si quieres, llenarla de una vez (sección 8.2).',
    '**Importar conceptos**: cargar descuentos, adelantos y bonos del mes desde Excel (sección 8.6).',
    '**Descargar reporte**: la planilla completa del mes en Excel, con todos sus conceptos (como el PLAME).',
    'En cada fila: **Ver** (sus trabajadores), **cerrar/abrir** (candado), editar y eliminar.',
    'Si hay trabajadores con planilla pero sin grupo, aparece un aviso con **Verlos y agruparlos**.',
  ]),
  h2('8.1 Antes de empezar: el periodo'),
  p('Toda planilla pertenece a un **periodo** (por ejemplo «Año Escolar 2026», de enero a diciembre). Si no hay ninguno, créalo primero en Configuración → Periodos.'),
  h2('8.2 Crear una planilla'),
  ...pasos([
    'Presiona **Nueva planilla**.',
    'Escribe un **nombre** (no se puede repetir en el mismo mes) y elige el **periodo**.',
    'Elige el **mes a generar**. Viene puesto el mes en curso.',
    'Deja marcado **Armarle la planilla a un grupo ahora mismo** y elige a quiénes: **todo el personal activo** o **solo a quienes yo elija** (por área, cargo, sede o uno por uno).',
    'Presiona **Crear planilla**. Al final ves cuántos se generaron y cuántos se omitieron, y por qué.',
  ]),
  ...img('10b-nueva-planilla.png', 'Nueva planilla: nombre, periodo, mes y a quiénes.'),
  ...bullets([
    'A cada uno se le arma su planilla con **todos sus conceptos de ley ya calculados**: sueldo por días trabajados, bonificación por cargo, asignación familiar, ONP o AFP, EsSalud, diezmo, gratificación (julio y diciembre) y Renta de 5ta.',
    '**A quien ya tiene planilla de ese mes se le omite**: puedes repetirlo sin duplicar nada.',
    '**Varios meses de una vez**: marca la casilla y elige los meses. Sirve para registrar meses que ya pasaron.',
    'Un **mes futuro** se calcula con los sueldos de hoy; si alguno cambia, habrá que recalcular.',
  ]),
  h3('Planillas de años anteriores'),
  p('Si el Administrador lo permite (Ajustes del sistema, sección 14.5), se pueden armar planillas de **años anteriores** para dejar registro de lo que se pagó cuando se firmaba en papel. En esas planillas entran también quienes ya cesaron, si trabajaban ese mes (con su sueldo proporcional a los días), y sus boletas quedan como **firmadas en papel**: no se le avisa a nadie ni se le pide firmar.'),
  nota('El sueldo sale de la ficha de hoy, aunque en ese año haya sido otro.'),
  h2('8.3 Dentro de una planilla'),
  p('Al presionar **Ver** aparecen sus trabajadores con su sueldo base del mes y su neto a pagar.'),
  ...img('11-planilla-corrida.png', 'Los trabajadores de la Planilla General de octubre.'),
  ...bullets([
    '**Agregar trabajadores**: armarle la planilla de este mes a más gente.',
    '**Aplicar concepto**: poner un mismo concepto a varios a la vez (por ejemplo una CTS, un bono o un descuento), con un monto fijo o un porcentaje del sueldo. Se ve el monto previsto antes de aplicarlo.',
    '**Descargar reporte**: el Excel solo de esta planilla.',
    'En cada fila: ver su detalle (boleta), **sacarlo de la planilla** (círculo) o eliminar su planilla del mes.',
  ]),
  h2('8.4 La planilla de un trabajador'),
  p('Muestra su sueldo base, lo que suma, lo que descuenta, el neto a pagar y lo que aporta el colegio. Debajo, cada **línea de concepto**: es lo mismo que saldrá en su boleta.'),
  ...img('12-planilla-detalle.png', 'La planilla de Alcides Huacasi, octubre 2026: neto S/ 3,284.08.'),
  ...bullets([
    '**Agregar concepto**: una línea para esta persona este mes: un adelanto, un descuento puntual, una bonificación. Con monto fijo o porcentaje, y un detalle opcional que sale en la boleta («Otros Conceptos: Subsidio de maternidad»).',
    '**Editar** o **quitar** una línea con los botones de la fila.',
    '**Recalcular sueldo**: vuelve a leer la ficha **de hoy** (sueldo, bonificación por cargo, hijos, pensión, diezmo, días trabajados) y recalcula todos los conceptos de ley. Úsalo cuando corriges la ficha después de armar la planilla. No toca las líneas que agregaste a mano.',
  ]),
  nota('Los conceptos de ley (pensión, EsSalud, asignación familiar, Renta de 5ta) se calculan solos. Agrega a mano solo lo que sea de esta persona en este mes.'),
  h2('8.5 Cerrar y abrir una planilla'),
  p('Cuando una planilla ya se pagó, **ciérrala** con el candado de su fila en la lista de Planillas. Cerrada queda como está: no se le pueden agregar ni sacar trabajadores, recalcular sueldos ni cambiar conceptos. Si hay que corregir algo, se **abre** con el mismo botón, se corrige y se vuelve a cerrar. El sistema pregunta antes de hacerlo.'),
  h2('8.6 Importar conceptos desde Excel'),
  p('Para cargar de una vez los conceptos del mes de **todas** las planillas: escolaridad, comedor, copias, tardanzas, adelantos, movilidad, bonos… Cuatro pasos: **Subir el Excel → Reconocer columnas → Revisar cambios → Listo**.'),
  ...img('13-importar-conceptos.png', 'Importar conceptos desde Excel.'),
  ...pasos([
    'Elige el **mes y año** de las planillas. Las planillas de ese mes tienen que existir ya.',
    'Si no tienes el archivo, **Descargar modelo** trae a los trabajadores con planilla ese mes y una columna por cada concepto del catálogo.',
    'Arrastra tu Excel. El sistema propone a qué concepto corresponde cada columna, aunque uses tus propios nombres («Movilidad», «Tardanza», «Bazar»). Una columna con un nombre que no existe se puede **crear como concepto nuevo**.',
    'En **Revisar cambios** ves, trabajador por trabajador, las líneas nuevas, cambiadas y quitadas. Nada se guarda todavía.',
    'Confirma. Se guarda todo o nada, y queda en la Auditoría.',
  ]),
  ...tabla(['En la celda escribes…', 'Qué pasa'], [
    ['Nada (vacía)', 'No se toca lo que ya tenga esa persona.'],
    ['0', 'Se le quita ese concepto.'],
    ['Un monto (120 o 35.50)', 'Se le pone ese monto.'],
  ], [3000, 6700]),
  nota('Las columnas de nombre, cargo o totales se ignoran solas. Si tu archivo es .xls antiguo, ábrelo en Excel y guárdalo como «Libro de Excel (.xlsx)». Los nombres que confirmas se recuerdan: el mes siguiente se reconocen solos.'),
];

// ── 9 ───────────────────────────────────────────────────────────────
const cap9 = [
  h1('9. Cómo se calcula cada monto'),
  p('Todo lo de esta sección lo hace el sistema solo al armar o recalcular una planilla. Los porcentajes y montos de ley salen de **Ajustes del sistema → Montos de ley**, del año de la planilla: nada está escrito a mano. Se comprobó contra el **PLAME de setiembre 2026**: iguales 93 de 94 trabajadores.'),
  h2('9.1 Días trabajados'),
  p('El sistema cuenta **días hábiles de lunes a viernes** (los feriados se pagan, así que cuentan). Quien está todo el mes cobra el sueldo completo. Quien **entra o cesa a mitad de mes** cobra solo sus días:'),
  ...tabla(['Caso (octubre 2026: 22 días hábiles)', 'Días que se pagan', 'Sueldo de S/ 2,200'], [
    ['Entró el 01/01/2026 (o antes)', '22', 'S/ 2,200.00 completo'],
    ['Entra el jueves 1 de octubre', '22', 'S/ 2,200.00 completo'],
    ['Entra el jueves 29 de octubre', '2 (jueves y viernes)', 'S/ 200.00'],
  ], [4200, 2400, 3100]),
  p('Las vacaciones aprobadas no restan sueldo: esos días salen en la boleta como **Días de vacaciones** en lugar de días trabajados.'),
  h2('9.2 Ingresos'),
  ...bullets([
    '**Remuneración básica**: el sueldo de la ficha por los días trabajados.',
    '**Bonificación por cargo**: la de la ficha, por los mismos días.',
    '**Asignación familiar**: el **10% del sueldo mínimo (RMV)** del año a quien tiene hijos (S/ 113.00 con la RMV de S/ 1,130). Se paga **completa**, aunque trabaje pocos días.',
    '**Vacaciones truncas**: solo a quien no tiene contrato indeterminado; el monto lo pone RR.HH.',
    '**Gratificación** (julio y diciembre): sueldo + asignación, proporcional a los meses trabajados del semestre (enero–junio o julio–diciembre), más la **Bonificación Extraordinaria** igual a la tasa de EsSalud (9%), Ley 30334.',
    '**Planilla de movilidad**: no es remuneración: no paga pensión, EsSalud ni Renta de 5ta.',
  ]),
  h2('9.3 La base afecta'),
  p('Es la base sobre la que se calculan ONP/AFP, EsSalud y diezmo, igual que el PLAME: **básica + bonificación por cargo + asignación familiar + vacaciones truncas**.'),
  h2('9.4 ONP'),
  p('**13%** de la base afecta, a quien está en el Sistema Nacional de Pensiones.'),
  h2('9.5 AFP'),
  ...bullets([
    '**Fondo**: 10% de la base afecta.',
    '**Prima de seguro**: 1.37%, igual para todas las AFP. **No se cobra desde el mes en que la persona ya tiene 65 años cumplidos** al día 1.',
    '**Comisión**: según su AFP (Habitat 1.47%, Integra 1.55%, Prima 1.60%, Profuturo 1.69%), solo a quien está en **comisión por flujo**. A quien está en **comisión mixta** no se le cobra en planilla: la AFP la cobra de su fondo.',
  ]),
  h2('9.6 EsSalud'),
  p('**9%** de la base afecta. Lo paga el colegio, no se descuenta al trabajador. **Nunca se calcula sobre menos que el sueldo mínimo** (proporcional a los días si trabajó parte del mes). Ejemplo del PLAME: Sonco Ramos, sueldo S/ 582.80 → EsSalud S/ 101.70 (9% de 1,130), no S/ 52.45.'),
  h2('9.7 Diezmo'),
  p('**10% de la base afecta** a todo el personal, salvo a quien tiene **Diezmo: No** en su ficha.'),
  h2('9.8 Otros descuentos y adelantos'),
  p('Escolaridad, comedor, bazar, copias, tardanzas y faltas, adelantos y otros: los pone RR.HH. con su monto (a mano, aplicándolo a un grupo o importando el Excel del mes).'),
  h2('9.9 Renta de 5ta categoría'),
  p('De **marzo a diciembre** se calcula **igual que el Excel de RR.HH. («Calculo 5ta.xlsx»)**: un solo cálculo del año, y el **mismo monto cada mes**.'),
  ...pasos([
    '**Ingreso mensual** = sueldo + asignación familiar + bonificación por cargo.',
    '**Proyección del año** = lo cobrado de verdad en **enero y febrero** + ingreso mensual × meses de marzo a diciembre que trabaja (casi siempre 10) + **vacaciones truncas** (solo contratados: ingreso ÷ 12 × esos meses) + **gratificación de julio y de diciembre** con su 9% (proporcionales a los meses del semestre) + otros ingresos del año (bonos).',
    'Se restan **7 UIT** (con la UIT de S/ 5,500: S/ 38,500). Si no pasa de ahí, la retención es 0.',
    'Lo que pasa se grava por tramos: **8% hasta 5 UIT, 14% hasta 20 UIT, 17% hasta 35 UIT, 20% hasta 45 UIT y 30% más allá**. Eso es el impuesto del año.',
    '**Retención del mes** = (impuesto del año − lo ya retenido en enero y febrero) ÷ meses de marzo a diciembre.',
  ]),
  ...tabla(['Ejemplo: Gatica Quispe (contratado desde marzo)', 'Monto'], [
    ['Ingreso mensual × 10 meses (3,300.80 × 10)', 'S/ 33,008.00'],
    ['Vacaciones truncas (3,300.80 ÷ 12 × 10)', 'S/ 2,750.67'],
    ['Gratificación de julio (4/6) + 9%', 'S/ 2,398.58'],
    ['Gratificación de diciembre (6/6) + 9%', 'S/ 3,597.87'],
    ['**Proyección del año**', '**S/ 41,755.12**'],
    ['Menos 7 UIT (38,500) → al 8%', 'S/ 260.41'],
    ['**Retención cada mes, de marzo a diciembre (÷ 10)**', '**S/ 26.04**'],
  ], [6700, 3000]),
  p('Quien entra a mitad de año reparte entre **sus** meses: si entra en junio, entre 7. En **enero y febrero** todavía no se sabe cuánto va a ganar en el año, así que esos dos meses se retienen con la proyección de SUNAT sobre el sueldo de ese mes.'),
  h3('Enero y febrero de 2026'),
  p('El colegio empieza a usar el sistema a fin de 2026, así que enero y febrero de este año no tienen planilla en el sistema. Lo cobrado y retenido en esos meses se carga una sola vez desde el Excel «Calculo 5ta» en **Ajustes del sistema** (sección 14.5). Si después se arma la planilla real de enero o febrero, manda la planilla y no se cuenta dos veces. Desde 2027, esos meses ya estarán en el sistema.'),
  h2('9.10 Ejemplo completo'),
  ...tabla(['Huacasi Yapo — octubre 2026 (AFP Integra flujo, con hijos)', 'Monto'], [
    ['Remuneración básica', 'S/ 3,381.00'],
    ['Bonificación por cargo', 'S/ 850.00'],
    ['Asignación familiar', 'S/ 113.00'],
    ['**Total ingresos (base afecta)**', '**S/ 4,344.00**'],
    ['SPP fondo (10%)', '− S/ 434.40'],
    ['SPP prima de seguro (1.37%)', '− S/ 59.51'],
    ['I.R. 5ta categoría', '− S/ 131.61'],
    ['Diezmo (10%)', '− S/ 434.40'],
    ['**Neto a pagar**', '**S/ 3,284.08**'],
    ['EsSalud 9% (lo paga el colegio)', 'S/ 390.96'],
  ], [6700, 3000]),
  nota('Huacasi está en AFP Integra con comisión **mixta**, por eso no tiene línea de comisión.'),
];

// ── 10 ──────────────────────────────────────────────────────────────
const cap10 = [
  h1('10. Emisión de Boletas'),
  quienVe('Administrador y RR.HH.'),
  p('Aquí se revisa y se **emite** la boleta de cada trabajador a partir de su planilla del mes. Emitir genera el PDF, lo guarda en su expediente y **le avisa al trabajador** por el sistema y por correo para que la revise y la firme.'),
  ...img('14-emision.png', 'Emisión de Boletas de octubre 2026.'),
  ...bullets([
    'Arriba eliges el **mes y año**. Los chips filtran: **Con planilla**, **Les falta** (sin planilla ese mes) y **Todos**. **Filtros** permite filtrar por sede, área, cargo, tipo de contrato y por **planilla**.',
    'La columna **Planilla** dice en qué planilla está cada trabajador ese mes.',
    'La columna **Estado** dice si su boleta ya está **Emitida** o **No emitida**.',
    '**Editar** abre la vista previa de su boleta. Si ya está emitida aparece **Bloqueado**: para corregirla hay que escribir la contraseña, y al volver a emitir se reemplaza la boleta.',
  ]),
  h2('10.1 La vista previa'),
  ...img('15-emision-vista-previa.png', 'Vista previa de la boleta: los datos del trabajador y el detalle financiero.'),
  ...bullets([
    'Arriba, los datos del trabajador que salen en la boleta (pensión, AFP, CUSPP, cargo, área, banco y cuenta). Si algo está mal, se corrige en su ficha.',
    'Abajo, **Ingresos, Descuentos y Aportaciones** con todas las filas de la boleta. Los campos grises (asignación, ONP/AFP, EsSalud, **I.R. 5ta**) los calcula el sistema; el I.R. 5ta muestra el monto calculado de esa persona y se recalcula si cambias el sueldo o la bonificación.',
    'Los demás campos se pueden escribir (comedor, bazar, escolaridad, adelantos…).',
    '**Guardar Borrador** guarda los cambios en su planilla sin emitir. **Emitir Boleta Oficial** guarda y emite.',
  ]),
  h2('10.2 Emitir masivamente'),
  p('**Emitir masivamente** emite de una vez las boletas del mes de **todo el personal con planilla** ese mes. El sistema pregunta antes de hacerlo y al final muestra qué pasó con cada uno.'),
  h2('10.3 Boletas de años anteriores'),
  p('Las boletas de planillas de años anteriores se emiten igual, pero quedan como **firmadas en papel**: no se avisa al trabajador ni se le pide firmar.'),
  h2('10.4 La boleta impresa'),
  p('La boleta sale **igual que la boleta física del colegio**, en dos copias (trabajador y empleador). Lleva:'),
  ...bullets([
    'Arriba, entre comillas, el **nombre oficial del año** («Año de la Esperanza y el Fortalecimiento de la Democracia»), que se configura en Ajustes del sistema.',
    'El **número de boleta** correlativo del año (BOL-2026-0002), el periodo y la fecha.',
    'Los **datos del trabajador**: cargo, área, sede, fecha de ingreso, días trabajados, días de vacaciones, sistema de pensión, CUSPP, banco y cuenta.',
    '**Todas las filas** de ingresos, descuentos, aportaciones y adelantos, **aunque estén en cero** (con «-»). Los conceptos que no son del modelo se agregan al final de su columna.',
    'Los porcentajes del nombre (ONP 13%, ESSALUD 9%) son los del año de la boleta.',
    'El **neto a pagar**, el lugar y fecha (Juliaca) y las firmas del empleador y del trabajador. Si el trabajador registró su firma dibujada, sale estampada al firmar.',
  ]),
  ...img('boleta-pdf-1.png', 'La boleta impresa de Huacasi Yapo, octubre 2026.'),
];

// ── 11 ──────────────────────────────────────────────────────────────
const cap11 = [
  h1('11. Documentos del personal'),
  quienVe('Administrador y RR.HH.'),
  p('Se parte de la **persona**: cada trabajador con si tiene hoja de vida, qué contrato tiene, cuántos documentos y cómo van sus boletas. Los chips llevan directo a lo que RR.HH. suele buscar: **sin hoja de vida**, **con boletas por firmar**, **contrato por vencer** y **dados de baja**.'),
  ...img('16-documentos.png', 'Documentos del personal.'),
  h2('11.1 El expediente de un trabajador'),
  p('**Ver expediente** abre todo lo que hay a su nombre, ordenado como el archivador físico:'),
  ...bullets([
    '**Sus documentos**: lo que trae el trabajador (hoja de vida, foto, DNI, certificados). Los sube él desde Mis Documentos o RR.HH. desde aquí.',
    '**Contratos**: del más reciente al más antiguo, con su contrato firmado adjunto (**Adjuntar contrato firmado**).',
    '**Su firma**: la firma dibujada que se estampa en sus boletas. RR.HH. puede dibujarla aquí si el trabajador no lo hizo.',
    '**Boletas**: mes a mes, con si la revisó, la descargó y la firmó.',
    '**Otros documentos**: CTS, comprobantes y todo lo que no va ligado a un contrato.',
  ]),
  ...img('17-expediente.png', 'El expediente de un trabajador.'),
  h2('11.2 Subir boletas y contratos anteriores'),
  p('Para cargar los PDF de **antes del sistema**, cada uno al expediente de su trabajador. Se eligen todos de una vez (hasta 1000 por tanda, PDF, Word o imagen de hasta 5 MB cada uno). El sistema lee cada PDF por dentro para saber de quién es (su DNI) y de qué mes; si no lo encuentra, mira el nombre del archivo, por ejemplo **42558107 2024-03.pdf**. Antes de guardar muestra qué entendió de cada uno para que lo confirmes o corrijas.'),
  ...img('18-subir-anteriores.png', 'Subir boletas y contratos anteriores.'),
  nota('¿Boletas de alguien que ya no trabaja aquí? Primero regístralo con Importar empleados, con «Cesado» en Estado y su fecha de cese.'),
  h2('11.3 Historial de boletas'),
  p('Elige un trabajador y aparecen todas sus boletas emitidas, con aviso, revisado, descargado y firmado. Sirve para atender reclamos o volver a descargar una boleta de otro mes.'),
  ...img('19-historial.png', 'Historial de boletas.'),
];

// ── 12 ──────────────────────────────────────────────────────────────
const cap12 = [
  h1('12. Vacaciones'),
  quienVe('Administrador y RR.HH.'),
  p('Las solicitudes de todo el personal. Los chips filtran por pendientes, aprobadas y rechazadas.'),
  ...img('20-vacaciones.png', 'Vacaciones.'),
  ...pasos([
    'Abre la solicitud pendiente.',
    'Revisa las fechas y los días.',
    '**Apruébala** o **recházala** explicando el motivo.',
  ]),
  ...bullets([
    'Las fechas no se cambian al resolver: si están mal, se rechaza explicando por qué y el trabajador vuelve a pedirlas.',
    '**Registrar solicitud**: para quien la trajo en papel, eligiendo al trabajador.',
    'Aprobar vacaciones **no cambia lo que se paga**: son remuneradas. En su boleta esos días salen como Días de vacaciones.',
    'Solo el contrato a plazo indeterminado da derecho a vacaciones; si eliges a otro, la pantalla te avisa.',
  ]),
];

// ── 13 ──────────────────────────────────────────────────────────────
const cap13 = [
  h1('13. Configuración'),
  quienVe('Administrador y RR.HH.'),
  p('Son las listas de las que se sirve el resto del sistema: cambiar algo aquí se nota en todos los formularios que lo usan.'),
  h2('13.1 Áreas y Cargos'),
  p('Las áreas del colegio (Dirección, Plana Docente — Primaria, Mantenimiento y Limpieza…) y los cargos del personal. Cada cargo está acotado a las áreas donde tiene sentido; uno como practicante o voluntario puede valer en cualquiera. En **Áreas** puedes filtrar por cargo, y en **Cargos** por área.'),
  ...img('22-cargos.png', 'Cargos, con su filtro por área.'),
  h2('13.2 Tipos de Contrato'),
  p('Contratado, Contrato/Parcial, Plazo indeterminado y Prácticas. Cada tipo dice si lleva fecha de término y si da derecho a vacaciones.'),
  h2('13.3 Sedes'),
  p('Los locales del colegio: **CATA Central, CATA Inicial, CATA Jerusalén y CATA Oasis**.'),
  h2('13.4 Periodos'),
  p('El tramo de meses de una campaña de planillas, por ejemplo **Año Escolar 2026** (enero a diciembre). Toda planilla pertenece a un periodo.'),
  h2('13.5 Conceptos de Pago'),
  p('El catálogo de todo lo que puede salir en una boleta: **bonificaciones** (suman), **descuentos** (restan), **aportaciones** (las paga el colegio, no afectan el neto) y **adelantos** (restan, en su propio bloque).'),
  ...img('26-conceptos.png', 'Conceptos de Pago.'),
  ...bullets([
    '**Nuevo Concepto**: nombre, tipo, si se aplica **automáticamente a todos** al crear la planilla (con su cálculo: monto fijo o porcentaje del sueldo) y una descripción.',
    'Los **conceptos de ley** (Asignación Familiar, ONP, AFP, EsSalud, Bonificación Extraordinaria) dicen **«Según ley»** y **«Lo calcula el sistema»**: su monto sale de Ajustes del sistema → Montos de ley, no del catálogo. No se les puede poner monto ni cambiar el nombre, porque el sistema los reconoce por su nombre.',
    '**Aplicar a un grupo de empleados** (ícono de personas): pone un concepto a varios en la planilla que ya tengan de un mes.',
  ]),
];

// ── 14 ──────────────────────────────────────────────────────────────
const cap14 = [
  h1('14. Solo Administrador'),
  quienVe('Administrador'),
  h2('14.1 Usuarios'),
  p('Las cuentas de acceso: su rol, a qué trabajador pertenecen, si están activas y si firmaron los términos. Desde aquí se **cambia el rol** de una cuenta (por ejemplo, de Empleado a RR.HH.), se **restablece la contraseña** (candado: vuelve a ser su DNI y tendrá que cambiarla al entrar), se edita el correo o se desactiva una cuenta.'),
  ...img('27-usuarios.png', 'Usuarios.'),
  h2('14.2 Roles'),
  p('Los tres roles del sistema (Admin, RR.HH., Empleado) con su descripción. No se crean roles nuevos.'),
  h2('14.3 Módulos y Módulos Padre'),
  p('El menú mismo: qué pantallas existen, en qué grupo, en qué orden y qué roles las ven. Cambiarlo cambia el menú de todo el colegio: úsalo con cuidado.'),
  h2('14.4 Auditoría'),
  p('El rastro de **quién cambió qué y cuándo**: sueldos, cuentas, conceptos, planillas, importaciones, montos de ley. Muestra el antes y el después. Solo se lee: nadie puede editarla.'),
  ...img('31-auditoria.png', 'Auditoría.'),
  h2('14.5 Ajustes del sistema'),
  p('Lo que el Administrador enciende, apaga o actualiza sin tocar código. Cada cambio queda en la Auditoría.'),
  ...img('32-ajustes.png', 'Ajustes del sistema.', 520),
  h3('Planillas de años anteriores'),
  p('Activado, RR.HH. puede crear planillas y emitir boletas de cualquier mes de años anteriores (sección 8.2). Apagado, solo del año en curso en adelante; lo ya armado se queda como está.'),
  h3('Montos de ley por año'),
  p('Cada planilla se calcula con los montos de **su propio año**. Si un año no está cargado, se usan los del último año anterior. Elige el año arriba; con **Agregar año** se crea uno nuevo copiando el anterior.'),
  ...tabla(['Dato', 'Para qué sirve', '2026'], [
    ['Nombre oficial del año', 'Sale arriba en cada boleta de ese año, entre comillas. Vacío = no se imprime.', 'Año de la Esperanza y el Fortalecimiento de la Democracia'],
    ['UIT', 'Las 7 UIT y los tramos de la Renta de 5ta.', 'S/ 5,500'],
    ['Sueldo mínimo (RMV)', 'De aquí sale la asignación familiar, y EsSalud nunca va sobre menos.', 'S/ 1,130'],
    ['Asignación familiar', 'Porcentaje de la RMV. La pantalla muestra cuánto sale en soles.', '10% (S/ 113)'],
    ['ONP', 'Descuento al trabajador.', '13%'],
    ['EsSalud', 'Aporte del colegio y la bonificación extraordinaria de julio y diciembre.', '9%'],
    ['AFP: aporte al fondo, prima de seguro', 'Igual para todas las AFP.', '10% y 1.37%'],
    ['AFP: comisiones', 'Por flujo, según la AFP.', 'Habitat 1.47, Integra 1.55, Prima 1.60, Profuturo 1.69'],
  ], [2600, 4300, 2800]),
  importante('Al guardar, solo cambian las planillas que se generen o recalculen desde ahora. Las ya emitidas no se tocan.'),
  h3('Renta de 5ta: enero y febrero'),
  p('Para cargar lo cobrado y retenido en enero y febrero del año en curso cuando esos meses no se hicieron en el sistema. Se sube el Excel «Calculo 5ta» de RR.HH. (con las columnas MODULAR, PATERNO, Enero, Febrero, IRQ DESCONTADO ENERO e IRQ DESCONTADO FEBRERO) y presionas **Cargar enero y febrero**. El sistema muestra cuántos leyó, cuántos tienen datos, cuántas planillas recalculó y quiénes no están registrados. Se puede volver a subir: reemplaza lo anterior.'),
];

// ── 15 ──────────────────────────────────────────────────────────────
const cap15 = [
  h1('15. El trabajo de cada mes, paso a paso'),
  quienVe('RR.HH.'),
  h2('15.1 Una vez al año (en enero)'),
  ...pasos([
    'El Administrador revisa **Ajustes del sistema → Montos de ley**: agrega el año nuevo, escribe el **nombre oficial del año** y actualiza lo que haya cambiado (casi siempre la UIT, a veces la RMV).',
    'Crea el **periodo** del año escolar en Configuración → Periodos.',
    'Revisa los **contratos** que vencieron y renueva los que sigan.',
  ]),
  h2('15.2 Cada mes'),
  ...pasos([
    '**Revisa las fichas**: altas nuevas, bajas (con su fecha de cese), cambios de sueldo, bonificación por cargo, hijos, AFP o diezmo. Para muchos cambios usa **Importar empleados**.',
    '**Crea la planilla del mes** en Planillas → Nueva planilla, con todo el personal activo. Los conceptos de ley quedan calculados.',
    '**Carga los conceptos del mes**: descarga el modelo en Importar conceptos, llena escolaridad, comedor, copias, tardanzas, adelantos, movilidad y otros, y súbelo. Lo que sea de una sola persona, agrégalo en su planilla.',
    '**Revisa**: el reporte de la planilla y algunas vistas previas en Emisión de Boletas. Si corriges una ficha, usa **Recalcular sueldo**.',
    '**Emite las boletas**: de una en una o con **Emitir masivamente**. Cada trabajador recibe el aviso.',
    '**Sigue las firmas** en el Panel de Control y en Documentos (chip «Con boletas por firmar»).',
    '**Cierra la planilla** cuando ya se pagó.',
  ]),
  h2('15.3 Meses especiales'),
  ...bullets([
    '**Julio y diciembre**: la gratificación y su bonificación extraordinaria se crean solas en la planilla de ese mes.',
    '**Fin de contrato de un contratado**: agrega sus **Vacaciones Truncas** en su planilla del último mes.',
    '**CTS**: si se paga junto con la boleta, usa el concepto Compensación por Tiempo de Servicios con **Aplicar concepto** en la planilla.',
  ]),
];

// ── 16 ──────────────────────────────────────────────────────────────
const cap16 = [
  h1('16. Puesta en marcha (noviembre 2026)'),
  quienVe('Administrador y RR.HH.'),
  h2('16.1 Instalar el sistema vacío (lo hace quien administra el servidor)'),
  ...pasos([
    'Subir el código de la rama al servidor y levantarlo. Al arrancar se crean solas las tablas, los **tipos de contrato**, los **montos de ley** 2024–2026 y el módulo de **Ajustes del sistema**.',
    'Cargar el catálogo base: **php artisan semilla:importar**. Trae roles, menú, las 15 áreas, los cargos, las 4 sedes y los 28 conceptos de pago.',
    'Crear la cuenta del Administrador: **php artisan db:seed --class=CuentasInicialesSeeder** (toma el correo y la clave del archivo .env).',
    'Entrar como Administrador, aceptar los términos, poner su contraseña y crear el **periodo** «Año Escolar 2026» en Configuración → Periodos.',
  ]),
  h2('16.2 Cargar los datos'),
  p('En la carpeta **«Para importar (noviembre)»** están los archivos listos. Súbelos en este orden:'),
  ...tabla(['#', 'Archivo', 'Dónde se sube', 'Qué hace'], [
    ['1', '1 - Empleados (alta completa de los 94).xlsx', 'Empleados → Importar empleados', 'Da de alta a los 94 trabajadores del PLAME con 27 columnas llenas: datos personales, correo, área, cargo, sede, ingreso, contrato, sueldo, bonificación por cargo, pensión, banco, hijos, diezmo y estudios. A cada uno se le crea su cuenta con su DNI como contraseña.'],
    ['2', '2 - Calculo 5ta (enero y febrero, se sube en Ajustes).xlsx', 'Ajustes del sistema → Renta de 5ta', 'Carga lo cobrado y retenido en enero y febrero (21 trabajadores).'],
    ['3', '—', 'Planillas → Nueva planilla', 'Crea la planilla del mes con todo el personal activo (para la prueba: Septiembre 2026).'],
    ['4', '3 - Conceptos de septiembre 2026 (completo, del PLAME).xlsx', 'Planillas → Importar conceptos (Septiembre 2026)', 'Para la prueba con setiembre: todos los descuentos y pagos variables del PLAME de setiembre (escolaridad, comedor, copias, tardanzas, adelantos, movilidad, curso IA, corbatas y polos): 256 montos.'],
  ], [400, 3300, 2600, 3400]),
  p('Para los meses reales en adelante se usa «Modelo - Conceptos de cada mes (escolaridad llena, lo demás por llenar)»: la escolaridad viene llena porque se repite; lo demás se llena con los montos de ese mes. También sirve el modelo que descarga la propia pantalla de Importar conceptos.'),
  p('Con la planilla de setiembre creada y los cuatro pasos hechos, el total neto debe salir S/ 178,801.84 contra S/ 178,790.88 del PLAME (los S/ 10.96 son la Renta de 5ta de Lanza Umiña). Por ejemplo, Gatica Quispe: neto S/ 2,509.22.'),
  p('El archivo 1 viene **lleno en todas sus celdas**: a quienes están en ONP se les pone «No aplica» en AFP, comisión y CUSPP, y a quienes tienen contrato indeterminado «Indeterminado» en Fecha de cese. El CUSPP de Lanza Umiña va como en el PLAME, con Ñ: 540581FLUZÑ0. No lleva CCI, especialidad, institución ni contacto de emergencia porque RR.HH. no tiene esos datos: son opcionales y se completan después en la ficha.'),
  p('Esta secuencia se probó en una base de datos vacía: 94 altas sin errores, 21 trabajadores con enero y febrero cargados, 94 planillas de octubre generadas e iguales al PLAME en 93 de 94 trabajadores (la diferencia es la Renta de 5ta de Lanza Umiña: el Excel de 5ta le suma una asignación familiar que el PLAME no le paga).'),
];

// ── 17 ──────────────────────────────────────────────────────────────
const pregunta = (q, a) => [new Paragraph({ spacing: { before: 200, after: 60 }, keepNext: true,
  children: [new TextRun({ text: q, bold: true, size: 22, font: FUENTE, color: AZUL_OSCURO })] }), p(a)];
const cap17 = [
  h1('17. Preguntas frecuentes'),
  ...pregunta('¿Por qué no puedo descargar mi boleta?', 'Porque todavía no la firmaste. Ábrela en Mis Boletas, revísala y fírmala con tu contraseña; después aparece Descargar.'),
  ...pregunta('Un monto de mi boleta está mal, ¿qué hago?', 'No la firmes. Avisa a RR.HH.: la corrige (la boleta emitida se desbloquea con su contraseña) y la vuelve a emitir.'),
  ...pregunta('¿Por qué la I.R. 5ta de alguien sale 0?', 'Porque lo que va a ganar en el año no pasa de 7 UIT (S/ 38,500 en 2026). Si le pagan más de lo que dice su ficha, revisa su sueldo, su bonificación por cargo y si tiene cargados enero y febrero.'),
  ...pregunta('Cambié el sueldo de alguien y su planilla sigue igual.', 'La planilla es una foto del momento en que se creó. Entra a su planilla y presiona Recalcular sueldo.'),
  ...pregunta('¿Por qué a alguien no le sale la comisión de la AFP?', 'Porque está en comisión mixta: la AFP la cobra de su fondo, no en planilla. Se ve en su ficha, paso Planilla.'),
  ...pregunta('¿Por qué alguien con 65 años no paga prima de seguro?', 'La AFP no cobra prima desde que el afiliado cumple 65 años. El sistema lo hace solo desde el mes siguiente a su cumpleaños.'),
  ...pregunta('El Excel de conceptos dice que no hay planillas.', 'Primero crea las planillas de ese mes (Planillas → Nueva planilla). La importación de conceptos llena planillas que ya existen.'),
  ...pregunta('Borré una celda en el Excel de empleados, ¿se borró el dato?', 'No. Una celda vacía no cambia nada. Para quitar un concepto en el Excel de conceptos, escribe 0.'),
  ...pregunta('¿Puedo cambiar yo mismo mi correo o mi rol?', 'No. Los cambia el Administrador desde Usuarios.'),
  ...pregunta('¿Dónde subo las boletas de antes del sistema?', 'En Documentos → Subir boletas y contratos anteriores.'),
  ...pregunta('¿La contraseña provisional (el DNI) sigue sirviendo?', 'No. En cuanto pones tu contraseña propia, el DNI deja de servir.'),
  ...pregunta('No veo el Panel de Control ni Empleados.', 'Tu cuenta es de Empleado. Si deberías tener otro rol, pídeselo al Administrador.'),
];

// ── 18 ──────────────────────────────────────────────────────────────
const cap18 = [
  h1('18. Glosario'),
  ...tabla(['Palabra', 'Qué significa'], [
    ['AFP', 'Administradora de Fondos de Pensiones (Habitat, Integra, Prima, Profuturo): el sistema privado de pensiones.'],
    ['Asignación familiar', '10% de la RMV para quien tiene hijos menores de 18 (o hasta 24 si estudian).'],
    ['Base afecta', 'Básica + bonificación por cargo + asignación familiar + vacaciones truncas: sobre ella se calculan pensión, EsSalud y diezmo.'],
    ['Comisión flujo / mixta', 'Flujo: la comisión de la AFP se descuenta cada mes en planilla. Mixta: la AFP la cobra de su fondo.'],
    ['CTS', 'Compensación por Tiempo de Servicios.'],
    ['CUSPP', 'Código único del afiliado a una AFP (12 caracteres).'],
    ['EsSalud', 'Seguro de salud: 9% que aporta el colegio por cada trabajador.'],
    ['Expediente', 'Todo lo que hay a nombre de un trabajador: hoja de vida, contratos, firma, boletas y otros documentos.'],
    ['Gratificación', 'Sueldo extra de julio (Fiestas Patrias) y diciembre (Navidad), con su bonificación extraordinaria del 9%.'],
    ['ONP', 'Oficina de Normalización Previsional: el sistema nacional de pensiones (13%).'],
    ['Planilla', 'El grupo de trabajadores que se pagan juntos en un mes, y el cálculo del sueldo de cada uno.'],
    ['PLAME', 'La planilla electrónica que el colegio presenta a SUNAT; el modelo con el que se comprobaron los cálculos.'],
    ['Renta de 5ta', 'Impuesto a la renta del trabajo dependiente, retenido cada mes.'],
    ['RMV', 'Remuneración Mínima Vital: el sueldo mínimo (S/ 1,130 en 2026).'],
    ['UIT', 'Unidad Impositiva Tributaria (S/ 5,500 en 2026).'],
    ['Vacaciones truncas', 'Las vacaciones ganadas y no tomadas que se pagan al terminar un contrato que no es indeterminado.'],
  ], [2500, 7200]),
];

// ── Documento ────────────────────────────────────────────────────────
const doc = new Document({
  creator: 'Colegio Adventista Túpac Amaru',
  title: 'Manual de usuario — CATA-Recibo',
  features: { updateFields: true },
  numbering: { config: [{ reference: 'bullets', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•',
    alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 360, hanging: 260 } } } }] }] },
  styles: {
    default: { document: { run: { font: FUENTE, size: 22, color: GRIS_TEXTO } } },
    paragraphStyles: [
      { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true,
        run: { size: 32, bold: true, color: AZUL_OSCURO, font: FUENTE }, paragraph: { spacing: { before: 200, after: 220 }, outlineLevel: 0 } },
      { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true,
        run: { size: 26, bold: true, color: AZUL, font: FUENTE }, paragraph: { spacing: { before: 300, after: 140 }, outlineLevel: 1, keepNext: true } },
      { id: 'Heading3', name: 'Heading 3', basedOn: 'Normal', next: 'Normal', quickFormat: true,
        run: { size: 22, bold: true, color: GRIS_TEXTO, font: FUENTE, italics: true }, paragraph: { spacing: { before: 200, after: 90 }, outlineLevel: 2, keepNext: true } },
    ],
  },
  sections: [
    { properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 1080, bottom: 1080, left: 1260, right: 1260 } } },
      children: [...portada, ...indice] },
    {
      properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 1260, bottom: 1080, left: 1260, right: 1260 } } },
      headers: { default: new Header({ children: [new Paragraph({ alignment: AlignmentType.RIGHT,
        border: { bottom: { color: LINEA, space: 4, style: BorderStyle.SINGLE, size: 4 } },
        children: [new TextRun({ text: 'CATA-Recibo · Manual de usuario · Versión 2', size: 16, color: GRIS_SUAVE, font: FUENTE })] })] }) },
      footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [
        new TextRun({ text: 'Página ', size: 16, color: GRIS_SUAVE, font: FUENTE }),
        new TextRun({ children: [PageNumber.CURRENT], size: 16, color: GRIS_SUAVE, font: FUENTE }),
        new TextRun({ text: ' de ', size: 16, color: GRIS_SUAVE, font: FUENTE }),
        new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 16, color: GRIS_SUAVE, font: FUENTE })] })] }) },
      children: [...cap1, ...cap2, ...cap3, ...cap4, ...cap5, ...cap6, ...cap7, ...cap8, ...cap9, ...cap10,
        ...cap11, ...cap12, ...cap13, ...cap14, ...cap15, ...cap16, ...cap17, ...cap18],
    },
  ],
});

Packer.toBuffer(doc).then((buffer) => {
  fs.writeFileSync(SALIDA, buffer);
  console.log('listo:', SALIDA, `(${numFigura} figuras)`);
});
