const WebSocket=require('ws'),http=require('http');
const get=(p)=>new Promise((r,j)=>{http.get({host:'127.0.0.1',port:9222,path:p},(s)=>{let d='';s.on('data',c=>d+=c);s.on('end',()=>r(JSON.parse(d)))}).on('error',j)});
(async()=>{
 const tab=(await get('/json')).find(t=>t.type==='page');
 const ws=new WebSocket(tab.webSocketDebuggerUrl,{perMessageDeflate:false});let id=0;const pend=new Map();
 const consola=[];
 const send=(m,q={})=>new Promise(r=>{const i=++id;pend.set(i,r);ws.send(JSON.stringify({id:i,method:m,params:q}))});
 await new Promise(r=>ws.on('open',r));
 ws.on('message',m=>{const x=JSON.parse(m);
   if(x.id&&pend.has(x.id)){pend.get(x.id)(x.result);pend.delete(x.id)}
   if(x.method==='Runtime.consoleAPICalled') consola.push(x.params.type+': '+x.params.args.map(a=>a.value??a.description??'').join(' ').slice(0,260));
   if(x.method==='Runtime.exceptionThrown') consola.push('EXCEPCION: '+(x.params.exceptionDetails.exception?.description||x.params.exceptionDetails.text).slice(0,260));
   if(x.method==='Network.responseReceived' && /\/api\//.test(x.params.response.url)) consola.push('red: '+x.params.response.status+' '+x.params.response.url.replace(/^.*\/api/,'/api'));
 });
 await send('Page.enable');await send('Runtime.enable');await send('Network.enable');
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,awaitPromise:true,returnByValue:true})).result.value;
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 // Recargar /terminos desde cero, con la sesión de Admin ya firmada
 await send('Page.navigate',{url:'http://localhost:4200/terminos'});
 await wait(5000);
 console.log('  al cargar /terminos:', await ev(`(()=>{
   const el=document.querySelector('app-cambiar-clave');
   const c=el && window.ng ? ng.getComponent(el) : null;
   return JSON.stringify({ruta:location.pathname, hayComponente:!!c, soloTerminos:c?.soloTerminos, paso:c?.paso, cargando:c?.cargandoTerminos, firmados:c?.terminos?.firmados, rutaDestino: c ? ng.getInjector(el).get(ng.getComponent(el).constructor)?.x : null});
 })()`));
 console.log('  consola/red:', JSON.stringify(consola, null, 1));
 ws.close();
})();
