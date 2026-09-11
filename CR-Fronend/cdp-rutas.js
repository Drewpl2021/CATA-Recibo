const WebSocket=require('ws'),http=require('http');
const get=(p)=>new Promise((r,j)=>{http.get({host:'127.0.0.1',port:9222,path:p},(s)=>{let d='';s.on('data',c=>d+=c);s.on('end',()=>r(JSON.parse(d)))}).on('error',j)});
(async()=>{
 const tab=(await get('/json')).find(t=>t.type==='page');
 const ws=new WebSocket(tab.webSocketDebuggerUrl,{perMessageDeflate:false});let id=0;const pend=new Map();
 const consola=[];
 const send=(m,q={})=>new Promise(r=>{const i=++id;pend.set(i,r);ws.send(JSON.stringify({id:i,method:m,params:q}))});
 await new Promise(r=>ws.on('open',r));
 ws.on('message',m=>{const x=JSON.parse(m);if(x.id&&pend.has(x.id)){pend.get(x.id)(x.result);pend.delete(x.id)}
   if(x.method==='Runtime.consoleAPICalled' && x.params.type!=='debug') consola.push(x.params.type+': '+x.params.args.map(a=>a.value??a.description??'').join(' ').slice(0,160));});
 await send('Page.enable');await send('Runtime.enable');
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,awaitPromise:true,returnByValue:true})).result.value;
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 console.log('  sesión guardada:', await ev(`(()=>{const u=JSON.parse(localStorage.getItem('auth_user')||'{}');return JSON.stringify({rol:u.rol, terminos:u.terminos_estado, debeCambiar:u.debe_cambiar_password})})()`));
 for (const r of ['/inicio/usuarios', '/inicio/auditoria', '/inicio/modulos']) {
   consola.length = 0;
   await send('Page.navigate',{url:'http://localhost:4200'+r}); await wait(4500);
   const fin = await ev('location.pathname');
   const trozo = await ev(`performance.getEntriesByType('resource').map(e=>e.name).filter(n=>/auditoria|usuarios-list|modulos-list/.test(n)).map(n=>n.split('/').pop()).join(', ') || '(ningún trozo de esa pantalla)'`);
   console.log('  ' + r.padEnd(20) + '→ ' + fin.padEnd(20) + ' | cargó: ' + trozo + (consola.length ? ' | consola: ' + JSON.stringify(consola.slice(0,2)) : ''));
 }
 ws.close();
})();
