(function(){'use strict';
function init(){
 const qty=document.getElementById('mdg-yb-qty'),total=document.getElementById('mdg-yb-total');
 if(qty&&qty.form&&qty.form.querySelector('[name="mdg_yb_buy"]')){const show=()=>{const n=Number(qty.value);total.textContent=Number.isInteger(n)&&n>=1&&n<=50?(n*100).toLocaleString('tr-TR')+' TL':'Kişi sayısını kontrol edin';};qty.addEventListener('input',show);document.querySelectorAll('[data-yb-step]').forEach(b=>b.addEventListener('click',()=>{qty.value=Math.max(1,Math.min(50,(Number(qty.value)||1)+Number(b.dataset.ybStep)));show();}));show();}
 const form=document.getElementById('mdg-yb-lookup'),result=document.getElementById('mdg-yb-result');
 if(!form)return;
 async function api(path,data){const r=await fetch(MDGYB.api+path,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':MDGYB.nonce},body:JSON.stringify(data)});const body=await r.json();if(!r.ok)throw new Error(body.message||'İşlem tamamlanamadı.');return body;}
 function text(tag,value,parent){const e=document.createElement(tag);e.textContent=value;parent.appendChild(e);return e;}
 function error(e){result.replaceChildren();const p=text('p',e.message,result);p.className='mdg-yb-error';}
 function render(data){result.replaceChildren();const box=document.createElement('div');box.className=data.paid&&data.remaining>0?'mdg-yb-ok':'mdg-yb-error';result.appendChild(box);text('h3',data.paid?(data.remaining>0?'Ödendi · Kullanılabilir':'Hakların tamamı kullanılmış'):'Ödeme / iade kontrolü gerekli',box);text('p','Sipariş #'+data.order_id+' · '+data.amount+' · '+data.paid_at,box);text('p','Toplam hak: '+data.qty+' · Kullanılan: '+data.used+' · Kalan: '+data.remaining,box);
 if(data.paid&&data.remaining>0){const label=text('label','Şimdi kaç kişiye hizmet verildi?',box);const input=document.createElement('input');input.type='number';input.min='1';input.max=String(data.remaining);input.step='1';input.value='1';input.setAttribute('aria-label','Kullanılacak kişi hakkı');label.appendChild(input);const button=text('button','Hizmet verildi · Hakkı kullan',box);button.type='button';button.className='mdg-yb-primary';button.addEventListener('click',async()=>{const n=Number(input.value);if(!Number.isInteger(n)||n<1||n>data.remaining){input.reportValidity();return;}button.disabled=true;try{render(await api('redeem',{code:data.code,qty:n,request_key:data.request_key}));}catch(e){error(e);}});}}
 form.addEventListener('submit',async e=>{e.preventDefault();const button=form.querySelector('button');button.disabled=true;result.textContent='Ödeme kontrol ediliyor…';try{render(await api('lookup',{code:form.elements.code.value.trim().toUpperCase()}));}catch(e){error(e);}finally{button.disabled=false;}});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
}());
