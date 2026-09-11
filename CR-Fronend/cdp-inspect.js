const WebSocket=require('ws'),http=require('http');
const get=(p)=>new Promise((r,j)=>{http.get({host:'127.0.0.1',port:9222,path:p},(s)=>{let d='';s.on('data',c=>d+=c);s.on('end',()=>r(JSON.parse(d)))}).on('error',j)});
(async()=>{
 const tab=(await get('/json')).find(t=>t.type==='page' && /localhost:4200/.test(t.url));
 const ws=new WebSocket(tab.webSocketDebuggerUrl,{perMessageDeflate:false,maxPayload:256*1024*1024});let id=0;const pend=new Map();
 const consola=[];
 const send=(m,q={})=>new Promise(r=>{const i=++id;pend.set(i,r);ws.send(JSON.stringify({id:i,method:m,params:q}))});
 await new Promise(r=>ws.on('open',r));
 ws.on('message',m=>{const x=JSON.parse(m);
   if(x.id&&pend.has(x.id)){pend.get(x.id)(x.result);pend.delete(x.id)}
   if(x.method==='Runtime.consoleAPICalled') consola.push(x.params.type+': '+x.params.args.map(a=>a.value??a.description??'').join(' ').slice(0,500));
   if(x.method==='Runtime.exceptionThrown') consola.push('EXCEPCION: '+(x.params.exceptionDetails.exception?.description||x.params.exceptionDetails.text).slice(0,500));
 });
 await send('Page.enable');await send('Runtime.enable');
 const ev=async e=>(await send('Runtime.evaluate',{expression:e,awaitPromise:true,returnByValue:true})).result;
 console.log('URL actual:', tab.url);
 console.log('estado:', JSON.stringify((await ev(`JSON.stringify({ruta:location.pathname, titulo:document.title, overlay: !!document.querySelector('#webpack-dev-server-client-overlay, ng-dev-overlay, .cdk-overlay-container .error, [class*=error-overlay]'), bodyLen: document.body.innerText.length })`)).value));
 console.log('primeros 1500 caracteres del body:');
 console.log((await ev('document.body.innerText.slice(0,1500)')).value);
 await new Promise(r=>setTimeout(r,500));
 console.log('consola capturada tras conectar:', JSON.stringify(consola.slice(0,20)));
 ws.close();
})().catch(e=>{console.error('fallo script:', e); process.exit(1)});
