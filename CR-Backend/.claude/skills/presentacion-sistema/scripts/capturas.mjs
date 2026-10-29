// Capturas para la presentación: cada pantalla del sistema, con los datos de
// personas difuminados (nombres, DNI, montos individuales).
//
//   MSYS_NO_PATHCONV=1 node capturas.mjs <token> [desde]
//
// Necesita Chrome con --remote-debugging-port=9222, el sistema en
// http://localhost:8081 y me.json (la respuesta de /api/me del usuario
// temporal, ver usuario-temporal.php). En Git Bash, MSYS_NO_PATHCONV=1 es
// obligatorio: si no, «/inicio/...» se convierte en una ruta de Windows y no
// se captura ninguna pantalla. Los ids de la lista P (una planilla, un
// trabajador) son de una base concreta: cambiarlos por los del momento.
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
mkdirSync('ppt/cap', { recursive: true });
const [,, token, desde = '0'] = process.argv;
const me = JSON.parse(readFileSync('me.json', 'utf8'));
const usuario = { ...me, rol: me.rol?.nombre ?? me.rol, debe_cambiar_password: false, terminos_estado: 'firmado' };
const pag = (await (await fetch('http://localhost:9222/json')).json()).find((t) => t.type === 'page');
const ws = new WebSocket(pag.webSocketDebuggerUrl); await new Promise((r) => ws.addEventListener('open', r, { once: true }));
let id = 0; const p = new Map(); ws.addEventListener('message', (e) => { const m = JSON.parse(e.data); if (m.id && p.has(m.id)) { p.get(m.id)(m); p.delete(m.id); } });
const cdp = (method, params = {}) => new Promise((r) => { const i = ++id; p.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
const ev = async (x) => (await cdp('Runtime.evaluate', { expression: x, awaitPromise: true, returnByValue: true })).result?.result?.value;
const z = (ms) => new Promise((r) => setTimeout(r, ms));

const CABECERA = `(() => { const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n;
  while ((n = w.nextNode())) { n.nodeValue = n.nodeValue.replace(/Buenas (tardes|noches|días), Prueba/, 'Buenas tardes').replace(/^\\s*Prueba( Fotos)?\\s*$/, 'Recursos Humanos'); }
  document.querySelectorAll('.avatar').forEach((a) => { if (a.textContent.trim() === 'PF' || a.textContent.trim() === 'PR') a.textContent = 'RH'; }); })()`;
// Difumina lo personal de las tablas: todo menos estados, acciones y casillas.
const TABLA = `document.querySelectorAll('app-data-table tbody td').forEach((td) => {
  if (td.classList.contains('celda-acciones') || td.classList.contains('celda-marca') || td.querySelector('.status-badge') || td.classList.contains('data-table-empty')) return;
  td.style.filter = 'blur(6px)'; })`;
const BLUR = (sel) => `document.querySelectorAll(${JSON.stringify(sel)}).forEach((e) => e.style.filter = 'blur(6px)')`;

const P = [
  ['panel', '/inicio/dashboard', BLUR('.cumple__lista, .cumple__proximo, .cumple__hoy, .vence')],
  ['empleados', '/inicio/empleados', TABLA],
  ['empleado-nuevo', '/inicio/empleados/nuevo', ''],
  ['empleados-importar', '/inicio/empleados/importar', ''],
  ['contratos', '/inicio/contratos', TABLA],
  ['planillas', '/inicio/planillas', TABLA],
  ['planilla-corrida', '/inicio/planillas/corrida/01a1196b-b2ec-73fa-a210-426d6e43c5f6', TABLA],
  ['importar-plame', '/inicio/planillas/importar-plame', ''],
  ['emision', '/inicio/emision-boleta', TABLA],
  ['documentos', '/inicio/documentos', TABLA],
  ['expediente', '/inicio/documentos/01a1172e-036c-70c5-808c-bf3a7262afd9', BLUR('h1, .page-header p, .expediente__persona, .ficha-persona, dd, app-data-table tbody td, .exp-persona, app-avatar')],
  ['vacaciones', '/inicio/vacaciones', TABLA],
  ['renta', '/inicio/renta-5ta', TABLA],
  ['renta-hoja', '/inicio/renta-5ta/01a1172e-036c-70c5-808c-bf3a7262afd9?anio=2026', BLUR('h1, .page-header p, app-avatar')],
  ['cts', '/inicio/cts', TABLA],
  ['gratificaciones', '/inicio/gratificaciones', TABLA],
  ['portal', '/inicio/portal-web', ''],
  ['portal-portada', '/inicio/portal-web/portada', ''],
  ['ajustes', '/inicio/ajustes', ''],
  ['conceptos', '/inicio/conceptos-pago', ''],
  ['roles', '/inicio/roles', ''],
  ['auditoria', '/inicio/auditoria', TABLA],
  ['usuarios', '/inicio/usuarios', TABLA],
];

await cdp('Page.enable');
await cdp('Emulation.setDeviceMetricsOverride', { width: 1440, height: 860, deviceScaleFactor: 2, mobile: false });
await cdp('Page.navigate', { url: 'http://localhost:8081/login' }); await z(1500);
await ev(`localStorage.clear(); sessionStorage.clear(); localStorage.setItem('app-theme','light'); localStorage.setItem('auth_token', ${JSON.stringify(token)}); localStorage.setItem('auth_user', ${JSON.stringify(JSON.stringify(usuario))});`);
for (const [nombre, ruta, js] of P.slice(+desde)) {
  await cdp('Page.navigate', { url: 'http://localhost:8081' + ruta }); await z(6500);
  await ev(CABECERA); if (js) await ev(js); await z(500);
  const s = await cdp('Page.captureScreenshot', { format: 'png' });
  writeFileSync(`ppt/cap/${nombre}.png`, Buffer.from(s.result.data, 'base64'));
  console.log(nombre, await ev('location.pathname'));
}
// Las públicas, sin sesión.
await ev('localStorage.clear(); sessionStorage.clear();');
for (const [nombre, ruta] of [['login', '/login'], ['privacidad', '/privacidad']]) {
  await cdp('Page.navigate', { url: 'http://localhost:8081' + ruta }); await z(4000);
  const s = await cdp('Page.captureScreenshot', { format: 'png' });
  writeFileSync(`ppt/cap/${nombre}.png`, Buffer.from(s.result.data, 'base64'));
  console.log(nombre, await ev('location.pathname'));
}
// El teléfono: el Panel en 390 px.
await cdp('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 3, mobile: true });
await cdp('Page.navigate', { url: 'http://localhost:8081/login' }); await z(1200);
await ev(`localStorage.setItem('app-theme','light'); localStorage.setItem('auth_token', ${JSON.stringify(token)}); localStorage.setItem('auth_user', ${JSON.stringify(JSON.stringify(usuario))});`);
await cdp('Page.navigate', { url: 'http://localhost:8081/inicio/dashboard' }); await z(6500); await ev(CABECERA);
let s = await cdp('Page.captureScreenshot', { format: 'png' }); writeFileSync('ppt/cap/movil-panel.png', Buffer.from(s.result.data, 'base64'));
await cdp('Emulation.clearDeviceMetricsOverride'); ws.close();
console.log('fin');
