const WebSocket=require('ws'),http=require('http');
const get=(p)=>new Promise((r,j)=>{http.get({host:'127.0.0.1',port:9222,path:p},(s)=>{let d='';s.on('data',c=>d+=c);s.on('end',()=>r(JSON.parse(d)))}).on('error',j)});
(async()=>{
 const tab=(await get('/json')).find(t=>t.type==='page' && /localhost:4200/.test(t.url));
 const ws=new WebSocket(tab.webSocketDebuggerUrl,{perMessageDeflate:false,maxPayload:256*1024*1024});let id=0;const pend=new Map();
 const eventos=[];
 const send=(m,q={})=>new Promise(r=>{const i=++id;pend.set(i,r);ws.send(JSON.stringify({id:i,method:m,params:q}))});
 await new Promise(r=>ws.on('open',r));
 ws.on('message',m=>{const x=JSON.parse(m);
   if(x.id&&pend.has(x.id)){pend.get(x.id)(x.result);pend.delete(x.id)}
   if(x.method==='Runtime.consoleAPICalled' && (x.params.type==='error'||x.params.type==='warning')) eventos.push('CONSOLE.'+x.params.type.toUpperCase()+': '+x.params.args.map(a=>a.value??a.description??'').join(' ').slice(0,300));
   if(x.method==='Runtime.exceptionThrown') eventos.push('EXCEPCION: '+(x.params.exceptionDetails.exception?.description||x.params.exceptionDetails.text).slice(0,400));
   if(x.method==='Network.responseReceived' && x.params.response.status>=400) eventos.push('HTTP '+x.params.response.status+' '+x.params.response.url.slice(0,150));
 });
 await send('Page.enable');await send('Runtime.enable');await send('Network.enable');
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,awaitPromise:true,returnByValue:true})).result;
 const wait=ms=>new Promise(r=>setTimeout(r,ms));

 await ev('localStorage.clear()');
 await send('Page.navigate',{url:'http://localhost:4200/login'}); await wait(3000);
 await ev(`(()=>{
   const set=(el,v)=>{const s=Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype,'value').set;s.call(el,v);el.dispatchEvent(new Event('input',{bubbles:true}))};
   const ins=[...document.querySelectorAll('input')];
   set(ins.find(i=>i.type==='email'||/correo/i.test(i.placeholder||'')),'admin@colegio.com');
   set(ins.find(i=>i.type==='password'),'admin123');
   [...document.querySelectorAll('button')].find(b=>/ingresar/i.test(b.textContent)).click();
 })()`);
 await wait(4000);
 console.log('tras login:', (await ev('location.pathname')).value);

 eventos.length = 0;
 await send('Page.navigate',{url:'http://localhost:4200/inicio/auditoria'});
 await wait(5000);
 const estado = await ev(`JSON.stringify({
   ruta: location.pathname,
   filas: document.querySelectorAll('.data-table tbody tr').length,
   hayComponenteAuditoria: !!document.querySelector('app-auditoria-list'),
   cabeceras: [...document.querySelectorAll('.data-table thead th')].map(th=>th.textContent.trim()),
   primeraFila: (document.querySelector('.data-table tbody tr')||{}).innerText,
   tituloPagina: (document.querySelector('h1')||{}).textContent
 })`);
 console.log('estado /inicio/auditoria:', estado.value ?? JSON.stringify(estado));
 console.log('eventos:', eventos.length ? JSON.stringify(eventos, null, 1) : 'ninguno');
 ws.close();
})().catch(e=>{console.error('fallo script:', e); process.exit(1)});
