(function(){
'use strict';
function money(n){try{return new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY',maximumFractionDigits:Number.isInteger(n)?0:2}).format(n);}catch(e){return n.toFixed(2)+' ₺';}}
function qs(sel,root){return (root||document).querySelector(sel);}function qsa(sel,root){return Array.prototype.slice.call((root||document).querySelectorAll(sel));}
var sales=window.MDG_EVENT_SALES||{enabled:false};
var sessionButtons=qsa('.mdg-session-option');
var ticketRows=qsa('.mdg-ticket-option');
var checkout=qs('[data-mdg-checkout-preview]');
var peopleEl=qs('[data-mdg-people]');
var totalEl=qs('[data-mdg-total]');
var stickySession=qs('[data-mdg-sticky-session]');
var stickyPrice=qs('[data-mdg-sticky-price]');
var mobileSummary=qs('[data-mdg-mobile-summary]');
var messageEl=qs('[data-mdg-cart-test-message]');
var minPrice=null;
ticketRows.forEach(function(row){var p=parseFloat(row.getAttribute('data-ticket-price')||'0');if(minPrice===null||p<minPrice)minPrice=p;});
function selectedSession(){return qs('.mdg-session-option.is-selected');}
function syncSessionUI(){var active=selectedSession();sessionButtons.forEach(function(b){b.setAttribute('aria-pressed',b===active?'true':'false');});if(!active)return;var d=active.getAttribute('data-session-date')||'';var t=active.getAttribute('data-session-time')||'';var label=(d+(t?' · '+t:'')).trim();if(stickySession)stickySession.textContent=label;if(mobileSummary){var priceLabel=minPrice!==null?' · '+money(minPrice)+"'den":'';mobileSummary.textContent=label+priceLabel;}}
function recalc(){var total=0,people=0;ticketRows.forEach(function(row){var price=parseFloat(row.getAttribute('data-ticket-price')||'0');var units=parseInt(row.getAttribute('data-ticket-units')||'1',10);var input=qs('input',row);var qty=parseInt(input?input.value:'0',10)||0;total+=price*qty;people+=units*qty;});if(peopleEl)peopleEl.textContent=String(people);if(totalEl)totalEl.textContent=money(total);if(checkout){checkout.disabled=people<1;checkout.textContent=people<1?'Devam etmek için bilet seçin':(sales.enabled?'Biletleri Sepete Ekle':'Ödeme adımını önizle');}if(stickyPrice){stickyPrice.textContent=total>0?('Toplam '+money(total)):(minPrice!==null?money(minPrice)+"'den başlayan":'Bilet seçimi');}if(mobileSummary&&total>0){var a=selectedSession();var d=a?(a.getAttribute('data-session-date')||''):'';var t=a?(a.getAttribute('data-session-time')||''):'';mobileSummary.textContent=(d+(t?' · '+t:'')).trim()+' · '+money(total);}}
function resetSelections(){ticketRows.forEach(function(row){var input=qs('input',row);if(input)input.value='0';});sessionButtons.forEach(function(b,i){b.classList.toggle('is-selected',i===0);b.setAttribute('aria-pressed',i===0?'true':'false');});syncSessionUI();recalc();}
function showMessage(text,isError){if(!messageEl)return;messageEl.hidden=false;messageEl.textContent=text||'';messageEl.classList.toggle('is-error',!!isError);}
sessionButtons.forEach(function(btn){btn.addEventListener('click',function(){sessionButtons.forEach(function(b){b.classList.remove('is-selected');b.setAttribute('aria-pressed','false');});btn.classList.add('is-selected');btn.setAttribute('aria-pressed','true');syncSessionUI();recalc();});});
ticketRows.forEach(function(row){var input=qs('input',row);var minus=qs('[data-minus]',row);var plus=qs('[data-plus]',row);if(minus)minus.addEventListener('click',function(){var v=Math.max(0,(parseInt(input.value,10)||0)-1);input.value=v;recalc();});if(plus)plus.addEventListener('click',function(){var v=Math.min(20,(parseInt(input.value,10)||0)+1);input.value=v;recalc();});});
if(checkout)checkout.addEventListener('click',function(){
    if(checkout.disabled)return;
    if(!sales.enabled){window.alert('Önizleme başarılı. Gerçek sepet bağlantısı bu taslak için henüz etkin değil.');return;}
    var active=selectedSession();
    var sessionId=active?parseInt(active.getAttribute('data-session-id')||'0',10):0;
    var lines=[];
    ticketRows.forEach(function(row){var input=qs('input',row);var qty=parseInt(input?input.value:'0',10)||0;if(qty>0){lines.push({code:row.getAttribute('data-ticket-code')||'',qty:qty});}});
    if(!sessionId||!lines.length){showMessage('Seans ve en az bir bilet seçmelisiniz.',true);return;}

    function sendToCart(forceClear){
        checkout.disabled=true;checkout.textContent=forceClear?'Sepet temizlenip aktarılıyor…':'Sepete aktarılıyor…';showMessage(forceClear?'WooCommerce sepet oturumu temizlenip seçim ekleniyor…':'WooCommerce sepeti hazırlanıyor…',false);
        var body=new URLSearchParams();body.set('action',sales.action||'mdg_preview_add_to_cart');body.set('nonce',sales.nonce||'');body.set('event_id',String(sales.eventId||0));body.set('session_id',String(sessionId));body.set('lines',JSON.stringify(lines));if(forceClear&&sales.mode==='preview')body.set('force_clear','1');
        fetch(sales.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
          .then(function(r){return r.json().catch(function(){throw new Error('Sunucudan geçerli yanıt alınamadı.');});})
          .then(function(res){
              if(!res||!res.success){
                  var data=res&&res.data?res.data:{};
                  if(sales.mode==='preview'&&data.code==='cart_has_other_items'&&!forceClear){
                      var ok=window.confirm('WooCommerce oturumunda eski/başka bir sepet ürünü görünüyor.\n\nSepette daha önce kalmış ürünler görünüyor. Sepet temizlensin ve yalnızca seçtiğiniz Madagaskar biletleri eklensin mi?');
                      if(ok){sendToCart(true);return;}
                  }
                  throw new Error(data.message||'Sepete aktarma başarısız.');
              }
              showMessage(res.data.message||'Sepete aktarıldı.',false);window.location.href=res.data.cart_url||sales.cartUrl||'/cart/';
          })
          .catch(function(err){showMessage(err.message||'Sepete aktarma sırasında hata oluştu.',true);checkout.disabled=false;recalc();});
    }

    sendToCart(false);
});
var modal=qs('[data-mdg-lightbox-modal]');var modalImg=modal?qs('img',modal):null;var photoButtons=qsa('[data-mdg-lightbox]');var currentPhoto=-1;function openPhoto(i){if(!modal||!modalImg||!photoButtons[i])return;currentPhoto=i;modalImg.src=photoButtons[i].getAttribute('data-mdg-lightbox')||'';modal.hidden=false;document.body.style.overflow='hidden';}photoButtons.forEach(function(btn,i){btn.addEventListener('click',function(){openPhoto(i);});});function close(){if(!modal)return;modal.hidden=true;if(modalImg)modalImg.src='';document.body.style.overflow='';currentPhoto=-1;}if(modal){var c=qs('[data-mdg-lightbox-close]',modal);if(c)c.addEventListener('click',close);modal.addEventListener('click',function(e){if(e.target===modal)close();});document.addEventListener('keydown',function(e){if(modal.hidden)return;if(e.key==='Escape')close();if(e.key==='ArrowRight'&&photoButtons.length)openPhoto((currentPhoto+1)%photoButtons.length);if(e.key==='ArrowLeft'&&photoButtons.length)openPhoto((currentPhoto-1+photoButtons.length)%photoButtons.length);});}
var hero=qs('.mdg-event-hero');var desktopSticky=qs('[data-mdg-desktop-sticky]');function syncDesktopSticky(){if(!hero||!desktopSticky)return;var r=hero.getBoundingClientRect();desktopSticky.classList.toggle('is-visible',r.bottom<92&&window.innerWidth>700);}window.addEventListener('scroll',syncDesktopSticky,{passive:true});window.addEventListener('resize',syncDesktopSticky);
window.addEventListener('pageshow',function(){resetSelections();syncDesktopSticky();});
resetSelections();syncDesktopSticky();
})();
