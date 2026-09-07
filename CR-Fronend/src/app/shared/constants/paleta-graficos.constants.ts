/**
 * Paleta de los gráficos (ApexCharts).
 *
 * ApexCharts recibe los colores por JavaScript, no por CSS, así que es el
 * único sitio de la app donde un color se escribe en duro fuera de
 * `styles.scss`. Estos valores son copia literal de los tokens
 * `--brand-*` / `--success` / `--warning`: si cambia la paleta allá,
 * hay que tocarlos acá también. No inventes azules nuevos: si necesitas
 * otro tono, sale de PALETA_MARCA.
 */

/** La escala del azul institucional, misma que --brand-* en styles.scss. */
export const PALETA_MARCA = {
  b900: '#0E2650',
  b700: '#1B4282',
  b600: '#2A5AA8',
  b500: '#3B72C4',
  b100: '#E7EEF9',
} as const;

/** Acento dorado del colegio, mismo que --accent. */
export const PALETA_ACENTO = '#F4B41A';

/** Colores de estado, mismos que --success / --warning / --danger. */
export const PALETA_ESTADO = {
  exito: '#10B981',
  aviso: '#F59E0B',
  peligro: '#E24C41',
} as const;

/**
 * Serie categórica para tortas y donas: azul de marca primero, y de ahí
 * tonos que se distinguen entre sí sin pelearse con la interfaz.
 */
export const PALETA_SERIES = [
  PALETA_MARCA.b700,
  PALETA_ACENTO,
  PALETA_ESTADO.exito,
  PALETA_MARCA.b500,
  PALETA_ESTADO.aviso,
] as const;

/** Serie de un solo color (barras, líneas): el azul institucional. */
export const PALETA_SERIE_UNICA = [PALETA_MARCA.b700];

/** Color al que degrada una barra horizontal, del institucional al claro. */
export const PALETA_DEGRADADO_BARRA = [PALETA_MARCA.b500];
