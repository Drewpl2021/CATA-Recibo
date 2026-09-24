/**
 * Catálogo único de íconos SVG de la app (paths internos de un <svg> 24x24).
 *
 * Las claves son las mismas que siembra el backend en modulos.icono
 * (ModuloSeeder), así el ícono del sidebar y el de la cabecera de la página
 * son SIEMPRE el mismo: si el menú muestra "Áreas" con el ícono `domain`,
 * la cabecera de Áreas muestra ese mismo ícono.
 *
 * Para pintarlos usa el componente <app-icon>, que además resuelve el ícono
 * por el NOMBRE del módulo cuando la base de datos no trae uno utilizable.
 */
export const ICON_MAP: Record<string, string> = {
  // ── Personas ──
  person: `<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>`,
  people: `<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>`,
  user_check: `<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>`,
  badge: `<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M16 10h2"/><path d="M16 14h2"/><circle cx="9" cy="11" r="2"/><path d="M6.2 15.5a3.2 3.2 0 0 1 5.6 0"/>`,

  // ── Boletas y documentos ──
  /* La boleta de verdad: el papel con el borde dentado de la impresora. El
     ícono de antes era una hoja cualquiera y no se distinguía de contratos
     ni de documentos. */
  receipt: `<path d="M4 3.5 6 2.5l2 1 2-1 2 1 2-1 2 1 2-1V20a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/>`,
  receipt_long: `<path d="M4 3.5 6 2.5l2 1 2-1 2 1 2-1 2 1 2-1v17.5l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/>`,
  description: `<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>`,
  file: `<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>`,
  folder: `<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>`,
  folder_open: `<path d="m6 14 1.6-3.1A2 2 0 0 1 9.4 10H20a2 2 0 0 1 1.9 2.6l-1.6 6A2 2 0 0 1 18.4 20H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.7.9l.8 1.2a2 2 0 0 0 1.7.9H18a2 2 0 0 1 2 2v2"/>`,
  folder_shared: `<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/><circle cx="12" cy="12" r="2"/><path d="M9 17a3 3 0 0 1 6 0"/>`,
  clipboard_check: `<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>`,

  // ── Planilla y dinero ──
  /* Antes era el símbolo del dólar suelto, y en el colegio se habla de
     soles. Un billete no depende de la moneda y se lee igual de lejos. */
  money: `<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01"/><path d="M18 12h.01"/>`,
  wallet: `<path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h14a2 2 0 0 1 2 2v3"/><path d="M3 5v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3"/><path d="M21 12h-4a2 2 0 0 0 0 4h4"/>`,
  table_chart: `<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 9v12"/>`,
  chart: `<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>`,
  bar_chart: `<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>`,
  trending_up: `<path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/>`,
  trending_down: `<path d="M16 17h6v-6"/><path d="m22 17-8.5-8.5-5 5L2 7"/>`,
  remove_circle: `<circle cx="12" cy="12" r="10"/><path d="M8 12h8"/>`,

  // ── El colegio: sus áreas, sus sedes ──
  domain: `<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>`,
  location_on: `<path d="M20 10c0 5-5.5 10.2-7.4 11.8a1 1 0 0 1-1.2 0C9.5 20.2 4 15 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>`,
  graduation_cap: `<path d="M21.4 10.9a1 1 0 0 0 0-1.8l-8.6-3.9a2 2 0 0 0-1.7 0L2.6 9.1a1 1 0 0 0 0 1.8l8.6 3.9a2 2 0 0 0 1.7 0Z"/><path d="M22 10v6"/><path d="M6 12.5V17c0 1.7 2.7 3 6 3s6-1.3 6-3v-4.5"/>`,

  // ── Tiempo ──
  date_range: `<path d="M8 2v4"/><path d="M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/>`,
  calendar_check: `<path d="M8 2v4"/><path d="M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/>`,
  clock: `<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>`,
  /* Vacaciones: una palmera. La "sombrilla" de antes era un triángulo sobre
     una raya, y de lejos parecía una montaña. */
  beach: `<path d="M13 8c0-2.8-2.5-5-5.5-5S2 5.2 2 8h2l1-1 1 1h4"/><path d="M13 7.1A5.8 5.8 0 0 1 16.5 6c3 0 5.5 2.2 5.5 5h-3l-1-1-1 1h-3"/><path d="M5.9 9.7c-2.2 2.2-2.3 5.1-.4 7l4.3-4.2.7-.7.7-.7 2.1-2.1c-1.9-2-4.9-1.8-7 .4Z"/><path d="M11 15.5c.5 2.5-.2 4.5-1 6.5h4c2-5.5-.5-12-1-14"/>`,

  // ── El sistema por dentro ──
  dashboard: `<rect x="3" y="3" width="8" height="9" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="13" y="12" width="8" height="9" rx="2"/><rect x="3" y="16" width="8" height="5" rx="2"/>`,
  view_module: `<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/>`,
  settings: `<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>`,
  build: `<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>`,
  layers: `<path d="M12.8 2.2a2 2 0 0 0-1.6 0L2.6 6.1a1 1 0 0 0 0 1.8l8.6 3.9a2 2 0 0 0 1.6 0l8.6-3.9a1 1 0 0 0 0-1.8Z"/><path d="m22 12.6-9.2 4.2a2 2 0 0 1-1.6 0L2 12.6"/><path d="m22 17.6-9.2 4.2a2 2 0 0 1-1.6 0L2 17.6"/>`,
  search: `<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>`,
  /* Los filtros: tres rayas que se van acortando. El embudo de antes era
     una figura rellena y pesaba el doble que todo lo demás. */
  filter: `<path d="M3 6h18"/><path d="M7 12h10"/><path d="M10 18h4"/>`,
  upload: `<path d="M12 3v12"/><path d="m17 8-5-5-5 5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>`,
  download: `<path d="M12 15V3"/><path d="m7 10 5 5 5-5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>`,
  mail: `<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-9 5.7a2 2 0 0 1-2 0L2 7"/>`,
  bell: `<path d="M10.3 21a2 2 0 0 0 3.4 0"/><path d="M3.3 15.3A1 1 0 0 0 4 17h16a1 1 0 0 0 .7-1.7C19.4 14 18 12.5 18 8A6 6 0 0 0 6 8c0 4.5-1.4 6-2.7 7.3"/>`,

  // ── Seguridad ──
  shield: `<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>`,
  admin_panel_settings: `<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><circle cx="12" cy="10.5" r="2.5"/><path d="M8.2 17.5a4.2 4.2 0 0 1 7.6 0"/>`,
  lock: `<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>`,
  /* Firmar. Antes se usaba el lápiz de editar y se leía como "corregir esto",
     que es justo lo contrario de lo que hace: una firma no se edita. Esto es
     el trazo de una rúbrica sobre la línea de firma. */
  signature: `<path d="M3 17c1.8-3.6 3.4-5.4 4.8-5.4 2.1 0 1.4 4.2 3.2 4.2 1.4 0 2-2.4 3.4-2.4 1 0 1.3 1.2 2.2 1.2.7 0 1.4-.5 2.4-1.6"/><path d="M3 21h18"/>`,

  // ── Estados: las cifras de la cabecera y los avisos ──
  check_circle: `<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>`,
  pause_circle: `<circle cx="12" cy="12" r="10"/><path d="M10 15V9"/><path d="M14 15V9"/>`,
  warning: `<path d="m10.3 3.9-8.5 14A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.7-3.1l-8.5-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>`,
  circle: `<circle cx="12" cy="12" r="10"/>`,
};

/** Ícono que se usa cuando no hay ninguna coincidencia. */
const ICONO_POR_DEFECTO = 'circle';

/** Las claves disponibles, ordenadas — para los selectores de ícono del admin. */
export const CLAVES_ICONO: readonly string[] = Object.keys(ICON_MAP).sort();

/**
 * Nombres de módulo (ya normalizados) emparejados con su ícono.
 *
 * Sirve para que un módulo nuevo salga con ícono aunque en la base de datos
 * venga vacío o con una clave que el front todavía no conoce: basta con que
 * el nombre coincida. Las claves van sin tildes y en minúsculas — normaliza()
 * se encarga de eso antes de buscar.
 */
const ICONO_POR_NOMBRE: Record<string, string> = {
  // ── Módulos padre ──
  'boletas y finanzas': 'receipt',
  configuracion: 'settings',
  'configuracion base': 'settings',
  'mi espacio': 'person',
  administracion: 'admin_panel_settings',
  reportes: 'chart',

  // ── Configuración base ──
  areas: 'domain',
  cargos: 'badge',
  sedes: 'location_on',
  periodos: 'date_range',
  roles: 'shield',
  'modulos padre': 'folder_open',
  modulos: 'view_module',
  'conceptos de pago': 'money',
  usuarios: 'user_check',

  // ── Personal y planilla ──
  empleados: 'people',
  planillas: 'table_chart',
  'emision de boleta': 'receipt',
  'emision de boletas': 'receipt',
  // Boletas y contratos tenían el MISMO ícono y en el menú se veían
  // iguales: ahora la boleta es la boleta, y el contrato el papel
  // firmado.
  boletas: 'receipt_long',
  contratos: 'clipboard_check',
  vacaciones: 'beach',
  descuentos: 'remove_circle',
  documentos: 'folder',

  // ── Autoservicio ──
  'mis boletas': 'receipt_long',
  'mis documentos': 'folder_shared',
  'mis vacaciones': 'beach',
  'mi perfil': 'person',
  'historial de boletas': 'receipt_long',
  notificaciones: 'bell',
  'panel de control': 'dashboard',
  inicio: 'dashboard',
  dashboard: 'dashboard',
};

/**
 * Baja a minúsculas, quita tildes y aprieta los espacios, para que
 * "Áreas", "áreas" y "  AREAS " lleguen todos como "areas".
 */
function normaliza(texto: string): string {
  return texto
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Devuelve la CLAVE de ícono que le toca a un módulo o módulo padre.
 *
 * Busca en este orden:
 *   1. El nombre exacto del módulo ("Áreas" → domain).
 *   2. El ícono que mandó el backend, si el catálogo lo conoce.
 *   3. Alguna palabra del nombre ("Reporte de Áreas" → domain).
 *   4. El ícono por defecto.
 *
 * El nombre va primero a propósito: si RR.HH. da de alta un módulo desde la
 * base de datos sin ícono, o con una clave que el front todavía no tiene,
 * igual sale con el ícono correcto solo por llamarse como se llama.
 */
export function resolverIconoModulo(nombre?: string | null, iconoBd?: string | null): string {
  const limpio = normaliza(nombre ?? '');

  if (limpio && ICONO_POR_NOMBRE[limpio]) {
    return ICONO_POR_NOMBRE[limpio];
  }

  if (iconoBd && ICON_MAP[iconoBd]) {
    return iconoBd;
  }

  // Coincidencia parcial: "Reporte de Áreas" o "Áreas académicas" → domain.
  for (const [clave, icono] of Object.entries(ICONO_POR_NOMBRE)) {
    if (limpio.includes(clave)) {
      return icono;
    }
  }

  return ICONO_POR_DEFECTO;
}

/** Los paths SVG de una clave del catálogo. */
export function getIconPath(nombre?: string | null): string {
  return ICON_MAP[nombre ?? ''] ?? ICON_MAP[ICONO_POR_DEFECTO];
}
