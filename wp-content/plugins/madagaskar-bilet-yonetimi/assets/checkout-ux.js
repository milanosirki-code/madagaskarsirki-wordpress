(function(){
'use strict';
var cfg=window.MDG_CHECKOUT_UX||{};
var marketingEN='i would like to receive exclusive emails with discounts and product information';
var marketingTR='kampanya ve etkinlik duyurularını e-posta ile almak istiyorum';
var guestTR='şu anda konuk olarak ödeme yapıyorsunuz.';
var guestEN='you are currently checking out as a guest.';
var invoiceLabels={
  type:'fatura türü',
  tckn:'t.c. kimlik no (opsiyonel)',
  company:'firma / şirket unvanı',
  taxNumber:'vergi numarası',
  taxOffice:'vergi dairesi'
};
function norm(v){return String(v||'').replace(/\s+/g,' ').trim().toLocaleLowerCase('tr-TR');}
function isMarketingText(v){var t=norm(v);return t===marketingEN||t===marketingTR;}
function replaceExact(el,from,to){if(!el)return;var t=String(el.textContent||'').replace(/\s+/g,' ').trim();if(t===from)el.textContent=to;}
function localize(){
  document.querySelectorAll('h1,h2,h3,h4,h5,h6,label,legend,span,p').forEach(function(el){
    replaceExact(el,'Attendee Info','Katılımcı Bilgileri');
    replaceExact(el,'First name','Ad');
    replaceExact(el,'Last name','Soyad');
    replaceExact(el,'I would like to receive exclusive emails with discounts and product information','Kampanya ve etkinlik duyurularını e-posta ile almak istiyorum');
  });
  document.querySelectorAll('input').forEach(function(input){
    if(input.placeholder==='First name')input.placeholder='Ad';
    if(input.placeholder==='Last name')input.placeholder='Soyad';
  });
}
function isOfficialConsentInput(input){
  if(!input)return false;
  if(input.getAttribute('data-mdg-marketing-optin')==='1')return true;
  var id=String(input.id||'');var name=String(input.name||'');
  return id.indexOf('marketing-opt-in')>-1||name.indexOf('marketing-opt-in')>-1;
}
function rowFromInput(input){
  if(!input)return null;
  var preferred=input.closest('label,.wc-block-components-checkbox,.wc-block-checkout__additional-fields,.woocommerce-form__label-for-checkbox,.woocommerce-form-row,.form-row,.wc-block-components-text-input,.wc-block-components-select-input');
  if(preferred&&preferred!==document.body)return preferred;
  var cur=input.parentElement,depth=0;
  while(cur&&depth<7){
    var controls=cur.querySelectorAll?cur.querySelectorAll('input,select,textarea').length:0;
    if(controls===1)return cur;
    cur=cur.parentElement;depth++;
  }
  return input.parentElement;
}
function rowFromText(el){
  if(!el)return null;
  var preferred=el.closest('label,.wc-block-components-checkbox,.woocommerce-form__label-for-checkbox,.woocommerce-form-row,.form-row,p,.wc-block-components-text-input,.wc-block-components-select-input');
  if(preferred)return preferred;
  var cur=el,depth=0;
  while(cur&&depth<5){
    var boxes=cur.querySelectorAll?cur.querySelectorAll('input[type="checkbox"]').length:0;
    if(boxes===1)return cur;
    cur=cur.parentElement;depth++;
  }
  return el;
}
function hideRow(row){
  if(!row)return;
  row.querySelectorAll&&row.querySelectorAll('input[type="checkbox"]').forEach(function(input){input.checked=false;input.disabled=true;});
  row.style.setProperty('display','none','important');
  row.setAttribute('aria-hidden','true');
  row.dataset.mdgLegacyConsentHidden='1';
}
function cleanMarketingConsent(){
  var official=null;
  document.querySelectorAll('input[type="checkbox"]').forEach(function(input){
    if(isOfficialConsentInput(input)){official=input;}
  });
  if(official){
    var officialRow=rowFromInput(official);
    official.disabled=false;
    if(officialRow){
      officialRow.style.removeProperty('display');
      officialRow.removeAttribute('aria-hidden');
      officialRow.classList.add('mdg-marketing-optin-row');
      officialRow.dataset.mdgConsentPrimary='1';
    }
  }
  document.querySelectorAll('input[type="checkbox"]').forEach(function(input){
    if(isOfficialConsentInput(input))return;
    var row=rowFromInput(input);if(!row)return;
    var text=norm(row.textContent);
    if(text.indexOf(marketingEN)>-1||text.indexOf(marketingTR)>-1)hideRow(row);
  });
  document.querySelectorAll('label,span,p,div').forEach(function(el){
    if(!isMarketingText(el.textContent))return;
    var row=rowFromText(el);if(!row)return;
    if(row.querySelector&&row.querySelector('input[data-mdg-marketing-optin="1"],input[id*="marketing-opt-in"],input[name*="marketing-opt-in"]'))return;
    hideRow(row);
  });
  if(!cfg.ticketOnly&&official){hideRow(rowFromInput(official));}
}
function hideGuestCheckoutHelper(){
  document.querySelectorAll('p,span,div').forEach(function(el){
    var t=norm(el.textContent);
    if(t!==guestTR&&t!==guestEN)return;
    if(el.children&&el.children.length>1)return;
    el.style.setProperty('display','none','important');
    el.setAttribute('aria-hidden','true');
    el.dataset.mdgGuestHelperHidden='1';
  });
}
function hideResidualShipping(){
  if(!cfg.ticketOnly)return;
  [
    '.wc-block-checkout__shipping-method',
    '.wc-block-checkout__shipping-fields',
    '.wc-block-components-shipping-rates-control',
    '.woocommerce-shipping-fields',
    '#shipping_method'
  ].forEach(function(sel){document.querySelectorAll(sel).forEach(function(el){el.style.display='none';el.setAttribute('aria-hidden','true');});});
}
function findInvoiceControl(kind){
  var expected=(cfg.invoiceFields||{})[kind]||'';
  var attrMap={type:'type',tckn:'tckn',company:'company','taxNumber':'tax-number','taxOffice':'tax-office'};
  var attrVal=attrMap[kind]||kind;
  var direct=document.querySelector('[data-mdg-invoice-field="'+attrVal+'"]');
  if(direct)return direct;
  if(expected){
    var slug=expected.split('/').pop();
    var candidates=document.querySelectorAll('input,select,textarea');
    for(var i=0;i<candidates.length;i++){
      var c=candidates[i],id=String(c.id||''),name=String(c.name||'');
      if(id.indexOf(slug)>-1||name.indexOf(slug)>-1)return c;
    }
  }
  var wanted=invoiceLabels[kind];
  if(wanted){
    var labels=document.querySelectorAll('label');
    for(var j=0;j<labels.length;j++){
      if(norm(labels[j].textContent)!==wanted)continue;
      var f=labels[j].getAttribute('for');
      if(f){var byId=document.getElementById(f);if(byId)return byId;}
      var inside=labels[j].querySelector('input,select,textarea');if(inside)return inside;
      var parent=labels[j].parentElement;var nearby=parent&&parent.querySelector?parent.querySelector('input,select,textarea'):null;if(nearby)return nearby;
    }
  }
  return null;
}
function invoiceRow(kind){var c=findInvoiceControl(kind);return c?rowFromInput(c):null;}
function setRowVisible(row,visible){
  if(!row)return;
  if(visible){row.style.removeProperty('display');row.removeAttribute('aria-hidden');}
  else{row.style.setProperty('display','none','important');row.setAttribute('aria-hidden','true');}
}
function cleanDigits(input,max){
  if(!input)return;
  var next=String(input.value||'').replace(/\D+/g,'').slice(0,max);
  if(next===input.value)return;
  input.value=next;
  try{input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));}catch(e){}
}
function ensureInvoiceHeading(typeRow){
  if(!typeRow||!typeRow.parentNode)return;
  var existing=document.querySelector('.mdg-invoice-fields-heading');
  if(existing)return;
  var wrap=document.createElement('div');
  wrap.className='mdg-invoice-fields-heading';
  wrap.innerHTML='<h3>Fatura bilgileri</h3><p>Bu bilgiler yalnızca faturalandırma işlemleri için kullanılır.</p>';
  typeRow.parentNode.insertBefore(wrap,typeRow);
}
function wireInvoiceControls(){
  var type=findInvoiceControl('type');
  var tckn=findInvoiceControl('tckn');
  var company=findInvoiceControl('company');
  var taxNumber=findInvoiceControl('taxNumber');
  var taxOffice=findInvoiceControl('taxOffice');
  var rows={type:type?rowFromInput(type):null,tckn:tckn?rowFromInput(tckn):null,company:company?rowFromInput(company):null,taxNumber:taxNumber?rowFromInput(taxNumber):null,taxOffice:taxOffice?rowFromInput(taxOffice):null};

  if(!cfg.ticketOnly){Object.keys(rows).forEach(function(k){setRowVisible(rows[k],false);});var h=document.querySelector('.mdg-invoice-fields-heading');if(h)h.style.display='none';return;}
  if(!type)return;

  ensureInvoiceHeading(rows.type);
  var heading=document.querySelector('.mdg-invoice-fields-heading');if(heading)heading.style.removeProperty('display');

  var corporate=String(type.value||'individual')==='corporate';
  setRowVisible(rows.type,true);
  setRowVisible(rows.tckn,!corporate);
  setRowVisible(rows.company,corporate);
  setRowVisible(rows.taxNumber,corporate);
  setRowVisible(rows.taxOffice,corporate);

  if(tckn){tckn.setAttribute('inputmode','numeric');tckn.setAttribute('maxlength','11');tckn.setAttribute('autocomplete','off');}
  if(company){company.setAttribute('maxlength','160');company.setAttribute('autocomplete','organization');}
  if(taxNumber){taxNumber.setAttribute('inputmode','numeric');taxNumber.setAttribute('maxlength','10');taxNumber.setAttribute('autocomplete','off');}
  if(taxOffice){taxOffice.setAttribute('maxlength','100');}
  if(!type.dataset.mdgInvoiceWired){
    type.dataset.mdgInvoiceWired='1';
    type.addEventListener('change',function(){window.setTimeout(wireInvoiceControls,0);});
  }
  if(tckn&&!tckn.dataset.mdgDigitsWired){tckn.dataset.mdgDigitsWired='1';tckn.addEventListener('input',function(){cleanDigits(tckn,11);});}
  if(taxNumber&&!taxNumber.dataset.mdgDigitsWired){taxNumber.dataset.mdgDigitsWired='1';taxNumber.addEventListener('input',function(){cleanDigits(taxNumber,10);});}
}
function run(){localize();cleanMarketingConsent();hideGuestCheckoutHelper();hideResidualShipping();wireInvoiceControls();}
var queued=false;function queue(){if(queued)return;queued=true;window.requestAnimationFrame(function(){queued=false;run();});}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',run);}else{run();}
var mo=new MutationObserver(queue);mo.observe(document.documentElement,{childList:true,subtree:true,characterData:true});
})();
