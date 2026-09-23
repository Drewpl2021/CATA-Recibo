/**
 * Convierte los diagramas .mmd en imagen (PNG y SVG).
 *
 *     node docs/herramientas/dibujar-modelo.js                 (todos)
 *     node docs/herramientas/dibujar-modelo.js modelo-planilla (uno)
 *
 * Usa el Chrome que ya está instalado y Mermaid desde su CDN: no hace falta
 * instalar nada. Si Chrome está en otro sitio, se le dice con la variable
 * CHROME, por ejemplo:
 *
 *     CHROME="C:/ruta/chrome.exe" node docs/herramientas/dibujar-modelo.js
 *
 * El PNG sale al doble de resolución para que se vea nítido pegado en un
 * documento; el SVG se puede escalar sin perder nada y editar en Inkscape.
 */
const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const DOCS = path.resolve(__dirname, '..');
const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PUERTO = 9455;
// El perfil de Chrome va al temporal del sistema: si queda algo suelto, no
// ensucia la carpeta del proyecto.
const PERFIL = path.join(os.tmpdir(), 'cata-modelo-' + Date.now());
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const cuales = process.argv[2]
  ? [process.argv[2]]
  : fs.readdirSync(DOCS).filter((f) => f.endsWith('.mmd')).map((f) => f.replace('.mmd', ''));

const pagina = (diagrama) => `<!doctype html><html><head><meta charset="utf-8">
<style>body { margin: 0; padding: 28px; background: #fff; font-family: Segoe UI, sans-serif; }
#d { display: inline-block; }</style></head><body>
<div id="d" class="mermaid">${diagrama.replace(/</g, '&lt;')}</div>
<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
<script>
  mermaid.initialize({ startOnLoad: true, theme: 'base', securityLevel: 'loose',
    themeVariables: {
      primaryColor: '#E7EEF9', primaryBorderColor: '#1B4282', primaryTextColor: '#131A2B',
      lineColor: '#3B72C4', fontSize: '15px', fontFamily: 'Segoe UI, sans-serif',
      attributeBackgroundColorOdd: '#FFFFFF', attributeBackgroundColorEven: '#F7F9FC',
    },
    er: { useMaxWidth: false, entityPadding: 12, minEntityWidth: 140 },
  });
</script></body></html>`;

const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${PUERTO}`,
  `--user-data-dir=${PERFIL}`, '--no-first-run',
  '--window-size=2400,1800', 'about:blank'], { stdio: 'ignore' });

(async () => {
  try {
    let objetivos;
    for (let i = 0; i < 40 && !objetivos; i++) {
      try { objetivos = await fetch(`http://127.0.0.1:${PUERTO}/json`).then((x) => x.json()); }
      catch { await esperar(250); }
    }
    const ws = new WebSocket(objetivos.find((t) => t.type === 'page').webSocketDebuggerUrl);
    await new Promise((res) => ws.addEventListener('open', res, { once: true }));

    let n = 0;
    const espera = new Map();
    ws.addEventListener('message', (ev) => {
      const m = JSON.parse(ev.data);
      if (m.id && espera.has(m.id)) { espera.get(m.id)(m); espera.delete(m.id); }
    });
    const cdp = (method, params = {}) => new Promise((res) => {
      const id = ++n; espera.set(id, res); ws.send(JSON.stringify({ id, method, params }));
    });
    const valor = async (e) => (await cdp('Runtime.evaluate',
      { expression: e, returnByValue: true, awaitPromise: true })).result?.result?.value;
    const hasta = async (c, ms = 60000) => {
      const fin = Date.now() + ms;
      while (Date.now() < fin) { if (await valor(c)) return true; await esperar(200); }
      return false;
    };

    await cdp('Runtime.enable'); await cdp('Page.enable');

    for (const base of cuales) {
      const html = path.join(DOCS, '.' + base + '.html');
      fs.writeFileSync(html, pagina(fs.readFileSync(path.join(DOCS, base + '.mmd'), 'utf-8')));
      await cdp('Page.navigate', { url: 'file:///' + html.replaceAll(String.fromCharCode(92), '/') });

      if (!await hasta("!!document.querySelector('#d svg')", 60000)) {
        console.log(base + ': Mermaid no pudo dibujarlo');
        continue;
      }
      await esperar(1000);

      const caja = JSON.parse(await valor(`(() => { const b = document.querySelector('#d svg').getBoundingClientRect();
        return JSON.stringify({ w: Math.ceil(b.width), h: Math.ceil(b.height) }); })()`));

      fs.writeFileSync(path.join(DOCS, base + '.svg'),
        '<?xml version="1.0" encoding="UTF-8"?>\n' + await valor("document.querySelector('#d svg').outerHTML"));

      await cdp('Emulation.setDeviceMetricsOverride',
        { width: caja.w + 56, height: caja.h + 56, deviceScaleFactor: 2, mobile: false });
      await esperar(600);
      const foto = await cdp('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
      fs.writeFileSync(path.join(DOCS, base + '.png'), Buffer.from(foto.result.data, 'base64'));
      fs.unlinkSync(html);

      console.log(`${base}: ${caja.w}x${caja.h} · PNG y SVG listos`);
    }
  } catch (e) {
    console.log('ERROR', e.stack);
  } finally {
    chrome.kill();
    // Chrome suelta sus archivos con retraso; si todavia los tiene, se queda
    // en el temporal y lo limpia el sistema.
    try { fs.rmSync(PERFIL, { recursive: true, force: true }); } catch { /* ya lo limpiara el sistema */ }
  }
})();
