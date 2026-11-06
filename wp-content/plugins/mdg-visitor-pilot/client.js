(function () {
    'use strict';
    var configEl = document.getElementById('vp-config');
    if (!configEl || !document.getElementById('mdg-vp')) return;
    var config, api;
    try {
        config = JSON.parse(configEl.textContent); api = new URL(config.api);
        if (!['https://madagaskarsirki.com', 'https://staging-f321-milanosirki-lgajf.wpcomstaging.com'].includes(api.origin) || api.origin !== location.origin || api.origin !== config.origin || api.pathname !== '/wp-json/mdg-visitor/v1/' || api.search || api.hash || api.username || api.password) return;
    } catch (_) { return; }
    var $ = function (id) { return document.getElementById('vp-' + id); };
    var token = '', offers = [], revision = 0, quoteBusy = false, parseBusy = false, aiEnabled = false, deadline = 0, expiryTimer = null;
    var money = new Intl.NumberFormat('tr-TR', {style:'currency', currency:'TRY'});
    var date = new Intl.DateTimeFormat('tr-TR', {timeZone:'Europe/Istanbul',day:'numeric',month:'long',hour:'2-digit',minute:'2-digit'});
    function status(text) { $('status').textContent = text; }
    function selected() { return offers.find(function(o){return String(o.session_id) === $('session').value;}); }
    function ready() { $('submit').disabled = quoteBusy || !selected(); $('parse').disabled = parseBusy || !aiEnabled || !token; }
    function clear() { revision++; clearTimeout(expiryTimer); deadline=0; $('result').hidden=true; $('ticket').removeAttribute('href'); ready(); }
    function expire() { clear(); status('Önerinin süresi doldu. Güncel fiyat için yeniden karşılaştırın.'); }
    function expiry(event) { if(deadline && performance.now() >= deadline){if(event)event.preventDefault();expire();} }
    $('ticket').addEventListener('click',expiry);
    document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible')expiry();});
    function option(select,text,value) { var o=document.createElement('option');o.textContent=text;o.value=value;select.appendChild(o); }
    function key(o) { return o.city + ' / ' + o.district; }
    function sessions() {
        $('session').replaceChildren();option($('session'),'Size uygun seansı seçin','');
        offers.filter(function(o){return key(o)===$('city').value;}).forEach(function(o){option($('session'),date.format(new Date(o.starts_at))+' · '+o.venue.name,String(o.session_id));});
        $('session').disabled=!$('city').value;ready();
    }
    async function request(path,data) {
        var controller=new AbortController(), timer=setTimeout(function(){controller.abort();},35000);
        try {
            var opts={method:data?'POST':'GET',credentials:'omit',cache:'no-store',signal:controller.signal,headers:{'Content-Type':'application/json'}};
            if(data)opts.body=JSON.stringify(Object.assign({token:token},data));
            var response=await fetch(new URL(path,api.href).href,opts), body;
            try{body=await response.json();}catch(_){throw new Error(response.status===429?'RATE_LIMIT':'UNAVAILABLE');}
            if(!response.ok)throw new Error(body.code||'UNAVAILABLE');
            return body;
        } finally {clearTimeout(timer);}
    }
    async function load() {
        clear();$('city').disabled=true;$('session').disabled=true;offers=[];
        try {
            var boot=await request('bootstrap');
            if(typeof boot.token!=='string'||boot.token.length>200)throw new Error();token=boot.token;aiEnabled=boot.ai_enabled===true;
            var data=await request('catalogue',{});if(!Array.isArray(data.offers))throw new Error();offers=data.offers;
            $('city').replaceChildren();option($('city'),'Şehrinizi seçin','');
            Array.from(new Set(offers.map(key))).forEach(function(c){option($('city'),c.normalize('NFC'),c);});
            $('city').disabled=!offers.length;sessions();
            status(offers.length?'Şehrinizi ve seansınızı seçerek başlayın.':'Şu anda öneri sunabileceğimiz güncel seans bulunmuyor. Tüm gösterilere Bilet Al sayfasından ulaşabilirsiniz.');
            if(!aiEnabled)$('ai-status').textContent='Yapay zekâ şu anda kapalı. Aşağıdaki formu kullanabilirsiniz.';
            ready();
        } catch (_) { token='';aiEnabled=false;ready();status('Öneri servisi şu anda kullanılamıyor. Tüm gösteriler için Bilet Al sayfasını kullanın.'); }
    }
    $('city').addEventListener('change',function(){clear();sessions();status('Seansınızı seçin.');});
    $('session').addEventListener('change',clear);
    ['adults','children','infants'].forEach(function(k){$(k).addEventListener('input',clear);});
    $('text').addEventListener('input',function(){revision++;});
    $('parse').addEventListener('click',async function(){
        if(parseBusy||!aiEnabled||!token)return;
        if($('text').value.trim().length<3){$('ai-status').textContent='Şehrinizi ve kaç kişi geleceğinizi birkaç kelimeyle yazın.';$('text').focus();return;}
        var before=revision;parseBusy=true;ready();$('ai-status').textContent='Bilgiler forma hazırlanıyor…';
        try {
            var data=await request('parse',{text:$('text').value});if(before!==revision){$('ai-status').textContent='Bilgiler değiştiği için önceki yanıt uygulanmadı.';return;}
            var p=data.proposal;if(data.requires_confirmation!==true||!p)throw new Error();
            ['adults','children','infants'].forEach(function(k){if(p[k]!==null&&(!Number.isInteger(p[k])||p[k]<(k==='adults'?1:0)||p[k]>20))throw new Error();});
            if(p.city_key!==null&&!Array.from($('city').options).some(function(o){return o.value===p.city_key&&o.value;}))throw new Error();
            clear();$('city').value=p.city_key===null?'':p.city_key;sessions();
            ['adults','children','infants'].forEach(function(k){$(k).value=p[k]===null?'':String(p[k]);});
            var names={city_key:'şehir/ilçe',adults:'yetişkin sayısı',children:'çocuk sayısı',infants:'0–2 yaş misafir sayısı'};
            var missing=Object.keys(names).filter(function(k){return p[k]===null;}).map(function(k){return names[k];});
            $('ai-status').textContent=missing.length?'Eksik bilgiler: '+missing.join(', ')+'. Formda tamamlayıp seansınızı seçin.':'Bilgileri kontrol edip seansınızı kendiniz seçin.';
        }catch(error){$('ai-status').textContent=['RATE_LIMIT','PLEASE_WAIT'].includes(error.message)?'Kısa bir süre bekleyin veya aşağıdaki formu hemen kullanın.':'Metin şu anda aktarılamadı. Aşağıdaki formu kullanabilirsiniz.';}
        finally{parseBusy=false;ready();}
    });
    function minor(n){if(!Number.isSafeInteger(n)||n<0)throw new Error();return money.format(n/100);}
    $('form').addEventListener('submit',async function(event){
        event.preventDefault();if(quoteBusy||!selected()||!$('form').reportValidity())return;
        var party={};for(var k of ['adults','children','infants']){var v=$(k).value;if(!/^(0|[1-9][0-9]?)$/.test(v)||Number(v)>20||(k==='adults'&&Number(v)<1))return;party[k]=Number(v);}
        clear();var rev=revision, offer=selected(), started=performance.now();quoteBusy=true;ready();status('Güncel bilet seçenekleri karşılaştırılıyor…');
        try{
            var q=await request('quote',{session_id:offer.session_id,party:party,source_revision:offer.source_revision});if(rev!==revision)return;
            if(q.session_id!==offer.session_id||q.reservation!==false||!q.recommended||!Array.isArray(q.recommended.lines)||!Number.isSafeInteger(q.verified_at)||!Number.isSafeInteger(q.expires_at))throw new Error();
            var ttl=(q.expires_at-q.verified_at)*1000, remaining=ttl-(performance.now()-started);if(ttl<=0||ttl>60000||remaining<=0)throw new Error('STALE_SELECTION');
            var url=new URL(q.ticket_url);if(url.origin!==config.origin||url.username||url.password||url.search||url.hash||!/^\/etkinlik\/[a-z0-9-]+\/$/.test(url.pathname))throw new Error();url.hash='bilet-secimi';
            var labels={family_2_2:'Aile Paketi 2+2',COCUK:'Çocuk bileti',CHILD:'Çocuk bileti',YETISKIN:'Yetişkin bileti',ADULT:'Yetişkin bileti'};$('lines').replaceChildren();
            q.recommended.lines.forEach(function(l){if(!labels[l.code]||!Number.isInteger(l.quantity)||l.quantity<1||l.quantity>20)throw new Error();var li=document.createElement('li');li.textContent=l.quantity+' × '+labels[l.code]+' · '+minor(l.unit_minor);$('lines').appendChild(li);});
            $('date').textContent=date.format(new Date(offer.starts_at))+' · '+offer.venue.name;$('total').textContent=minor(q.recommended.total_minor);
            $('savings').textContent=q.regular_option_available&&q.recommended.savings_minor>0?'Tekil biletlere göre '+minor(q.recommended.savings_minor)+' daha uygun.':'Bu grup için güncel fiyatlarla en uygun geçerli seçenek.';
            $('capacity').textContent=q.recommended.family_quantity>0?'Aile paketinin kullanılmayan hakları olsa da her paket 4 kişilik kapasite kullanır.':'';
            $('handoff').textContent='Bilet ekranında '+date.format(new Date(offer.starts_at))+' seansını ve yukarıdaki bilet adetlerini yeniden seçin. Seçimleriniz otomatik aktarılmaz; öneri rezervasyon oluşturmaz.';
            $('ticket').href=url.href;$('result').hidden=false;deadline=performance.now()+remaining;expiryTimer=setTimeout(expire,remaining);status('Bilet öneriniz hazır.');
        }catch(error){
            if(rev!==revision)return;clear();
            if(['STALE_SELECTION','SESSION_UNAVAILABLE','SESSION_REQUIRED'].includes(error.message)){await load();status('Gösteri veya oturum bilgisi değişti. Seansınızı yeniden seçin.');}
            else status(error.message==='NO_FEASIBLE_COMBINATION'?'Bu kişi sayısı için uygun seçenek bulunamadı. Başka bir seans seçin.':'Öneri şu anda hesaplanamadı. Biraz sonra deneyin veya Bilet Al sayfasını kullanın.');
        }finally{quoteBusy=false;ready();}
    });
    load();
})();
