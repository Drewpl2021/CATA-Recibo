const WebSocket=require('ws'),fs=require('fs'),http=require('http');
const get=(p)=>new Promise((r,j)=>{http.get({host:'127.0.0.1',port:9222,path:p},(s)=>{let d='';s.on('data',c=>d+=c);s.on('end',()=>r(JSON.parse(d)))}).on('error',j)});
(async()=>{
 const tab=(await get('/json')).find(t=>t.type==='page');
 const ws=new WebSocket(tab.webSocketDebuggerUrl,{perMessageDeflate:false,maxPayload:256*1024*1024});let id=0;const pend=new Map();
 const errores=[];
 const send=(m,q={})=>new Promise(r=>{const i=++id;pend.set(i,r);ws.send(JSON.stringify({id:i,method:m,params:q}))});
 await new Promise(r=>ws.on('open',r));
 ws.on('message',m=>{const x=JSON.parse(m);if(x.id&&pend.has(x.id)){pend.get(x.id)(x.result);pend.delete(x.id)}
   if(x.method==='Runtime.consoleAPICalled'&&x.params.type==='error') errores.push(x.params.args.map(a=>a.value??a.description??'').join(' ').slice(0,150));});
 await send('Page.enable');await send('Runtime.enable');
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,awaitPromise:true,returnByValue:true})).result.value;
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 await send('Emulation.setDeviceMetricsOverride',{width:1500,height:1000,deviceScaleFactor:1,mobile:false});
 // Página recién cargada: el bundle que haya ahora en el servidor de desarrollo
 await send('Page.navigate',{url:'http://localhost:4200/inicio/dashboard'}); await wait(1500);
 await send('Page.reload',{ignoreCache:true}); await wait(6000);
 console.log('  sesión:', await ev('location.pathname'));
 console.log('  menú según la API:', await ev(`(async()=>{
   const t = localStorage.getItem('auth_token');
   const r = await fetch('http://127.0.0.1:8000/api/mis-modulos',{headers:{Authorization:'Bearer '+t,Accept:'application/json'}});
   const d = await r.json();
   const conf = (d.data||[]).find(p=>/Configuraci/.test(p.nombre));
   return r.status + ' | Configuración: ' + (conf ? conf.modulos.map(m=>m.nombre).join(', ') : 'no está');
 })()`));
 await send('Page.navigate',{url:'http://localhost:4200/inicio/auditoria'}); await wait(5500);
 console.log('  pantalla:', await ev(`JSON.stringify({ruta:location.pathname, filas:document.querySelectorAll('.data-table tbody tr').length, editar:document.querySelectorAll('.data-table [aria-label*=Editar],.data-table [aria-label*=Eliminar]').length})`));
 console.log(await ev(`[...document.querySelectorAll('.data-table tbody tr')].slice(0,6).map(tr=>'    '+[...tr.querySelectorAll('td')].map(td=>td.innerText.trim().split(String.fromCharCode(10)).join(' ')).join(' | ').slice(0,200)).join(String.fromCharCode(10))`));
 await ev(`(()=>{const s=document.querySelector('#filtroEntidad'); if(!s) return; s.value='usuario'; s.dispatchEvent(new Event('change',{bubbles:true}))})()`); await wait(3000);
 console.log('  filtro "Cuentas de acceso", 1ª fila:', await ev(`(document.querySelector('.data-table tbody tr')||{}).innerText?.split(String.fromCharCode(10)).join(' | ').slice(0,200) || '(vacía)'`));
 fs.writeFileSync('auditoria.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
 console.log('  errores en consola:', errores.length ? JSON.stringify(errores.slice(0,3)) : 'ninguno');
 ws.close();
})();
