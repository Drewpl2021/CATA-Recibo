// Plantilla: CATA-Recibo módulo por módulo (la presentación al Consejo
// Directivo, octubre de 2026). Para una nueva: copiar a la carpeta de trabajo,
// ajustar textos, cifras y la lista de módulos, y generar con
//
//   node presentacion.js
//
// en una carpeta que tenga cap/*.jpg (ver capturas.mjs), logo-cata.png y
// logo-blanco.png (de ../recursos) y apply_theme.js (de la skill pptx).
// Ver SKILL.md de presentacion-sistema para el proceso completo.
const pptxgen = require('pptxgenjs');
const React = require('react');
const ReactDOMServer = require('react-dom/server');
const sharp = require('sharp');
const fa = require('react-icons/fa6');
const { applyTheme } = require('./apply_theme.js');

const THEME = {
  name: 'CATA', headFontFace: 'Cambria', bodyFontFace: 'Calibri',
  colors: { dk1: '16213F', lt1: 'FFFFFF', dk2: '0E2650', lt2: 'EEF2F8', accent1: '1B4282', accent2: 'F4B41A', accent3: '3B72C4', accent4: '047857', accent5: 'B3271C', accent6: '6B7690', hlink: '1B4282', folHlink: '3B72C4' },
};
const H = { navy: '1B4282', hondo: '0E2650', oro: 'F4B41A', claro: '3B72C4', suave: 'EEF2F8', gris: '6B7690', borde: 'D9E0EC', hielo: 'CADCFC', barra: 'F1F3F7' };
const IMG = (n) => `cap/${n}.jpg`;
const RATIO = 1720 / 2880;

const pres = new pptxgen();
pres.layout = 'LAYOUT_WIDE';
pres.title = 'CATA-Recibo: el sistema de planillas y boletas del colegio';
pres.author = 'Área de TIC, Colegio Adventista Túpac Amaru';
pres.company = 'Colegio Adventista Túpac Amaru';
pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };
const C = pres.SchemeColor;

const cache = {};
async function icono(nombre, color) {
  const k = nombre + color;
  if (!cache[k]) {
    const svg = ReactDOMServer.renderToStaticMarkup(React.createElement(fa[nombre], { color: '#' + color, size: 256 }));
    cache[k] = 'image/png;base64,' + (await sharp(Buffer.from(svg)).resize(256, 256).png().toBuffer()).toString('base64');
  }
  return cache[k];
}
async function circulo(s, nombre, x, y, d, fondo, color, obj) {
  s.addShape(pres.shapes.OVAL, { x, y, w: d, h: d, fill: { color: fondo }, line: { color: fondo }, objectName: obj + ' círculo' });
  const pad = d * 0.27;
  s.addImage({ data: await icono(nombre, color), x: x + pad, y: y + pad, w: d - 2 * pad, h: d - 2 * pad, objectName: obj + ' ícono' });
}
const sombra = () => ({ type: 'outer', color: '0E2650', opacity: 0.22, blur: 14, offset: 5, angle: 90 });

// Una ventana de navegador con la captura dentro: así se lee como el sistema real.
function marco(s, img, x, y, w, ruta, obj) {
  const bar = 0.3, ih = w * RATIO;
  s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w, h: ih + bar, rectRadius: 0.07, fill: { color: H.barra }, line: { color: H.borde, width: 0.75 }, shadow: sombra(), objectName: obj + ' ventana' });
  ['FF5F57', 'FEBC2E', '28C840'].forEach((c, i) => s.addShape(pres.shapes.OVAL, { x: x + 0.14 + i * 0.17, y: y + 0.1, w: 0.1, h: 0.1, fill: { color: c }, line: { color: c }, objectName: obj + ' punto ' + (i + 1) }));
  s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: x + 0.75, y: y + 0.06, w: Math.min(3.6, w - 1.0), h: 0.18, rectRadius: 0.09, fill: { color: 'FFFFFF' }, line: { color: H.borde, width: 0.5 }, objectName: obj + ' dirección' });
  s.addText('cata-recibo' + ruta, { x: x + 0.85, y: y + 0.06, w: Math.min(3.4, w - 1.2), h: 0.18, fontSize: 8, color: H.gris, margin: 0, valign: 'middle', isTextBox: true, objectName: obj + ' url' });
  s.addImage({ path: img, x, y: y + bar, w, h: ih, objectName: obj + ' captura' });
  return y + bar + ih;
}
function telefono(s, img, x, y, h, obj) {
  const w = h * 1170 / 2532 + 0.2;
  s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w, h: h + 0.2, rectRadius: 0.3, fill: { color: '16213F' }, line: { color: '16213F' }, shadow: sombra(), objectName: obj + ' teléfono' });
  s.addImage({ path: img, x: x + 0.1, y: y + 0.1, w: w - 0.2, h, objectName: obj + ' pantalla' });
}

// ── Layouts ──────────────────────────────────────────────────────────
pres.defineSlideMaster({ title: 'OSCURO', background: { color: H.hondo }, objects: [] });
const pie = [{ image: { path: 'logo-cata.png', x: 11.55, y: 6.98, w: 1.2, h: 1.2 * 181 / 795 } }];
const num = { x: 0.6, y: 6.98, w: 0.6, h: 0.28, fontSize: 10, color: H.gris, fontFace: 'Calibri' };
pres.defineSlideMaster({
  title: 'CONTENIDO', background: { color: 'FFFFFF' }, margin: [0.5, 0.6, 0.6, 0.6], slideNumber: num,
  objects: [...pie, { placeholder: { options: { name: 'title', type: 'title', x: 0.6, y: 0.4, w: 11.4, h: 0.85, fontSize: 30, bold: true, color: C.text2, fontFace: 'Cambria', valign: 'middle', align: 'left', margin: 0 }, text: '' } }],
});
pres.defineSlideMaster({
  title: 'MODULO', background: { color: 'FFFFFF' }, margin: [0.5, 0.6, 0.6, 0.6], slideNumber: num,
  objects: [...pie, { placeholder: { options: { name: 'title', type: 'title', x: 0.6, y: 1.45, w: 4.3, h: 1.35, fontSize: 26, bold: true, color: C.text2, fontFace: 'Cambria', valign: 'top', align: 'left', margin: 0 }, text: '' } }],
});

/**
 * Un módulo: a la izquierda su nombre, el mensaje y lo que hace; a la
 * derecha, la pantalla real (una o dos, la segunda en cascada).
 */
async function modulo(seccion, m) {
  const s = pres.addSlide({ masterName: 'MODULO', sectionTitle: seccion });
  await circulo(s, m.icono, 0.6, 0.55, 0.62, H.navy, 'FFFFFF', m.nombre);
  s.addText(m.nombre, { x: 1.35, y: 0.55, w: 3.6, h: 0.62, fontSize: 16, bold: true, color: H.claro, margin: 0, valign: 'middle', isTextBox: true });
  s.addText(m.titulo, { placeholder: 'title' });
  for (let i = 0; i < m.puntos.length; i++) {
    const y = 3.0 + i * 0.86;
    await circulo(s, 'FaCheck', 0.6, y + 0.03, 0.32, H.oro, H.hondo, m.nombre + ' punto ' + (i + 1));
    s.addText(m.puntos[i], { x: 1.08, y, w: 3.85, h: 0.78, fontSize: 14, color: C.text1, margin: 0, valign: 'top', isTextBox: true });
  }
  if (m.capturas.length === 1) {
    const fin = marco(s, IMG(m.capturas[0][0]), 5.3, 0.75, 7.45, m.capturas[0][1], m.nombre);
    if (m.difuminado) s.addText('Los nombres, DNI y montos de cada persona aparecen difuminados en esta presentación.', { x: 5.3, y: fin + 0.12, w: 7.45, h: 0.3, fontSize: 10, italic: true, color: H.gris, margin: 0, isTextBox: true });
  } else {
    marco(s, IMG(m.capturas[0][0]), 5.15, 0.55, 6.2, m.capturas[0][1], m.nombre + ' 1');
    const fin = marco(s, IMG(m.capturas[1][0]), 6.55, 2.45, 6.2, m.capturas[1][1], m.nombre + ' 2');
    if (m.difuminado) s.addText('Datos de personas difuminados.', { x: 6.55, y: fin + 0.1, w: 6.2, h: 0.28, fontSize: 10, italic: true, color: H.gris, align: 'right', margin: 0, isTextBox: true });
  }
  s.addNotes(m.notas);
}

/** Separador de sección: oscuro, con su número y sus módulos. */
async function separador(seccion, numero, titulo, texto, iconos) {
  pres.addSection({ title: seccion });
  const s = pres.addSlide({ masterName: 'OSCURO', sectionTitle: seccion });
  s.addText(numero, { x: 0.8, y: 1.3, w: 3, h: 1.4, fontFace: 'Cambria', fontSize: 96, bold: true, color: H.oro, margin: 0, isTextBox: true });
  s.addText(titulo, { x: 0.8, y: 2.85, w: 11, h: 1.0, fontFace: 'Cambria', fontSize: 44, bold: true, color: 'FFFFFF', margin: 0, isTextBox: true });
  s.addText(texto, { x: 0.8, y: 3.9, w: 9.5, h: 0.9, fontSize: 20, color: H.hielo, margin: 0, isTextBox: true });
  for (let i = 0; i < iconos.length; i++) {
    const x = 0.8 + i * 2.35;
    await circulo(s, iconos[i][0], x, 5.25, 0.7, '233A6B', H.oro, titulo + ' ' + (i + 1));
    s.addText(iconos[i][1], { x: x + 0.85, y: 5.25, w: 1.45, h: 0.7, fontSize: 14, bold: true, color: 'FFFFFF', margin: 0, valign: 'middle', isTextBox: true });
  }
  s.addImage({ path: 'logo-blanco.png', x: 10.6, y: 6.6, w: 1.9, h: 1.9 * 181 / 795, objectName: 'Logo' });
  return s;
}

(async () => {
  // ════ Apertura ════
  pres.addSection({ title: 'Apertura' });
  let s = pres.addSlide({ masterName: 'OSCURO', sectionTitle: 'Apertura' });
  s.addImage({ path: 'logo-blanco.png', x: 0.8, y: 0.7, w: 2.4, h: 2.4 * 181 / 795, objectName: 'Logo' });
  s.addText('CATA-Recibo', { x: 0.8, y: 2.0, w: 6.2, h: 1.2, fontFace: 'Cambria', fontSize: 58, bold: true, color: 'FFFFFF', margin: 0, isTextBox: true });
  s.addText('El sistema de planillas, boletas y documentos laborales del colegio', { x: 0.8, y: 3.25, w: 5.9, h: 1.0, fontSize: 22, color: H.hielo, margin: 0, isTextBox: true });
  s.addText([
    { text: 'Presentación al Consejo Directivo', options: { bold: true, color: H.oro, breakLine: true } },
    { text: 'Colegio Adventista Túpac Amaru, Juliaca', options: { color: 'FFFFFF', breakLine: true } },
    { text: 'Área de TIC, octubre de 2026', options: { color: H.hielo } },
  ], { x: 0.8, y: 5.3, w: 6, h: 1.2, fontSize: 16, margin: 0, paraSpaceAfter: 4, isTextBox: true });
  marco(s, IMG('panel'), 7.0, 1.45, 5.75, '/inicio/dashboard', 'Portada');
  s.addNotes('Buenos días. Les voy a mostrar CATA-Recibo, el sistema que hicimos para el colegio: cómo se arma la planilla, cómo llegan las boletas a cada trabajador y todo lo que maneja, módulo por módulo, con las pantallas reales.');

  // Qué es, para quién
  s = pres.addSlide({ masterName: 'CONTENIDO', sectionTitle: 'Apertura' });
  s.addText('Un solo sistema para todo lo laboral del colegio', { placeholder: 'title' });
  const quienes = [
    ['FaUsersGear', 'Recursos Humanos', 'Lleva al personal, arma la planilla, emite las boletas y calcula quinta, CTS y gratificaciones.'],
    ['FaBuildingColumns', 'Administración', 'Firma las boletas con el certificado del colegio, configura el sistema y revisa la auditoría.'],
    ['FaMobileScreen', 'Cada trabajador', 'Recibe sus boletas y documentos, los firma desde el celular y pide sus vacaciones.'],
  ];
  for (let i = 0; i < 3; i++) {
    const x = 0.6 + i * 4.1;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y: 1.65, w: 3.85, h: 4.7, rectRadius: 0.12, fill: { color: i === 1 ? H.navy : H.suave }, line: { color: i === 1 ? H.navy : H.suave }, objectName: 'Usuario ' + (i + 1) });
    await circulo(s, quienes[i][0], x + 0.35, 2.0, 1.05, i === 1 ? H.oro : H.navy, i === 1 ? H.hondo : 'FFFFFF', 'Usuario ' + (i + 1));
    s.addText(quienes[i][1], { x: x + 0.35, y: 3.3, w: 3.2, h: 0.6, fontFace: 'Cambria', fontSize: 22, bold: true, color: i === 1 ? 'FFFFFF' : C.text2, margin: 0, isTextBox: true });
    s.addText(quienes[i][2], { x: x + 0.35, y: 4.0, w: 3.2, h: 2.0, fontSize: 16, color: i === 1 ? 'FFFFFF' : C.text1, margin: 0, valign: 'top', isTextBox: true });
  }
  s.addNotes('El sistema tiene tres tipos de usuario. Recursos Humanos, que hace el trabajo de cada mes. Administración, que firma y configura. Y cada trabajador, que recibe sus boletas en el celular. Cada uno ve solo lo que le corresponde.');

  // Mapa del sistema
  s = pres.addSlide({ masterName: 'CONTENIDO', sectionTitle: 'Apertura' });
  s.addText('Todo lo que maneja el sistema, en cinco grupos', { placeholder: 'title' });
  const mapa = [
    ['FaUsers', 'Personal', ['Empleados', 'Contratos', 'Expedientes', 'Vacaciones']],
    ['FaFileInvoiceDollar', 'Planilla y boletas', ['Panel de Control', 'Planillas', 'Importar PLAME', 'Emisión de boletas', 'Firma digital']],
    ['FaScaleBalanced', 'Beneficios de ley', ['Renta de 5ta', 'CTS', 'Gratificaciones', 'Conceptos de pago']],
    ['FaGlobe', 'Portal Web', ['Inicio y noticias', 'Matrícula', 'Proyectos', 'Nosotros', 'Propuesta']],
    ['FaGears', 'Configuración', ['Áreas, cargos, sedes', 'Usuarios y roles', 'Ajustes de ley', 'Auditoría']],
  ];
  for (let i = 0; i < 5; i++) {
    const x = 0.6 + i * 2.47;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y: 1.6, w: 2.3, h: 4.85, rectRadius: 0.12, fill: { color: H.suave }, line: { color: H.suave }, objectName: 'Grupo ' + (i + 1) });
    await circulo(s, mapa[i][0], x + 0.25, 1.85, 0.8, H.navy, 'FFFFFF', 'Grupo ' + (i + 1));
    s.addText(mapa[i][1], { x: x + 0.25, y: 2.8, w: 1.95, h: 0.75, fontFace: 'Cambria', fontSize: 18, bold: true, color: C.text2, margin: 0, valign: 'top', isTextBox: true });
    s.addText(mapa[i][2].map((t, k) => ({ text: t, options: { bullet: true, breakLine: k < mapa[i][2].length - 1 } })), {
      x: x + 0.25, y: 3.6, w: 1.95, h: 2.7, fontSize: 14, color: C.text1, margin: 0, valign: 'top', paraSpaceAfter: 6, isTextBox: true });
  }
  s.addNotes('Este es el mapa completo: veintiséis módulos en cinco grupos. Personal, planilla y boletas, beneficios de ley, el portal web del colegio y la configuración. Los vamos a recorrer en ese orden.');

  // Cifras
  s = pres.addSlide({ masterName: 'CONTENIDO', sectionTitle: 'Apertura' });
  s.addText('Ya trabaja con la planilla real del colegio', { placeholder: 'title' });
  const cifras = [['84', 'trabajadores activos', 'de 94 registrados'], ['S/ 192,183.75', 'planilla de octubre', 'neto a pagar, al céntimo'], ['94', 'boletas emitidas', 'del mes'], ['4', 'sedes', 'y 15 áreas']];
  for (let i = 0; i < 4; i++) {
    const x = 0.6 + (i % 2) * 6.2, y = 1.65 + Math.floor(i / 2) * 2.45;
    const d = i === 1;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w: 5.9, h: 2.15, rectRadius: 0.12, fill: { color: d ? H.navy : H.suave }, line: { color: d ? H.navy : H.suave }, objectName: 'Cifra ' + (i + 1) });
    s.addText(cifras[i][0], { x: x + 0.4, y: y + 0.25, w: 5.2, h: 1.0, fontFace: 'Cambria', fontSize: d ? 44 : 54, bold: true, color: d ? H.oro : H.navy, margin: 0, valign: 'middle', isTextBox: true });
    s.addText([{ text: cifras[i][1], options: { bold: true, fontSize: 18, breakLine: true } }, { text: cifras[i][2], options: { fontSize: 14 } }],
      { x: x + 0.4, y: y + 1.25, w: 5.2, h: 0.75, color: d ? 'FFFFFF' : C.text1, margin: 0, isTextBox: true });
  }
  s.addNotes('No es una maqueta: tiene la planilla real. 84 trabajadores activos, la planilla de octubre de 192 mil 183 soles con 75 céntimos y 94 boletas emitidas, en las 4 sedes.');

  // ════ 01 Personal ════
  const P1 = 'Personal';
  await separador(P1, '01', 'Personal', 'Cada trabajador, su contrato y su expediente, en un solo lugar.',
    [['FaUsers', 'Empleados'], ['FaFileContract', 'Contratos'], ['FaFolderOpen', 'Expedientes'], ['FaUmbrellaBeach', 'Vacaciones']]);
  await modulo(P1, {
    nombre: 'Empleados', icono: 'FaUsers', titulo: 'Todo el personal, con su ficha completa',
    capturas: [['empleados', '/inicio/empleados']], difuminado: true,
    puntos: ['Busca y filtra por sede, área, cargo, contrato, pensión o fecha de ingreso.', 'Marca a varios en todas las páginas: exporta a Excel o envía su acceso por correo.', 'Da de baja o reactiva, con su foto y su estado a la vista.'],
    notas: 'Empleados es la lista de todo el personal. Se busca y se filtra por cualquier dato, y se puede marcar a varios para exportarlos o enviarles su acceso por correo.',
  });
  await modulo(P1, {
    nombre: 'Altas', icono: 'FaUserPlus', titulo: 'Dar de alta a uno o importar a todos desde Excel',
    capturas: [['empleado-nuevo', '/inicio/empleados/nuevo'], ['empleados-importar', '/inicio/empleados/importar']],
    puntos: ['Formulario por pasos que revisa cada dato antes de guardar.', 'Crea solo su contrato inicial y le envía su acceso al correo.', 'Importa un Excel completo y muestra los errores fila por fila antes de cargar.'],
    notas: 'Un trabajador nuevo se registra con un formulario por pasos. Si son muchos, se importan desde un Excel y el sistema muestra qué filas tienen errores antes de cargar nada.',
  });
  await modulo(P1, {
    nombre: 'Contratos', icono: 'FaFileContract', titulo: 'Cada contrato con sus fechas y su documento firmado',
    capturas: [['contratos', '/inicio/contratos']], difuminado: true,
    puntos: ['Vigentes, finalizados y por vencer, por tipo de contrato.', 'Al renovar, el contrato anterior se cierra solo.', 'El contrato firmado se sube en PDF y queda en su expediente.'],
    notas: 'Contratos guarda el historial de cada trabajador: el vigente, los anteriores y los que vencen pronto. El documento firmado se sube y queda en su expediente.',
  });
  await modulo(P1, {
    nombre: 'Expedientes', icono: 'FaFolderOpen', titulo: 'El expediente digital de cada trabajador',
    capturas: [['documentos', '/inicio/documentos'], ['expediente', '/inicio/documentos/…']], difuminado: true,
    puntos: ['Hoja de vida, contratos, boletas y otros documentos de cada persona.', 'Muestra a quién le falta la hoja de vida o tiene boletas por firmar.', 'Los documentos de años anteriores se suben en bloque.'],
    notas: 'Cada trabajador tiene su expediente digital con todos sus documentos. La lista avisa a quién le falta algo, como la hoja de vida o firmar sus boletas.',
  });
  await modulo(P1, {
    nombre: 'Vacaciones', icono: 'FaUmbrellaBeach', titulo: 'Solicitudes de vacaciones con su saldo',
    capturas: [['vacaciones', '/inicio/vacaciones']],
    puntos: ['30 días por año de servicio, con el saldo de cada trabajador.', 'El trabajador las pide desde el sistema; RR.HH. aprueba o rechaza.', 'Las vacaciones truncas se pagan al terminar el contrato.'],
    notas: 'El trabajador pide sus vacaciones en el sistema y RR.HH. las aprueba o rechaza, viendo su saldo. Hoy no hay solicitudes registradas, por eso la lista aparece vacía.',
  });

  // ════ 02 Planilla y boletas ════
  const P2 = 'Planilla y boletas';
  await separador(P2, '02', 'Planilla y boletas', 'Del cálculo del mes a la boleta firmada en el celular del trabajador.',
    [['FaChartPie', 'Panel'], ['FaTableList', 'Planillas'], ['FaFilePdf', 'Boletas'], ['FaStamp', 'Firma digital']]);
  await modulo(P2, {
    nombre: 'Panel de Control', icono: 'FaChartPie', titulo: 'El mes de un vistazo',
    capturas: [['panel', '/inicio/dashboard']],
    puntos: ['Total de la planilla, comparado con el mes y el año anterior.', 'Pendientes que llevan a la pantalla donde se resuelven.', 'Cumpleaños, edades, antigüedad y personal por sede.'],
    notas: 'El Panel de Control muestra el mes completo: cuánto se paga, cómo va la firma de las boletas, qué está pendiente y cómo es el personal. Se puede ver por mes y por sede, y bajar en Excel.',
  });
  await modulo(P2, {
    nombre: 'Planillas', icono: 'FaTableList', titulo: 'La planilla del mes, calculada según la ley',
    capturas: [['planillas', '/inicio/planillas'], ['planilla-corrida', '/inicio/planillas/corrida/…']], difuminado: true,
    puntos: ['Sueldo, asignación familiar, AFP u ONP, EsSalud, quinta y diezmo.', 'Bonos y descuentos de muchos trabajadores a la vez, o desde Excel.', 'Reporte de la planilla en Excel, con el neto exacto al céntimo.'],
    notas: 'La planilla se genera para todo el personal del mes. El sistema calcula los aportes y descuentos de ley, y los bonos o descuentos se aplican a varios a la vez o se importan desde Excel.',
  });
  await modulo(P2, {
    nombre: 'Importar PLAME', icono: 'FaFileImport', titulo: 'Los meses pagados antes del sistema, tal como se pagaron',
    capturas: [['importar-plame', '/inicio/planillas/importar-plame']],
    puntos: ['Se sube el Excel del PLAME de RR.HH. y se elige el mes.', 'Reconoce las columnas solo y muestra lo que va a cargar.', 'Con eso, la quinta y los beneficios usan lo que de verdad se pagó.'],
    notas: 'Para los meses que se pagaron antes del sistema, se sube el PLAME de RR.HH. tal como está. Así la renta de quinta, la CTS y la gratificación parten de lo que realmente se pagó.',
  });
  await modulo(P2, {
    nombre: 'Emisión de boletas', icono: 'FaFilePdf', titulo: 'Las boletas de todos, emitidas y firmadas por el colegio',
    capturas: [['emision', '/inicio/emision-boleta']], difuminado: true,
    puntos: ['Emite las boletas del mes de todos o de los marcados, con su avance.', 'Cada boleta en PDF lleva un código QR para comprobar que es auténtica.', 'El colegio las firma con su certificado digital, sin imprimir nada.'],
    notas: 'Desde Emisión de boletas se generan las boletas del mes. Cada una lleva un código QR para verificarla y la firma digital del colegio, que tiene el mismo valor legal que la firma a mano.',
  });

  // El trabajador
  s = pres.addSlide({ masterName: 'CONTENIDO', sectionTitle: P2 });
  s.addText('El trabajador recibe y firma su boleta desde el celular', { placeholder: 'title' });
  telefono(s, 'cap/movil-panel.jpg', 0.9, 1.45, 5.05, 'Celular');
  const flujo = [
    ['FaEnvelope', 'Le llega su acceso', 'Un enlace personal a su correo para crear su contraseña. Nunca el DNI.'],
    ['FaBell', 'Le avisa la boleta nueva', 'Un aviso en el sistema y en su correo cuando su boleta está lista.'],
    ['FaFileSignature', 'La revisa y la firma', 'La abre en el celular y la firma con su contraseña.'],
    ['FaQrcode', 'La puede comprobar', 'Cualquiera puede escanear el QR y ver que la boleta es auténtica.'],
  ];
  for (let i = 0; i < 4; i++) {
    const y = 1.55 + i * 1.28;
    await circulo(s, flujo[i][0], 4.0, y, 0.85, i % 2 ? H.oro : H.navy, i % 2 ? H.hondo : 'FFFFFF', 'Trabajador ' + (i + 1));
    s.addText([{ text: flujo[i][1], options: { bold: true, fontSize: 18, color: C.text2, breakLine: true } }, { text: flujo[i][2], options: { fontSize: 15, color: C.text1 } }],
      { x: 5.1, y: y - 0.05, w: 7.6, h: 1.1, margin: 0, valign: 'top', isTextBox: true });
  }
  s.addNotes('Del lado del trabajador: le llega su acceso por correo, recibe un aviso cuando su boleta está lista, la abre en el celular, la revisa y la firma. Y cualquiera puede comprobar con el QR que la boleta es auténtica. Todo el sistema funciona igual en el teléfono.');

  // ════ 03 Beneficios de ley ════
  const P3 = 'Beneficios de ley';
  await separador(P3, '03', 'Beneficios de ley', 'Renta de quinta, CTS y gratificaciones, calculadas como manda la norma.',
    [['FaCalculator', 'Renta de 5ta'], ['FaPiggyBank', 'CTS'], ['FaGift', 'Gratificaciones'], ['FaTags', 'Conceptos']]);
  await modulo(P3, {
    nombre: 'Renta de 5ta', icono: 'FaCalculator', titulo: 'La retención de quinta con el procedimiento de SUNAT',
    capturas: [['renta', '/inicio/renta-5ta'], ['renta-hoja', '/inicio/renta-5ta/…']], difuminado: true,
    puntos: ['Calcula mes a mes con el artículo 40 del Reglamento del Impuesto a la Renta.', 'Hoja de retención de cada trabajador, como la del PLAME.', 'En diciembre regulariza el año completo.'],
    notas: 'La renta de quinta se calcula cada mes con el procedimiento de SUNAT. Cada trabajador tiene su hoja de retención del año, igual a la que se armaba a mano, y en diciembre se regulariza.',
  });
  await modulo(P3, {
    nombre: 'CTS', icono: 'FaPiggyBank', titulo: 'La CTS de mayo y noviembre, lista para depositar',
    capturas: [['cts', '/inicio/cts']], difuminado: true,
    puntos: ['Remuneración computable con el sexto de la gratificación.', 'Meses y días de cada semestre, también para quien entró a mitad.', 'Registra el depósito y deja la hoja de liquidación en su expediente.'],
    notas: 'La CTS se calcula por semestre, para los depósitos de mayo y noviembre. Al registrar el depósito, la hoja de liquidación queda en el expediente del trabajador y la firma en el sistema.',
  });
  await modulo(P3, {
    nombre: 'Gratificaciones', icono: 'FaGift', titulo: 'Fiestas Patrias y Navidad, revisadas contra la ley',
    capturas: [['gratificaciones', '/inicio/gratificaciones']], difuminado: true,
    puntos: ['Calcula por meses completos, con la bonificación del 9 %.', 'Compara la ley con lo que tiene cada planilla y marca las diferencias.', 'La trunca va en la boleta del mes en que cesa el trabajador.'],
    notas: 'Las gratificaciones de julio y diciembre se calculan por meses completos con su 9 %. El sistema compara lo que manda la ley con lo que tiene cada planilla y señala cualquier diferencia.',
  });
  await modulo(P3, {
    nombre: 'Conceptos y ajustes', icono: 'FaSliders', titulo: 'Los montos de ley de cada año, sin tocar el código',
    capturas: [['ajustes', '/inicio/ajustes'], ['conceptos', '/inicio/conceptos-pago']],
    puntos: ['UIT, remuneración mínima, EsSalud y comisiones de AFP de cada año.', 'Bonos, descuentos y aportes del colegio, fijos o en porcentaje.', 'Cada cambio queda registrado en la auditoría.'],
    notas: 'Cuando cambia la UIT o el porcentaje de una AFP, RR.HH. lo actualiza en Ajustes, sin tocar el código. Los conceptos de pago definen cada bono, descuento o aporte.',
  });

  // ════ 04 Portal y administración ════
  const P4 = 'Portal y administración';
  await separador(P4, '04', 'Portal Web y administración', 'La página del colegio y el control de quién hace qué.',
    [['FaGlobe', 'Portal Web'], ['FaUserShield', 'Roles'], ['FaClipboardList', 'Auditoría'], ['FaGears', 'Ajustes']]);
  await modulo(P4, {
    nombre: 'Portal Web', icono: 'FaGlobe', titulo: 'La página web del colegio se edita aquí',
    capturas: [['portal', '/inicio/portal-web'], ['portal-portada', '/inicio/portal-web/portada']],
    puntos: ['Inicio, noticias, matrícula, proyectos, propuesta educativa y nosotros.', 'Fotos que se enderezan solas y textos con borrador antes de publicar.', 'Muestra qué falta cargar en cada página.'],
    notas: 'El sistema incluye el panel para editar la página web del colegio. Las noticias, la matrícula o los proyectos se actualizan aquí sin depender de un programador. Hoy muestra lo que falta cargar en cada página.',
  });
  await modulo(P4, {
    nombre: 'Roles y auditoría', icono: 'FaUserShield', titulo: 'Cada uno entra solo a lo suyo, y todo queda anotado',
    capturas: [['roles', '/inicio/roles'], ['auditoria', '/inicio/auditoria']], difuminado: true,
    puntos: ['Permisos por módulo: se marcan en Roles y el sistema los respeta.', 'La auditoría registra quién cambió qué y cuándo, con el antes y el después.', 'Ese registro no se puede editar ni borrar.'],
    notas: 'Los permisos se dan por módulo en Roles. Y la auditoría registra cada cambio importante: quién lo hizo, cuándo, y cómo estaba antes y después. Ese registro no se puede borrar.',
  });

  // ════ 05 Seguridad y calidad ════
  const P5 = 'Seguridad y calidad';
  await separador(P5, '05', 'Seguridad y calidad', 'Cumple la ley, protege los datos y se prueba antes de cada cambio.',
    [['FaGavel', 'Ley'], ['FaLock', 'Seguridad'], ['FaVialCircleCheck', 'Pruebas'], ['FaServer', 'Servidor']]);

  s = pres.addSlide({ masterName: 'CONTENIDO', sectionTitle: P5 });
  s.addText('Cada cálculo sigue la ley peruana', { placeholder: 'title' });
  const leyes = [
    ['FaFileSignature', 'Boleta digital', 'D.S. 009-2011-TR: la boleta se puede entregar por medios electrónicos.'],
    ['FaStamp', 'Firma digital', 'Ley 27269: la firma digital tiene el mismo valor que la firma a mano.'],
    ['FaCalculator', 'Renta de quinta', 'Artículo 40 del Reglamento de la Ley del Impuesto a la Renta.'],
    ['FaGift', 'Gratificaciones', 'Ley 27735, con la bonificación del 9 % de la Ley 30334.'],
    ['FaPiggyBank', 'CTS', 'D.S. 001-97-TR: depósitos de mayo y noviembre.'],
    ['FaUserShield', 'Datos personales', 'Ley 29733: los datos se usan solo para la relación laboral.'],
  ];
  for (let i = 0; i < 6; i++) {
    const x = 0.6 + (i % 3) * 4.1, y = 1.6 + Math.floor(i / 3) * 2.45;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w: 3.85, h: 2.2, rectRadius: 0.12, fill: { color: H.suave }, line: { color: H.suave }, objectName: 'Ley ' + (i + 1) });
    await circulo(s, leyes[i][0], x + 0.3, y + 0.3, 0.7, H.navy, 'FFFFFF', 'Ley ' + (i + 1));
    s.addText(leyes[i][1], { x: x + 1.15, y: y + 0.3, w: 2.5, h: 0.7, fontFace: 'Cambria', fontSize: 18, bold: true, color: C.text2, margin: 0, valign: 'middle', isTextBox: true });
    s.addText(leyes[i][2], { x: x + 0.3, y: y + 1.1, w: 3.3, h: 1.0, fontSize: 14, color: C.text1, margin: 0, valign: 'top', isTextBox: true });
  }
  s.addNotes('Todo lo que calcula y entrega el sistema sigue la norma vigente: la boleta electrónica, la firma digital, la renta de quinta, las gratificaciones, la CTS y la protección de datos personales.');

  s = pres.addSlide({ masterName: 'CONTENIDO', sectionTitle: P5 });
  s.addText('Los datos del personal están protegidos', { placeholder: 'title' });
  const seg = [
    ['FaLock', 'Contraseña solo suya', 'Nadie puede verla, ni RR.HH.; el primer acceso llega por correo.'],
    ['FaUserLock', 'Cada uno ve lo suyo', 'El trabajador solo ve sus boletas y documentos.'],
    ['FaClock', 'Sesión que se cierra sola', 'Tras 2 horas sin uso; se frenan los intentos repetidos.'],
    ['FaDatabase', 'Respaldo diario', 'Copia automática de la base de datos y los documentos.'],
  ];
  for (let i = 0; i < 4; i++) {
    const y = 1.6 + i * 1.22;
    await circulo(s, seg[i][0], 0.6, y, 0.8, i % 2 ? H.oro : H.navy, i % 2 ? H.hondo : 'FFFFFF', 'Seguridad ' + (i + 1));
    s.addText([{ text: seg[i][1], options: { bold: true, fontSize: 18, color: C.text2, breakLine: true } }, { text: seg[i][2], options: { fontSize: 14, color: C.text1 } }],
      { x: 1.6, y: y - 0.05, w: 4.4, h: 1.1, margin: 0, valign: 'top', isTextBox: true });
  }
  marco(s, IMG('privacidad'), 6.4, 1.5, 6.35, '/privacidad', 'Privacidad');
  s.addText('La página pública de privacidad explica al personal qué datos tiene el colegio y para qué.', { x: 6.4, y: 5.45, w: 6.35, h: 0.5, fontSize: 12, italic: true, color: H.gris, margin: 0, isTextBox: true });
  s.addNotes('Los datos del personal se cuidan en varias capas: contraseñas que nadie puede leer, permisos por rol, cierre automático de la sesión y un respaldo diario. Además hay una página pública de privacidad que explica todo esto en palabras sencillas.');

  // Cómo está hecho
  s = pres.addSlide({ masterName: 'CONTENIDO', sectionTitle: P5 });
  s.addText('Cómo está construido', { placeholder: 'title' });
  const capas = [
    ['FaDesktop', 'Pantallas', 'Angular: funciona en computadora y celular, en modo claro y oscuro.'],
    ['FaCodeBranch', 'Servidor', 'Laravel: hace los cálculos, firma las boletas y guarda los documentos.'],
    ['FaDatabase', 'Base de datos', 'MySQL, con respaldo automático todos los días.'],
    ['FaDocker', 'Instalación', 'Docker: se instala igual en cualquier servidor del colegio.'],
  ];
  for (let i = 0; i < 4; i++) {
    const x = 0.6 + i * 3.1;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y: 1.6, w: 2.85, h: 2.75, rectRadius: 0.12, fill: { color: H.suave }, line: { color: H.suave }, objectName: 'Capa ' + (i + 1) });
    await circulo(s, capas[i][0], x + 0.3, 1.85, 0.75, H.navy, 'FFFFFF', 'Capa ' + (i + 1));
    s.addText(capas[i][1], { x: x + 0.3, y: 2.75, w: 2.3, h: 0.45, fontFace: 'Cambria', fontSize: 18, bold: true, color: C.text2, margin: 0, isTextBox: true });
    s.addText(capas[i][2], { x: x + 0.3, y: 3.2, w: 2.3, h: 1.05, fontSize: 13.5, color: C.text1, margin: 0, valign: 'top', isTextBox: true });
    if (i < 3) s.addShape(pres.shapes.LINE, { x: x + 2.88, y: 2.95, w: 0.2, h: 0, line: { color: H.claro, width: 2, endArrowType: 'triangle' }, objectName: 'Flecha capa ' + (i + 1) });
  }
  const pruebas = [['730', 'pruebas automáticas', 'en cada cambio'], ['416', 'verificaciones de ataque', 'inyección SQL: ninguna pasó'], ['327', 'cambios registrados', 'desde mayo de 2026']];
  for (let i = 0; i < 3; i++) {
    const x = 0.6 + i * 4.1;
    s.addText(pruebas[i][0], { x, y: 4.65, w: 1.9, h: 1.0, fontFace: 'Cambria', fontSize: 48, bold: true, color: i === 0 ? H.oro : H.navy, margin: 0, valign: 'middle', isTextBox: true });
    s.addText([{ text: pruebas[i][1], options: { bold: true, fontSize: 16, color: C.text2, breakLine: true } }, { text: pruebas[i][2], options: { fontSize: 13, color: C.text1 } }],
      { x: x + 1.95, y: 4.75, w: 2.0, h: 0.85, margin: 0, valign: 'middle', isTextBox: true });
  }
  s.addText('Cada pantalla se revisa también en celular, porque ahí es donde los trabajadores firman.', { x: 0.6, y: 6.0, w: 11.4, h: 0.45, fontSize: 14, italic: true, color: H.gris, margin: 0, isTextBox: true });
  s.addNotes('Por dentro, el sistema tiene pantallas en Angular, un servidor en Laravel que hace los cálculos y firma, una base de datos MySQL con respaldo diario, y se instala con Docker en cualquier servidor. Antes de cada cambio pasa 730 pruebas automáticas.');

  // ════ Cierre ════
  pres.addSection({ title: 'Cierre' });
  s = pres.addSlide({ masterName: 'OSCURO', sectionTitle: 'Cierre' });
  s.addText('CATA-Recibo hoy', { x: 0.8, y: 0.7, w: 11.7, h: 0.9, fontFace: 'Cambria', fontSize: 40, bold: true, color: 'FFFFFF', margin: 0, isTextBox: true });
  const hoy = [
    ['FaTableList', 'La planilla del mes', 'calculada con todos los aportes y descuentos de ley'],
    ['FaFileSignature', 'Boletas digitales', 'con firma del colegio, QR y firma del trabajador en el celular'],
    ['FaScaleBalanced', 'Quinta, CTS y gratificaciones', 'según la norma, con sus hojas para el expediente'],
    ['FaFolderOpen', 'Expediente digital', 'de cada trabajador, con respaldo diario'],
    ['FaGlobe', 'La página web del colegio', 'editada desde el mismo sistema'],
  ];
  for (let i = 0; i < 5; i++) {
    const y = 1.85 + i * 0.86;
    await circulo(s, hoy[i][0], 0.8, y, 0.62, H.oro, H.hondo, 'Hoy ' + (i + 1));
    s.addText([{ text: hoy[i][1] + '  ', options: { bold: true, color: 'FFFFFF' } }, { text: hoy[i][2], options: { color: H.hielo } }],
      { x: 1.65, y, w: 10.8, h: 0.62, fontSize: 19, margin: 0, valign: 'middle', isTextBox: true });
  }
  s.addText('Gracias. ¿Preguntas?', { x: 0.8, y: 6.3, w: 8, h: 0.6, fontFace: 'Cambria', fontSize: 26, italic: true, color: H.oro, margin: 0, isTextBox: true });
  s.addImage({ path: 'logo-blanco.png', x: 10.3, y: 6.45, w: 2.2, h: 2.2 * 181 / 795, objectName: 'Logo' });
  s.addNotes('En resumen, hoy CATA-Recibo arma la planilla, entrega boletas digitales firmadas, calcula los beneficios de ley, guarda el expediente de cada trabajador y edita la página del colegio. Muchas gracias; quedo atento a sus preguntas.');

  await pres.writeFile({ fileName: 'CATA-Recibo-Consejo-Directivo.pptx' });
  await applyTheme('CATA-Recibo-Consejo-Directivo.pptx', THEME);
  console.log('listo');
})().catch((e) => { console.error(e); process.exit(1); });
