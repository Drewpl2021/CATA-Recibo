const WebSocket=require('ws'),http=require('http');
const get=(p)=>new Promise((r,j)=>{http.get({host:'127.0.0.1',port:9222,path:p},(s)=>{let d='';s.on('data',c=>d+=c);s.on('end',()=>r(JSON.parse(d)))}).on('error',j)});
(async()=>{
 const tab=(await get('/json')).find(t=>t.type==='page');
 const ws=new WebSocket(tab.webSocketDebuggerUrl,{perMessageDeflate:false});let id=0;const pend=new Map();
 const red=[];
 const send=(m,q={})=>new Promise(r=>{const i=++id;pend.set(i,r);ws.send(JSON.stringify({id:i,method:m,params:q}))});
 await new Promise(r=>ws.on('open',r));
 ws.on('message',m=>{const x=JSON.parse(m);
   if(x.id&&pend.has(x.id)){pend.get(x.id)(x.result);pend.delete(x.id)}
   if(x.method==='Network.responseReceived' && /\/api\//.test(x.params.response.url)) red.push(x.params.response.status+' '+x.params.response.url.replace(/^.*\/api/,'/api'));
   if(x.method==='Runtime.exceptionThrown') red.push('EXCEPCION JS: '+(x.params.exceptionDetails.exception?.description||x.params.exceptionDetails.text).slice(0,200));
 });
 await send('Page.enable');await send('Runtime.enable');await send('Network.enable');
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,awaitPromise:true,returnByValue:true})).result.value;
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 console.log('  antes del clic:', await ev(`JSON.stringify({ruta:location.pathname, casilla:document.querySelector('.terminos__acepto input')?.checked, boton:[...document.querySelectorAll('button')].find(b=>/Aceptar y continuar/.test(b.textContent))?.disabled, estadoGuardado: JSON.parse(localStorage.getItem('auth_user')||'{}').terminos_estado})`));
 await ev(`(()=>{const c=document.querySelector('.terminos__acepto input'); if(!c.checked) c.click(); return 1})()`);
 await wait(600);
 console.log('  casilla marcada, botón:', await ev(`[...document.querySelectorAll('button')].find(b=>/Aceptar y continuar/.test(b.textContent))?.disabled ? 'BLOQUEADO' : 'activo'`));
 await ev(`[...document.querySelectorAll('button')].find(b=>/Aceptar y continuar/.test(b.textContent)).click()`);
 await wait(5000);
 console.log('  red:', JSON.stringify(red));
 console.log('  después:', await ev(`JSON.stringify({ruta:location.pathname, estadoGuardado: JSON.parse(localStorage.getItem('auth_user')||'{}').terminos_estado, avisos:[...document.querySelectorAll('[class*=toast]')].map(t=>t.innerText.trim().split(String.fromCharCode(10)).join(' / ')).filter(Boolean).slice(0,3)})`));
 ws.close();
})();
