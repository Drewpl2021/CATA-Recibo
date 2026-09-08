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
  receipt: `<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/>`,
  settings: `<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>`,
  person: `<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>`,
  people: `<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>`,
  table_chart: `<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>`,
  description: `<path d="M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"/><path d="M17 8h4"/><path d="M17 12h4"/><path d="M17 16h4"/>`,
  remove_circle: `<circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/>`,
  folder: `<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>`,
  domain: `<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>`,
  badge: `<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/>`,
  admin_panel_settings: `<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>`,
  date_range: `<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>`,
  location_on: `<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>`,
  view_module: `<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>`,
  folder_open: `<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><polyline points="8 10 12 14 16 10"/>`,
  receipt_long: `<path d="M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"/><path d="M17 8h4"/><path d="M17 12h4"/><path d="M17 16h4"/><path d="M12 8H8"/><path d="M12 12H8"/><path d="M12 16H8"/>`,
  dashboard: `<rect x="3" y="3" width="8" height="9" rx="1"/><rect x="13" y="3" width="8" height="5" rx="1"/><rect x="13" y="12" width="8" height="9" rx="1"/><rect x="3" y="16" width="8" height="5" rx="1"/>`,
  folder_shared: `<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><path d="M12 11a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M10 19c0-2.2 1.8-4 4-4s4 1.8 4 4"/>`,
  money: `<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>`,
  shield: `<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>`,
  calendar_check: `<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/>`,
  beach: `<path d="M22 21H2"/><path d="M6 18l6-12 6 12"/><circle cx="12" cy="4" r="2"/>`,
  chart: `<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>`,
  bell: `<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>`,
  bar_chart: `<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>`,
  build: `<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>`,
  lock: `<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>`,
  circle: `<circle cx="12" cy="12" r="10"/>`,

  // ── Tipos de concepto de pago y filtros ──
  trending_up: `<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>`,
  trending_down: `<polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/>`,
  clock: `<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>`,
  filter: `<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>`,
  layers: `<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>`,

  // ── Cifras de la cabecera ──
  check_circle: `<circle cx="12" cy="12" r="10"/><polyline points="8.5 12.5 11 15 15.5 9.5"/>`,
  pause_circle: `<circle cx="12" cy="12" r="10"/><line x1="10" y1="9" x2="10" y2="15"/><line x1="14" y1="9" x2="14" y2="15"/>`,
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
  usuarios: 'people',

  // ── Personal y planilla ──
  empleados: 'people',
  planillas: 'table_chart',
  'emision de boleta': 'receipt',
  'emision de boletas': 'receipt',
  boletas: 'description',
  contratos: 'description',
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
