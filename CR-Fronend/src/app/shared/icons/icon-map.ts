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
 *
 * Los dibujos son de Hugeicons (estilo "stroke rounded", licencia MIT),
 * elegidos el 2026-09-28 frente a Lucide, Phosphor y Solar: mismo tipo de
 * trazo que los de antes, con esquinas más suaves. Cada dibujo trae su
 * propio trazo (1.5 px), así que el `grosor` de <app-icon> no los cambia.
 * Para agregar uno nuevo, copiar el "body" de la familia hugeicons en
 * https://icon-sets.iconify.design/hugeicons/ — nunca mezclar con otra
 * familia: es lo que más rápido hace ver desordenada una pantalla.
 */
export const ICON_MAP: Record<string, string> = {
  // ── Personas ──
  person: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M20 21a7.53 7.53 0 0 0-7.005-6.934L12 14q-.531.015-1 .038c-3.7.181-6.716 3.268-7 6.962"/><circle cx="12" cy="7" r="4"/></g>`,
  people: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.5 20.5c-.234-2.931-2.658-5.252-5.692-5.448L11.999 15q-.431.012-.811.03C8.18 15.172 5.73 17.597 5.5 20.5m9.75-11.25a3.25 3.25 0 1 1-6.5 0a3.25 3.25 0 0 1 6.5 0M5.502 8.5A3.25 3.25 0 0 1 9.5 3.752M18.496 8.5A3.25 3.25 0 0 0 14.5 3.752M22 18c-.18-2.263-2-4.5-4-5M2 18c.18-2.263 2-4.5 4-5"/>`,
  user_check: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M3 21c.284-3.694 3.3-6.78 7-6.962q.469-.023 1-.038l.995.066a7.5 7.5 0 0 1 3.005.849"/><circle cx="11" cy="7" r="4"/><path d="M14 19.333s.875 0 1.75 1.667c0 0 2.78-4.167 5.25-5"/></g>`,
  user_off: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M3 21c.284-3.694 3.3-6.78 7-6.962q.469-.023 1-.038l.995.066a7.5 7.5 0 0 1 3.005.849"/><circle cx="11" cy="7" r="4"/><path d="M15.5 18.5h6"/></g>`,
  badge: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M2.001 8.5L2 13.997c-.001 3.3-.002 4.951 1.023 5.977S5.698 21 9 21h6c3.3 0 4.949 0 5.974-1.025S22 17.3 22 14.001l.001-5.501m-13.502-2c0-1.404 0-2.107.337-2.611a2 2 0 0 1 .551-.552C9.892 3 10.594 3 12 3c1.404 0 2.106 0 2.61.337a2 2 0 0 1 .553.552c.337.504.337 1.207.337 2.611"/><path d="M19.998 6.5h-16a2 2 0 0 0-2 2a4 4 0 0 0 4.001 4h11.999a4 4 0 0 0 4-4a2 2 0 0 0-2-2m-9.999 6v1c0 .465 0 .697.05.888a1.5 1.5 0 0 0 1.061 1.06c.191.052.424.052.889.052s.697 0 .888-.051a1.5 1.5 0 0 0 1.06-1.06c.052-.191.052-.424.052-.889v-1"/></g>`,

  // ── Boletas y documentos ──
  /* La boleta de verdad: el papel con el borde dentado de la impresora, sin
     signo de moneda (el colegio paga en soles). La hoja cualquiera de antes
     no se distinguía de contratos ni de documentos. */
  receipt: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m8.068 2.725l-.172.137c-.185.147-.278.22-.37.267c-.319.16-.7.139-1-.055c-.086-.056-.17-.14-.337-.306c-.4-.396-.6-.595-.751-.668a1.003 1.003 0 0 0-1.383.568C4 2.826 4 3.106 4 3.667v17.031c0 .257 0 .385.012.46c.114.736.974 1.09 1.58.649c.061-.045.153-.136.336-.317c.115-.114.173-.172.227-.216a1.51 1.51 0 0 1 1.779-.098c.059.038.123.089.25.19l.136.108c.23.183.346.274.461.336c.448.243.99.243 1.438 0c.115-.062.23-.153.46-.336l.071-.056c.297-.235.445-.353.598-.426a1.51 1.51 0 0 1 1.304 0c.153.073.301.19.598.426l.07.056c.23.183.346.274.461.336c.448.243.99.243 1.438 0c.115-.062.23-.153.46-.336l.137-.108c.127-.101.191-.152.25-.19a1.51 1.51 0 0 1 1.779.098c.054.044.112.101.227.216c.183.181.275.272.336.317c.606.44 1.467.087 1.58-.649c.012-.075.012-.203.012-.46V3.667c0-.561 0-.841-.055-.999a1.003 1.003 0 0 0-1.383-.568c-.15.073-.35.272-.75.668c-.168.166-.252.25-.339.306a1.01 1.01 0 0 1-1 .055c-.091-.047-.184-.12-.369-.267l-.172-.137c-.47-.373-.706-.56-.963-.643c-.304-.1-.634-.1-.938 0c-.257.084-.492.27-.963.643L13 2.78c-.357.284-.536.425-.734.48a1 1 0 0 1-.532 0c-.198-.055-.377-.196-.734-.48l-.068-.054c-.47-.373-.706-.56-.962-.643a1.5 1.5 0 0 0-.94 0c-.256.084-.491.27-.962.643M8 12h8M8 8h4m-4 8h8"/>`,
  receipt_long: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 18.646V8.054c0-2.854 0-4.28.879-5.167C5.757 2 7.172 2 10 2h4c2.828 0 4.243 0 5.121.887C20 3.773 20 5.2 20 8.054v10.592c0 1.511 0 2.267-.462 2.565c-.755.486-1.922-.534-2.509-.904c-.485-.306-.727-.458-.997-.467c-.29-.01-.537.137-1.061.467l-1.911 1.205c-.516.325-.773.488-1.06.488s-.544-.163-1.06-.488l-1.91-1.205c-.486-.306-.728-.458-.997-.467c-.291-.01-.538.137-1.062.467c-.587.37-1.754 1.39-2.51.904C4 20.913 4 20.158 4 18.646M11 11H8m6-4H8"/>`,
  description: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 17h8m-8-4h4m1-10.5V3c0 2.828 0 4.243.879 5.121C14.757 9 16.172 9 19 9h.5m.5 1.657V14c0 3.771 0 5.657-1.172 6.828S15.771 22 12 22s-5.657 0-6.828-1.172S4 17.771 4 14V9.456c0-3.245 0-4.868.886-5.967a4 4 0 0 1 .603-.603C6.59 2 8.211 2 11.456 2c.705 0 1.058 0 1.381.114q.1.036.197.082c.31.148.559.397 1.058.896l4.736 4.736c.579.578.867.868 1.02 1.235c.152.368.152.776.152 1.594"/>`,
  file: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h8m-8 4h4m1 10.5V21c0-2.828 0-4.243.879-5.121C14.757 15 16.172 15 19 15h.5m.5-1.657V10c0-3.771 0-5.657-1.172-6.828S15.771 2 12 2S6.343 2 5.172 3.172S4 6.229 4 10v4.544c0 3.245 0 4.868.886 5.967a4 4 0 0 0 .603.603C6.59 22 8.211 22 11.456 22c.705 0 1.058 0 1.381-.114q.1-.036.197-.082c.31-.148.559-.397 1.058-.896l4.736-4.736c.579-.578.867-.867 1.02-1.235c.152-.368.152-.776.152-1.594"/>`,
  folder: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M8 7h8.75c2.107 0 3.16 0 3.917.506a3 3 0 0 1 .827.827C22 9.09 22 10.143 22 12.25c0 3.511 0 5.267-.843 6.528a5 5 0 0 1-1.38 1.38C18.518 21 16.762 21 13.25 21H12c-4.714 0-7.071 0-8.536-1.465C2 18.072 2 15.715 2 11V7.944c0-1.816 0-2.724.38-3.406A3 3 0 0 1 3.538 3.38C4.22 3 5.128 3 6.944 3C8.108 3 8.69 3 9.2 3.191c1.163.436 1.643 1.493 2.168 2.542L12 7"/>`,
  folder_open: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 20V8.877c0-1.288 0-1.931.285-2.407a2 2 0 0 1 .685-.685C3.946 5.5 4.594 5.5 5.892 5.5c.631 0 .947 0 1.234.088a2 2 0 0 1 .539.26c.248.168.444.413.835.902s.587.734.835.903a2 2 0 0 0 .539.259C10.16 8 10.474 8 11.1 8H15c1.404 0 2.107 0 2.611.337a2 2 0 0 1 .552.552c.337.504.337 1.207.337 2.611"/><path d="m4.42 14.014l-.786 2c-.978 2.486-1.467 3.729-.882 4.607s1.901.879 4.533.879h7.905c1.235 0 1.852 0 2.34-.32c.487-.321.74-.893 1.247-2.038l.885-2c1.125-2.543 1.687-3.814 1.106-4.728s-1.952-.914-4.693-.914H8.072c-1.29 0-1.934 0-2.434.344s-.739.953-1.218 2.17Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M11.5 4.515c.915-1.23 2.166-1.96 4.012-2.013a4.1 4.1 0 0 1 1.756.353c1.307.571 2.15 1.301 2.732 2.645L21.5 3"/></g>`,
  folder_shared: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linejoin="round" d="M16.361 21h4.278c.885 0 1.633-.601 1.265-1.247C21.314 18.719 20.01 18 18.5 18s-2.813.719-3.404 1.753c-.368.646.38 1.247 1.265 1.247Z"/><path d="M18.497 15.5a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5Z"/><path stroke-linecap="round" d="M12.003 21c-4.716 0-7.073 0-8.538-1.465C2 18.072 2 15.715 2 11V7.944c0-1.816 0-2.724.38-3.406A3 3 0 0 1 3.538 3.38C4.22 3 5.128 3 6.946 3C8.11 3 8.692 3 9.2 3.191c1.163.436 1.643 1.493 2.168 2.542L12.003 7M8.002 7h8.752c2.107 0 3.16 0 3.918.506a3 3 0 0 1 .828.827c.394.59.48 1.36.5 2.667"/></g>`,
  clipboard_check: `<g fill="none"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m9 14l2 2l4-4"/><path stroke="currentColor" stroke-linejoin="round" stroke-width="1.5" d="M8 4v1c0 .943 0 1.414.293 1.707S9.057 7 10 7h4c.943 0 1.414 0 1.707-.293S16 5.943 16 5V4"/><path fill="currentColor" d="m14.45 4l-.735.15a.75.75 0 0 0 .727.6zm-4.9 0l.008.75a.75.75 0 0 0 .727-.6zm9.571.879l.53-.53zm0 16.242l.53.53zM16 4.017l.023-.75h-.015zM4.879 4.88l.53.53zM8 4.017l-.008-.75h-.015zM14.45 4l.735-.15A3.25 3.25 0 0 0 12 1.25v1.5c.846 0 1.553.6 1.715 1.4zM12 2v-.75a3.25 3.25 0 0 0-3.185 2.6L9.55 4l.735.15A1.75 1.75 0 0 1 12 2.75zm8 8h.75c0-1.393.002-2.513-.116-3.392c-.122-.9-.38-1.658-.982-2.26l-.53.53l-.531.531c.277.277.457.665.556 1.4c.101.754.103 1.756.103 3.191zm0 6h.75v-6h-1.5v6zm0 0h-.75c0 1.435-.002 2.436-.103 3.192c-.099.734-.28 1.122-.556 1.399l.53.53l.53.53c.603-.601.861-1.36.983-2.26c.118-.878.116-1.998.116-3.391zm-6 6v.75c1.393 0 2.513.002 3.392-.116c.9-.122 1.658-.38 2.26-.982l-.53-.53l-.531-.531c-.277.277-.665.457-1.4.556c-.755.101-1.756.103-3.191.103zm2-17.983l-.023.75c1.565.047 2.203.231 2.614.642l.53-.53l.53-.53c-.871-.873-2.086-1.035-3.628-1.081zM10 22v.75h4v-1.5h-4zm0 0v-.75c-1.435 0-2.437-.002-3.192-.103c-.734-.099-1.122-.28-1.399-.556l-.53.53l-.53.53c.601.603 1.36.861 2.26.983c.878.118 1.998.116 3.391.116zm-6-6h-.75c0 1.393-.002 2.513.117 3.392c.12.9.38 1.658.981 2.26l.53-.53l.531-.531c-.277-.277-.457-.665-.556-1.4c-.101-.755-.103-1.756-.103-3.191zm0-6h.75c0-1.435.002-2.437.103-3.192c.099-.734.28-1.122.556-1.399l-.53-.53l-.53-.53c-.603.601-.861 1.36-.982 2.26c-.119.878-.117 1.998-.117 3.391zm4-5.983l-.023-.75c-1.542.047-2.757.21-3.629 1.081l.53.53l.531.531c.41-.41 1.049-.595 2.614-.642zM4 10h-.75v6h1.5v-6zm4-5.983l.008.75l1.55-.017L9.55 4l-.008-.75l-1.55.017zm8 0l.008-.75l-1.55-.017l-.008.75l-.008.75l1.55.017z"/></g>`,

  // ── Planilla y dinero ──
  /* Antes era el símbolo del dólar suelto, y en el colegio se habla de
     soles. Un billete no depende de la moneda y se lee igual de lejos. */
  money: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M14.5 12.001a2.5 2.5 0 1 1-5 0a2.5 2.5 0 0 1 5 0"/><path d="M16 5.001c2.48 0 4.19.384 5.133.676c.543.168.867.683.867 1.251v9.755c0 1.115-1.228 1.954-2.324 1.747c-.94-.177-2.165-.32-3.676-.32c-4.75 0-5.89 1.806-12.855.27A1.47 1.47 0 0 1 2 16.94V6.921c0-.976.92-1.687 1.878-1.497C10.197 6.678 11.421 5 16 5"/><path d="M2 9.001c1.951 0 3.705-1.595 3.929-3.246M18.5 5.501c0 2.04 1.765 3.969 3.5 3.969m0 5.531c-1.9 0-3.74 1.31-3.898 3.098M6 18.497a4 4 0 0 0-4-4"/></g>`,
  wallet: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M14 3H5a2 2 0 1 0 0 4h13c0-.93 0-1.395-.102-1.776a3 3 0 0 0-2.121-2.122C15.395 3 14.93 3 14 3"/><path d="M3 5v10c0 2.828 0 4.243.879 5.121C4.757 21 6.172 21 9 21h6c2.828 0 4.243 0 5.121-.879C21 19.243 21 17.828 21 15v-2c0-2.828 0-4.243-.879-5.121C19.243 7 17.828 7 15 7H7"/><path d="M21 12h-2c-.465 0-.698 0-.888.051a1.5 1.5 0 0 0-1.06 1.06C17 13.303 17 13.536 17 14s0 .697.051.888a1.5 1.5 0 0 0 1.06 1.06c.191.052.424.052.889.052h2"/></g>`,
  table_chart: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 3h-2C7.229 3 5.343 3 4.172 4.172S3 7.229 3 11v2c0 3.771 0 5.657 1.172 6.828S7.229 21 11 21h2c3.771 0 5.657 0 6.828-1.172S21 16.771 21 13v-2c0-3.771 0-5.657-1.172-6.828S16.771 3 13 3M9 3v18m6-18v18m6-12H3m18 6H3"/>`,
  chart: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M7 17v-4m5 4V7m5 10v-6"/><path stroke-linejoin="round" d="M2.5 12c0-4.478 0-6.718 1.391-8.109S7.521 2.5 12 2.5c4.478 0 6.718 0 8.109 1.391S21.5 7.521 21.5 12c0 4.478 0 6.718-1.391 8.109S16.479 21.5 12 21.5c-4.478 0-6.718 0-8.109-1.391S2.5 16.479 2.5 12Z"/></g>`,
  bar_chart: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M7 17v-4m5 4V7m5 10v-6"/><path stroke-linejoin="round" d="M2.5 12c0-4.478 0-6.718 1.391-8.109S7.521 2.5 12 2.5c4.478 0 6.718 0 8.109 1.391S21.5 7.521 21.5 12c0 4.478 0 6.718-1.391 8.109S16.479 21.5 12 21.5c-4.478 0-6.718 0-8.109-1.391S2.5 16.479 2.5 12Z"/></g>`,
  trending_up: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path d="M21 21H10c-3.3 0-4.95 0-5.975-1.025S3 17.3 3 14V3"/><path stroke-linejoin="round" d="M7.997 16.999c3.532 0 10.915-1.464 10.7-10.566m-2.208 1.61l1.883-1.897a.497.497 0 0 1 .703-.003l1.922 1.9"/></g>`,
  trending_down: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path d="M21 21H10c-3.3 0-4.95 0-5.975-1.025S3 17.3 3 14V3"/><path stroke-linejoin="round" d="M6.997 5.999c3.532 0 10.915 1.464 10.7 10.566m-2.208-1.61l1.883 1.897a.497.497 0 0 0 .703.003l1.922-1.9"/></g>`,
  remove_circle: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12H8"/><circle cx="12" cy="12" r="10"/></g>`,

  // ── El colegio: sus áreas, sus sedes ──
  domain: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linejoin="round" d="m16 10l2.15.645c1.373.412 2.06.618 2.455 1.15c.395.53.395 1.248.395 2.681V22"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 9h3m-3 4h3"/><path stroke-linejoin="round" d="M12 22v-3c0-.943 0-1.414-.293-1.707S10.943 17 10 17H9c-.943 0-1.414 0-1.707.293S7 18.057 7 19v3"/><path stroke-linecap="round" d="M2 22h20"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 22V6.717c0-2.51 0-3.766.791-4.389s1.956-.284 4.287.392l5 1.451c1.406.408 2.109.612 2.515 1.169C16 5.896 16 6.653 16 8.169V22"/></g>`,
  location_on: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13.618 21.367A2.37 2.37 0 0 1 12 22a2.37 2.37 0 0 1-1.617-.633C6.412 17.626 1.09 13.447 3.685 7.38C5.09 4.1 8.458 2 12.001 2s6.912 2.1 8.315 5.38c2.592 6.06-2.717 10.259-6.698 13.987Z"/><path d="M15.5 11a3.5 3.5 0 1 1-7 0a3.5 3.5 0 0 1 7 0Z"/></g>`,
  graduation_cap: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M1.998 8c0 1.341 8.096 5 9.988 5s9.987-3.659 9.987-5c0-1.343-8.096-5.001-9.987-5.001s-9.988 3.658-9.988 5"/><path d="m5.992 11l1.251 5.8c.086.398.284.769.614 1.005c2.222 1.595 6.034 1.595 8.256 0c.33-.236.527-.607.613-1.005l1.251-5.8m2.5-1.5v7m0 0c-.79 1.447-1.14 2.222-1.496 3.501c-.077.455-.016.684.298.888c.127.083.28.112.431.112h1.519a.8.8 0 0 0 .457-.125c.291-.201.366-.422.287-.875c-.311-1.187-.708-2-1.496-3.5"/></g>`,

  // ── Tiempo ──
  date_range: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M16 2v4M8 2v4m5-2h-2C7.229 4 5.343 4 4.172 5.172S3 8.229 3 12v2c0 3.771 0 5.657 1.172 6.828S7.229 22 11 22h2c3.771 0 5.657 0 6.828-1.172S21 17.771 21 14v-2c0-3.771 0-5.657-1.172-6.828S16.771 4 13 4M3 10h18"/><path d="M12.126 14H12m.125 4H12m-4.376-4H7.5m.125 4H7.5m9.125-4H16.5m-4.25 0a.25.25 0 1 1-.5 0a.25.25 0 0 1 .5 0m0 4a.25.25 0 1 1-.5 0a.25.25 0 0 1 .5 0m-4.5-4a.25.25 0 1 1-.5 0a.25.25 0 0 1 .5 0m0 4a.25.25 0 1 1-.5 0a.25.25 0 0 1 .5 0m9-4a.25.25 0 1 1-.5 0a.25.25 0 0 1 .5 0"/></g>`,
  calendar_check: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M16 2v4M8 2v4m13 10v-4c0-3.771 0-5.657-1.172-6.828S16.771 4 13 4h-2C7.229 4 5.343 4 4.172 5.172S3 8.229 3 12v2c0 3.771 0 5.657 1.172 6.828S7.229 22 11 22h1M3 10h18"/><path d="M21 19.5h-6.5m2 2.5c-.506-.491-2.5-1.8-2.5-2.5s1.994-2.009 2.5-2.5"/></g>`,
  clock: `<g fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2 2"/></g>`,
  /* Vacaciones: la playa con su sombrilla. La "sombrilla" dibujada a mano
     de antes era un triángulo sobre una raya y parecía una montaña. */
  beach: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.513 8.502L7.494 15.5m5.41-12.523C10.52 1.95 7.849 2.637 6.21 4.488c-.522.59-.784.885-.673 1.389c.11.503.552.694 1.437 1.074l7.249 3.12c.887.382 1.33.573 1.764.302s.46-.668.512-1.462c.162-2.484-1.215-4.91-3.595-5.934M3 16.5s1.616-1 4.5-1C12 15.5 15 18 21 18M3 20.5h18"/>`,

  // ── El sistema por dentro ──
  dashboard: `<path fill="none" stroke="currentColor" stroke-linecap="square" stroke-linejoin="round" stroke-width="1.5" d="M13.69 19.457c-.19-.46-.19-1.042-.19-2.207s0-1.747.19-2.207a2.5 2.5 0 0 1 1.353-1.353c.46-.19 1.042-.19 2.207-.19s1.747 0 2.207.19a2.5 2.5 0 0 1 1.353 1.353c.19.46.19 1.042.19 2.207s0 1.747-.19 2.207a2.5 2.5 0 0 1-1.353 1.353c-.46.19-1.042.19-2.207.19s-1.747 0-2.207-.19a2.5 2.5 0 0 1-1.353-1.353Zm0-10.5c-.19-.46-.19-1.042-.19-2.207s0-1.747.19-2.207a2.5 2.5 0 0 1 1.353-1.353C15.503 3 16.085 3 17.25 3s1.747 0 2.207.19a2.5 2.5 0 0 1 1.353 1.353c.19.46.19 1.042.19 2.207s0 1.747-.19 2.207a2.5 2.5 0 0 1-1.353 1.353c-.46.19-1.042.19-2.207.19s-1.747 0-2.207-.19a2.5 2.5 0 0 1-1.353-1.353Zm-10.5 10.5C3 18.997 3 18.415 3 17.25s0-1.747.19-2.207a2.5 2.5 0 0 1 1.353-1.353c.46-.19 1.042-.19 2.207-.19s1.747 0 2.207.19a2.5 2.5 0 0 1 1.353 1.353c.19.46.19 1.042.19 2.207s0 1.747-.19 2.207a2.5 2.5 0 0 1-1.353 1.353c-.46.19-1.042.19-2.207.19s-1.747 0-2.207-.19a2.5 2.5 0 0 1-1.353-1.353Zm0-10.5C3 8.497 3 7.915 3 6.75s0-1.747.19-2.207A2.5 2.5 0 0 1 4.543 3.19C5.003 3 5.585 3 6.75 3s1.747 0 2.207.19a2.5 2.5 0 0 1 1.353 1.353c.19.46.19 1.042.19 2.207s0 1.747-.19 2.207a2.5 2.5 0 0 1-1.353 1.353c-.46.19-1.042.19-2.207.19s-1.747 0-2.207-.19A2.5 2.5 0 0 1 3.19 8.957Z"/>`,
  view_module: `<path fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.5" d="M3.889 9.663C4.393 10 5.096 10 6.5 10s2.107 0 2.611-.337a2 2 0 0 0 .552-.552C10 8.607 10 7.904 10 6.5s0-2.107-.337-2.611a2 2 0 0 0-.552-.552C8.607 3 7.904 3 6.5 3s-2.107 0-2.611.337a2 2 0 0 0-.552.552C3 4.393 3 5.096 3 6.5s0 2.107.337 2.611a2 2 0 0 0 .552.552Zm11 0C15.393 10 16.096 10 17.5 10s2.107 0 2.611-.337a2 2 0 0 0 .552-.552C21 8.607 21 7.904 21 6.5s0-2.107-.337-2.611a2 2 0 0 0-.552-.552C19.607 3 18.904 3 17.5 3s-2.107 0-2.611.337a2 2 0 0 0-.552.552C14 4.393 14 5.096 14 6.5s0 2.107.337 2.611a2 2 0 0 0 .552.552Zm-11 11C4.393 21 5.096 21 6.5 21s2.107 0 2.611-.337a2 2 0 0 0 .552-.552C10 19.607 10 18.904 10 17.5s0-2.107-.337-2.611a2 2 0 0 0-.552-.552C8.607 14 7.904 14 6.5 14s-2.107 0-2.611.337a2 2 0 0 0-.552.552C3 15.393 3 16.096 3 17.5s0 2.107.337 2.611a2 2 0 0 0 .552.552Zm11 0C15.393 21 16.096 21 17.5 21s2.107 0 2.611-.337c.218-.146.406-.334.552-.552C21 19.607 21 18.904 21 17.5s0-2.107-.337-2.611a2 2 0 0 0-.552-.552C19.607 14 18.904 14 17.5 14s-2.107 0-2.611.337a2 2 0 0 0-.552.552C14 15.393 14 16.096 14 17.5s0 2.107.337 2.611c.146.218.334.406.552.552Z"/>`,
  settings: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15.5 12a3.5 3.5 0 1 1-7 0a3.5 3.5 0 0 1 7 0Z"/><path stroke-linecap="round" d="M21.011 14.097c.522-.141.783-.212.886-.346c.103-.135.103-.351.103-.784v-1.934c0-.433 0-.65-.103-.784s-.364-.205-.886-.345c-1.95-.526-3.171-2.565-2.668-4.503c.139-.533.208-.8.142-.956s-.256-.264-.635-.479l-1.725-.98c-.372-.21-.558-.316-.725-.294s-.356.21-.733.587c-1.459 1.455-3.873 1.455-5.333 0c-.377-.376-.565-.564-.732-.587c-.167-.022-.353.083-.725.295l-1.725.979c-.38.215-.57.323-.635.48c-.066.155.003.422.141.955c.503 1.938-.718 3.977-2.669 4.503c-.522.14-.783.21-.886.345S2 10.6 2 11.033v1.934c0 .433 0 .65.103.784s.364.205.886.346c1.95.526 3.171 2.565 2.668 4.502c-.139.533-.208.8-.142.956s.256.264.635.48l1.725.978c.372.212.558.317.725.295s.356-.21.733-.587c1.46-1.457 3.876-1.457 5.336 0c.377.376.565.564.732.587c.167.022.353-.083.726-.295l1.724-.979c.38-.215.57-.323.635-.48s-.003-.422-.141-.955c-.504-1.937.716-3.976 2.666-4.502Z"/></g>`,
  build: `<g fill="none" stroke="currentColor"><path stroke-width="1.5" d="M20.358 13.357c-1.19 1.189-3.427 1.143-6.859 1.143a4 4 0 0 1-3.999-4c0-3.43-.046-5.67 1.143-6.859s1.715-1.14 6.984-1.14a.57.57 0 0 1 .406.973L15.32 6.187a1.763 1.763 0 1 0 2.492 2.494l2.714-2.712a.57.57 0 0 1 .974.405c0 5.268.048 5.794-1.142 6.983Z"/><path stroke-linecap="round" stroke-width="1.5" d="m13.5 14.5l-6.172 6.172a2.829 2.829 0 0 1-4-4L9.5 10.5"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.509 18.5H5.5"/></g>`,
  layers: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="m8.643 3.146l-1.705.788C4.313 5.147 3 5.754 3 6.75s1.313 1.603 3.938 2.816l1.705.788c1.652.764 2.478 1.146 3.357 1.146s1.705-.382 3.357-1.146l1.705-.788C19.687 8.353 21 7.746 21 6.75s-1.313-1.603-3.938-2.816l-1.705-.788C13.705 2.382 12.879 2 12 2s-1.705.382-3.357 1.146"/><path d="M20.788 11.097c.141.199.212.406.212.634c0 .982-1.313 1.58-3.938 2.776l-1.705.777c-1.652.753-2.478 1.13-3.357 1.13s-1.705-.377-3.357-1.13l-1.705-.777C4.313 13.311 3 12.713 3 11.731c0-.228.07-.435.212-.634"/><path d="M20.377 16.266c.415.331.623.661.623 1.052c0 .981-1.313 1.58-3.938 2.776l-1.705.777C13.705 21.624 12.879 22 12 22s-1.705-.376-3.357-1.13l-1.705-.776C4.313 18.898 3 18.299 3 17.318c0-.391.208-.72.623-1.052"/></g>`,
  search: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m17 17l4 4m-2-10a8 8 0 1 0-16 0a8 8 0 0 0 16 0"/>`,
  /* Los filtros: dos reguladores. El embudo de antes era una figura
     rellena y pesaba el doble que todo lo demás. */
  filter: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h3M3 17h6m9 0h3M15 7h6"/><path d="M6 7c0-.932 0-1.398.152-1.765a2 2 0 0 1 1.083-1.083C7.602 4 8.068 4 9 4s1.398 0 1.765.152a2 2 0 0 1 1.083 1.083C12 5.602 12 6.068 12 7s0 1.398-.152 1.765a2 2 0 0 1-1.083 1.083C10.398 10 9.932 10 9 10s-1.398 0-1.765-.152a2 2 0 0 1-1.083-1.083C6 8.398 6 7.932 6 7Zm6 10c0-.932 0-1.398.152-1.765a2 2 0 0 1 1.083-1.083C13.602 14 14.068 14 15 14s1.398 0 1.765.152a2 2 0 0 1 1.083 1.083C18 15.602 18 16.068 18 17s0 1.398-.152 1.765a2 2 0 0 1-1.083 1.083C16.398 20 15.932 20 15 20s-1.398 0-1.765-.152a2 2 0 0 1-1.083-1.083C12 18.398 12 17.932 12 17Z"/></g>`,
  upload: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 21h13.5c.232 0 .348 0 .446-.01a2 2 0 0 0 1.794-1.794c.01-.098.01-.214.01-.446s0-.348-.01-.446a2 2 0 0 0-1.794-1.794c-.098-.01-.214-.01-.446-.01h-1.69c-.228 0-.342 0-.451.012a2 2 0 0 0-1.03.427c-.087.069-.168.15-.329.311a4 4 0 0 1-.328.311a2 2 0 0 1-1.03.427c-.11.012-.224.012-.453.012h-2.878c-.229 0-.343 0-.452-.012a2 2 0 0 1-1.03-.427c-.087-.069-.168-.15-.329-.311s-.242-.242-.328-.311a2 2 0 0 0-1.03-.427c-.11-.012-.224-.012-.453-.012H5.25c-.232 0-.348 0-.446.01a2 2 0 0 0-1.794 1.794c-.01.098-.01.214-.01.446s0 .348.01.446a2 2 0 0 0 1.794 1.794c.098.01.214.01.446.01M16.5 7.5S13.186 3 12 3S7.5 7.5 7.5 7.5M12 4v11"/>`,
  download: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.95 12.182c.248.632-.442 1.39-1.823 2.908c-1.457 1.6-2.207 2.4-3.127 2.41c-.92-.01-1.67-.81-3.127-2.41c-1.38-1.517-2.071-2.276-1.823-2.908l.03-.069c.27-.571 1.165-.61 2.92-.613V5c0-.465 0-.697.051-.888a1.5 1.5 0 0 1 1.06-1.06C11.303 3 11.536 3 12 3s.697 0 .888.051a1.5 1.5 0 0 1 1.06 1.06C14 4.304 14 4.536 14 5v6.5c1.755.003 2.65.042 2.92.614q.016.033.03.068M5 21h14"/>`,
  phone: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.08 3.5h2.47c.54 0 1.02.35 1.18.87l.93 3.02a1.25 1.25 0 0 1-.36 1.29l-1.42 1.3a11.6 11.6 0 0 0 6.16 6.16l1.3-1.42a1.25 1.25 0 0 1 1.29-.36l3.02.93c.52.16.87.64.87 1.18v2.47c0 1.15-.95 2.08-2.1 2.04C10.6 20.68 3.32 13.4 3.04 5.6A2.03 2.03 0 0 1 5.08 3.5"/>`,
  mail: `<g fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.5"><path d="m2 6l6.913 3.917c2.549 1.444 3.625 1.444 6.174 0L22 6"/><path d="M2.016 13.476c.065 3.065.098 4.598 1.229 5.733c1.131 1.136 2.705 1.175 5.854 1.254c1.94.05 3.862.05 5.802 0c3.149-.079 4.723-.118 5.854-1.254c1.131-1.135 1.164-2.668 1.23-5.733c.02-.986.02-1.966 0-2.952c-.066-3.065-.099-4.598-1.23-5.733c-1.131-1.136-2.705-1.175-5.854-1.254a115 115 0 0 0-5.802 0c-3.149.079-4.723.118-5.854 1.254c-1.131 1.135-1.164 2.668-1.23 5.733a69 69 0 0 0 0 2.952Z"/></g>`,
  bell: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M20 18.501L18.349 7.934a6.426 6.426 0 0 0-12.698 0L4 18.501"/><path d="M20 18.5c0-1.657-3.582-3-8-3s-8 1.343-8 3s3.582 3 8 3s8-1.343 8-3m-7 0h-2"/></g>`,

  // ── Seguridad ──
  shield: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M18.709 3.495C16.817 2.554 14.5 2 12 2s-4.816.554-6.709 1.495c-.928.462-1.392.693-1.841 1.419S3 6.342 3 7.748v3.49c0 5.683 4.542 8.842 7.173 10.196c.734.377 1.1.566 1.827.566s1.093-.189 1.827-.566C16.457 20.08 21 16.92 21 11.237V7.748c0-1.406 0-2.108-.45-2.834s-.913-.957-1.841-1.419"/><path d="M9 11.5s1.408.252 2 2c0 0 1.5-3 4-4"/></g>`,
  admin_panel_settings: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M18.709 3.495C16.817 2.554 14.5 2 12 2s-4.816.554-6.709 1.495c-.928.462-1.392.693-1.841 1.419S3 6.342 3 7.748v3.49c0 5.683 4.542 8.842 7.173 10.196c.734.377 1.1.566 1.827.566s1.093-.189 1.827-.566C16.457 20.08 21 16.92 21 11.237V7.748c0-1.406 0-2.108-.45-2.834s-.913-.957-1.841-1.419"/><path d="M8.5 14.498a3.5 3.5 0 1 1 7 0M14 9a2 2 0 1 1-4 0a2 2 0 0 1 4 0"/></g>`,
  lock: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M12 14.5v3m1-3a1 1 0 1 1-2 0a1 1 0 0 1 2 0M16.5 9V6.5a4.5 4.5 0 1 0-9 0V9"/><path d="M4.268 18.845c.225 1.67 1.608 2.979 3.292 3.056c1.416.065 2.855.099 4.44.099s3.024-.034 4.44-.1c1.684-.076 3.067-1.385 3.292-3.055c.147-1.09.268-2.207.268-3.345s-.121-2.255-.268-3.345c-.225-1.67-1.608-2.979-3.292-3.056A95 95 0 0 0 12 9c-1.585 0-3.024.034-4.44.1c-1.684.076-3.067 1.385-3.292 3.055C4.12 13.245 4 14.362 4 15.5s.121 2.255.268 3.345"/></g>`,
  /* Firmar. Antes se usaba el lápiz de editar y se leía como "corregir esto",
     que es justo lo contrario de lo que hace: una firma no se edita. Esto es
     el trazo de una rúbrica sobre la línea de firma. */
  signature: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M22 12.634c-4 3.512-4.572-2.013-6.65-1.617c-2.35.447-3.85 5.428-2.35 5.428s-.5-5.945-2.5-3.89s-2.64 4.74-4.265 2.748C-1.5 5.813 5-1.15 8.163 3.457C10.165 6.373 6.5 16.977 2 22m7-1h10"/>`,

  // ── Estados: las cifras de la cabecera y los avisos ──
  check_circle: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12s4.477 10 10 10s10-4.477 10-10Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m8 12.5l2.5 2.5L16 9"/></g>`,
  pause_circle: `<g fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 9v6m5-6v6"/></g>`,
  warning: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M13.925 21h-3.85c-4.63 0-6.945 0-7.799-1.506c-.853-1.506.331-3.503 2.7-7.495L6.9 8.753C9.176 4.918 10.313 3 12 3s2.824 1.918 5.1 5.753L19.023 12c2.369 3.992 3.553 5.989 2.7 7.495C20.87 21 18.555 21 13.924 21M12 9v4"/><path d="M12.125 16.75H12m.25 0a.25.25 0 1 1-.5 0a.25.25 0 0 1 .5 0"/></g>`,
  info: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m.125-3.75H12m.25 0a.25.25 0 1 0-.5 0a.25.25 0 0 0 .5 0"/></g>`,
  alert_circle: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m.125 3.75H12m.25 0a.25.25 0 1 1-.5 0a.25.25 0 0 1 .5 0"/></g>`,
  circle_x: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12s4.477 10 10 10s10-4.477 10-10m-7 3L9 9m0 6l6-6"/>`,
  circle: `<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.5"/>`,

  /* ── Acciones de la interfaz ──
     Antes vivían dibujadas a mano en cada pantalla: 131 <svg> copiados, con
     13 tamaños distintos para el mismo "+". Desde acá salen todos iguales, y
     cambiar uno lo cambia en toda la app. No se ofrecen como ícono de módulo
     (ver SOLO_INTERFAZ). */
  plus: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12.001 5v14.002m7.001-7H5"/>`,
  close: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 6L6 18m12 0L6 6"/>`,
  check: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m5 14l3.5 3.5L19 6.5"/>`,
  edit: `<g fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.5"><path d="m16.425 4.605l.99-.99a2.1 2.1 0 0 1 2.97 2.97l-.99.99m-2.97-2.97l-6.66 6.66a3.96 3.96 0 0 0-1.041 1.84L8 16l2.896-.724a3.96 3.96 0 0 0 1.84-1.042l6.659-6.659m-2.97-2.97l2.97 2.97"/><path stroke-linecap="round" d="M19 13.5c0 3.288 0 4.931-.908 6.038a4 4 0 0 1-.554.554C16.43 21 14.788 21 11.5 21H11c-3.771 0-5.657 0-6.828-1.172S3 16.771 3 13v-.5c0-3.287 0-4.931.908-6.038q.25-.304.554-.554C5.57 5 7.212 5 10.5 5"/></g>`,
  trash: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="m19.5 5.5l-.62 10.025c-.158 2.561-.237 3.842-.88 4.763a4 4 0 0 1-1.2 1.128c-.957.584-2.24.584-4.806.584c-2.57 0-3.855 0-4.814-.585a4 4 0 0 1-1.2-1.13c-.642-.922-.72-2.205-.874-4.77L4.5 5.5M3 5.5h18m-4.944 0l-.683-1.408c-.453-.936-.68-1.403-1.071-1.695a2 2 0 0 0-.275-.172C13.594 2 13.074 2 12.035 2c-1.066 0-1.599 0-2.04.234a2 2 0 0 0-.278.18c-.395.303-.616.788-1.058 1.757L8.053 5.5m1.447 11v-6m5 6v-6"/>`,
  save: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linejoin="round" d="M8 22v-3c0-1.886 0-2.828.586-3.414S10.114 15 12 15s2.828 0 3.414.586S16 17.114 16 19v3"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 7h4"/><path d="M3 11.858c0-4.576 0-6.864 1.387-8.314a5 5 0 0 1 .157-.157C5.994 2 8.282 2 12.858 2c1.085 0 1.608.004 2.105.19c.479.178.88.512 1.682 1.181l2.196 1.83c1.062.885 1.592 1.327 1.876 1.932C21 7.737 21 8.428 21 9.81V13c0 3.75 0 5.625-.955 6.939a5 5 0 0 1-1.106 1.106C17.625 22 15.749 22 12 22s-5.625 0-6.939-.955a5 5 0 0 1-1.106-1.106C3 18.625 3 16.749 3 13z"/></g>`,
  eye: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21.544 11.045c.304.426.456.64.456.955c0 .316-.152.529-.456.955C20.178 14.871 16.689 19 12 19c-4.69 0-8.178-4.13-9.544-6.045C2.152 12.529 2 12.315 2 12c0-.316.152-.529.456-.955C3.822 9.129 7.311 5 12 5c4.69 0 8.178 4.13 9.544 6.045Z"/><path d="M15 12a3 3 0 1 0-6 0a3 3 0 0 0 6 0Z"/></g>`,
  eye_off: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path stroke-linejoin="round" d="M19.439 15.439a19.5 19.5 0 0 0 2.105-2.484c.304-.426.456-.64.456-.955c0-.316-.152-.529-.456-.955C20.178 9.129 16.689 5 12 5c-.908 0-1.77.155-2.582.418m-2.67 1.33c-2.017 1.36-3.506 3.195-4.292 4.297c-.304.426-.456.64-.456.955c0 .316.152.529.456.955C3.822 14.871 7.311 19 12 19c1.99 0 3.765-.744 5.253-1.747"/><path d="M9.858 10A2.929 2.929 0 1 0 14 14.142"/><path stroke-linejoin="round" d="m3 3l18 18"/></g>`,
  refresh: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.01 2v3.132a.314.314 0 0 1-.556.201A9.98 9.98 0 0 0 12 2C6.477 2 2 6.477 2 12s4.477 10 10 10s10-4.477 10-10"/>`,
  rotate_ccw: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M12 21A9 9 0 1 0 4.204 7.5"/><path d="M3 3v1.278c0 2.192 0 3.288.707 3.887c.708.6 1.789.42 3.95.059L9 8"/></g>`,
  arrow_left: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.5 12.002H19m-8 6s-6-4.419-6-6s6-6 6-6"/>`,
  arrow_right: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.5 12H5m8 6s6-4.419 6-6s-6-6-6-6"/>`,
  chevron_left: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 6s-6 4.419-6 6s6 6 6 6"/>`,
  chevron_right: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 6s6 4.419 6 6s-6 6-6 6"/>`,
  chevron_down: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9s-4.419 6-6 6s-6-6-6-6"/>`,
  file_down: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M4 7c.59.607 2.16 3 3 3s2.41-2.393 3-3M7 9V2"/><path d="M4 13v1.544c0 3.245 0 4.868.886 5.967a4 4 0 0 0 .603.603C6.59 22 8.211 22 11.456 22c.705 0 1.058 0 1.381-.114q.1-.036.197-.082c.31-.148.559-.397 1.058-.896l4.736-4.736c.579-.578.867-.867 1.02-1.235c.152-.368.152-.776.152-1.594V10c0-3.771 0-5.657-1.172-6.828S15.771 2 12 2m1 19.5V21c0-2.828 0-4.243.879-5.121C14.757 15 16.172 15 19 15h.5"/></g>`,
  file_up: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M4 12v2.544c0 3.245 0 4.868.886 5.967a4 4 0 0 0 .603.603C6.59 22 8.211 22 11.456 22c.705 0 1.058 0 1.381-.114q.1-.036.197-.082c.31-.148.559-.397 1.058-.896l4.736-4.736c.579-.578.867-.867 1.02-1.235c.152-.368.152-.776.152-1.594V10c0-3.771 0-5.657-1.172-6.828S15.771 2 12 2m1 19.5V21c0-2.828 0-4.243.879-5.121C14.757 15 16.172 15 19 15h.5"/><path d="M10 5c-.59-.607-2.16-3-3-3S4.59 4.393 4 5m3-2v7"/></g>`,
  /* Lo que se baja en Excel se ve como una hoja de cálculo, no como un
     documento cualquiera: "Descargar reporte" y "Descargar CV" usaban el
     mismo papel con flecha, y uno es un .xlsx y el otro un PDF. */
  excel: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13v-2.343c0-.818 0-1.226-.152-1.594c-.152-.367-.441-.657-1.02-1.235l-4.736-4.736c-.499-.499-.748-.748-1.058-.896a2 2 0 0 0-.197-.082C12.514 2 12.161 2 11.456 2c-3.245 0-4.868 0-5.967.886a4 4 0 0 0-.603.603C4 4.59 4 6.211 4 9.456V13m9-10.5V3c0 2.828 0 4.243.879 5.121C14.757 9 16.172 9 19 9h.5m-9 7v4c0 .943 0 1.414.293 1.707S11.557 22 12.5 22H14M4 16l2 3m0 0l2 3m-2-3l2-3m-2 3l-2 3m16-6h-2.5a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1H19a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-2.5"/>`,
  logout: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M18 18c0 .464 0 .697-.022.892a3.5 3.5 0 0 1-3.086 3.086C14.697 22 14.464 22 14 22h-3c-3.3 0-4.95 0-5.975-1.025S4 18.3 4 15V9c0-3.3 0-4.95 1.025-5.975S7.7 2 11 2h3c.464 0 .697 0 .892.022a3.5 3.5 0 0 1 3.086 3.086C18 5.303 18 5.536 18 6"/><path d="M8.076 11.118C8 11.302 8 11.535 8 12.001s0 .699.076.883a1 1 0 0 0 .541.54c.184.077.417.077.883.077h5c0 1.75.011 2.629.562 2.885q.03.015.063.026c.58.223 1.275-.398 2.666-1.64c1.467-1.312 2.2-1.987 2.209-2.815c-.009-.828-.742-1.503-2.21-2.814c-1.39-1.243-2.085-1.864-2.665-1.641l-.063.026c-.56.26-.562 1.165-.562 2.973h-5c-.466 0-.699 0-.883.076a1 1 0 0 0-.54.541"/></g>`,
  menu: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5h16M4 12h16M4 19h16"/>`,
  moon: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.5 14.078A8.557 8.557 0 0 1 9.922 2.5C5.668 3.497 2.5 7.315 2.5 11.873a9.627 9.627 0 0 0 9.627 9.627c4.558 0 8.376-3.168 9.373-7.422"/>`,
  sun: `<g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 12a5 5 0 1 1-10 0a5 5 0 0 1 10 0Z"/><path stroke-linecap="round" d="M12 2v1.5m0 17V22m7.07-2.929l-1.06-1.06M5.99 5.989L4.928 4.93M22 12h-1.5m-17 0H2m17.071-7.071l-1.06 1.06M5.99 18.011l-1.06 1.06"/></g>`,
  lightbulb: `<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.292 16a8 8 0 1 0-10.583 0M12 11v5m-3.5 3h7M10 22h4"/>`,
  camera: `<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path stroke-linejoin="round" d="M12.697 3.5h-1.394c-.715 0-1.072 0-1.392.112a2 2 0 0 0-.545.292c-.271.204-.47.501-.866 1.096h0c-.203.305-.502.753-.621.879a2 2 0 0 1-1.106.591c-.17.03-.353.03-.72.03c-.98 0-1.47 0-1.87.113a3 3 0 0 0-2.07 2.07C2 9.083 2 9.573 2 10.553V14.5c0 2.828 0 4.243.879 5.121S5.172 20.5 8 20.5h8c2.829 0 4.243 0 5.122-.879C22 18.743 22 17.328 22 14.5v-3.946c0-.98 0-1.47-.113-1.871a3 3 0 0 0-2.07-2.07c-.4-.113-.89-.113-1.87-.113c-.366 0-.55 0-.72-.03a2 2 0 0 1-1.105-.591c-.12-.126-.419-.574-.622-.879c-.396-.595-.594-.892-.865-1.096a2 2 0 0 0-.545-.292c-.32-.112-.678-.112-1.393-.112"/><path stroke-linejoin="round" d="M16 13a4 4 0 1 1-8 0a4 4 0 0 1 8 0"/><path d="M19.125 9.5H19m.25 0a.25.25 0 1 1-.5 0a.25.25 0 0 1 .5 0Z"/></g>`,
  cake: `<g fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.5"><path d="M13.5 4.5a1.5 1.5 0 0 1-3 0C10.5 3.672 12 2 12 2s1.5 1.672 1.5 2.5Z"/><path stroke-linecap="round" d="M12 6v3"/><path d="M17.667 14c1.564 0 2.833-1.12 2.833-2.5S19.232 9 17.667 9H6.333C4.77 9 3.5 10.12 3.5 11.5S4.769 14 6.333 14c1.371 0 2.571-.859 2.834-2c.262 1.141 1.462 2 2.833 2c1.37 0 2.57-.859 2.833-2c.263 1.141 1.463 2 2.834 2Z"/><path stroke-linecap="round" d="m5 14l.52 2.58c.525 2.597.788 3.895 1.676 4.658c.889.762 2.14.762 4.643.762h.322c2.503 0 3.754 0 4.643-.762c.889-.763 1.15-2.061 1.675-4.658L19 14"/></g>`,
};

/** Ícono que se usa cuando no hay ninguna coincidencia. */
const ICONO_POR_DEFECTO = 'circle';

/**
 * Los íconos de acción (cerrar, agregar, editar…). Están en el catálogo para
 * que toda la app los pinte igual, pero no tienen sentido como ícono de un
 * módulo del menú: no se ofrecen en el selector del admin.
 */
const SOLO_INTERFAZ = new Set([
  'plus', 'close', 'check', 'edit', 'trash', 'save', 'eye', 'eye_off', 'refresh', 'rotate_ccw',
  'arrow_left', 'arrow_right', 'chevron_left', 'chevron_right', 'chevron_down',
  'file_down', 'file_up', 'excel', 'logout', 'menu', 'moon', 'sun', 'lightbulb', 'camera',
  'info', 'alert_circle', 'circle_x', 'user_off',
]);

/** Las claves para el selector de ícono de módulos del admin, ordenadas. */
export const CLAVES_ICONO: readonly string[] = Object.keys(ICON_MAP)
  .filter((clave) => !SOLO_INTERFAZ.has(clave))
  .sort();

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
