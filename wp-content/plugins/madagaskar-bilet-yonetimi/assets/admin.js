(function(){
    'use strict';

    function initDistrictSelector(){
        var province = document.querySelector('[data-mdg-province]');
        var district = document.querySelector('[data-mdg-district]');
        var manualWrap = document.querySelector('[data-mdg-district-manual-wrap]');
        var manual = document.querySelector('[data-mdg-district-manual]');
        if(!province || !district || typeof window.MDG_VENUE_DATA === 'undefined') return;

        var data = window.MDG_VENUE_DATA.districts || {};
        var selected = district.getAttribute('data-selected') || '';

        function addOption(value, label){
            var o = document.createElement('option');
            o.value = value;
            o.textContent = label;
            district.appendChild(o);
        }
        function placeKey(value){
            var s=String(value==null?'':value).trim().replace(/\s+/g,' ').toLocaleLowerCase('tr-TR');
            try { s=s.normalize('NFD').replace(/[\u0300-\u036f]/g,''); } catch(e) {}
            return s.replace(/ı/g,'i');
        }

        function toggleManual(show){
            if(!manualWrap || !manual) return;
            manualWrap.style.display = show ? 'block' : 'none';
            manual.required = !!show;
            district.required = !show;
        }

        function populate(){
            var code = province.value;
            var list = Array.isArray(data[code]) ? data[code] : [];
            district.innerHTML = '';
            addOption('', code ? 'İlçe seçiniz' : 'Önce il seçiniz');
            district.disabled = !code;

            if(code && list.length){
                list.forEach(function(name){ addOption(name, name); });
                addOption('__manual__', 'Listede yok / manuel gir');
                toggleManual(false);
                if(selected){
                    var match=list.find(function(item){ return placeKey(item)===placeKey(selected); });
                    if(match){
                        district.value = match;
                        if(manual) manual.value = '';
                        toggleManual(false);
                    } else {
                        district.value = '__manual__';
                        if(manual){ manual.value = selected; }
                        toggleManual(true);
                    }
                }
            } else if(code){
                addOption('__manual__', 'İlçe listesini yükleyemedik – manuel gir');
                district.value = '__manual__';
                toggleManual(true);
            } else {
                toggleManual(false);
            }
        }

        province.addEventListener('change', function(){
            selected = '';
            if(manual) manual.value = '';
            populate();
        });
        district.addEventListener('change', function(){
            toggleManual(district.value === '__manual__');
            if(district.value !== '__manual__' && manual) manual.value = '';
        });
        populate();
    }

    function initVenueQr(){
        var form = document.querySelector('[data-mdg-venue-form]');
        var maps = document.querySelector('[data-mdg-maps-url]');
        var hidden = document.querySelector('[data-mdg-qr-data]');
        var force = document.querySelector('[data-mdg-force-qr]');
        var preview = document.querySelector('[data-mdg-qr-preview]');
        var status = document.querySelector('[data-mdg-qr-status]');
        var openLink = document.querySelector('[data-mdg-open-map]');
        var regenerate = document.querySelector('[data-mdg-regenerate-qr]');
        if(!form || !maps || !hidden || !preview) return;

        var timer = null;
        var generatedFor = '';

        function setStatus(text, type){
            if(!status) return;
            status.textContent = text;
            status.className = 'mdg-qr-status ' + (type ? 'is-' + type : '');
        }

        function clearTemp(){
            var temp = preview.querySelector('[data-mdg-qr-temp]');
            if(temp) temp.remove();
        }

        function render(url, callback){
            url = (url || '').trim();
            hidden.value = '';
            generatedFor = '';
            clearTemp();

            if(openLink){
                openLink.href = url || '#';
                openLink.style.display = url ? 'inline-flex' : 'none';
            }
            if(!url){
                setStatus('Harita bağlantısı girildiğinde QR otomatik hazırlanır.', 'muted');
                if(callback) callback(false);
                return;
            }
            if(typeof window.QRCode === 'undefined'){
                setStatus('QR kütüphanesi yüklenemedi. Salon QR olmadan da kaydedilebilir.', 'warning');
                if(callback) callback(false);
                return;
            }

            try{
                var holder = document.createElement('div');
                holder.setAttribute('data-mdg-qr-temp','1');
                holder.className = 'mdg-qr-temp';
                preview.appendChild(holder);
                new window.QRCode(holder, {
                    text: url,
                    width: 280,
                    height: 280,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: window.QRCode.CorrectLevel.M
                });

                window.setTimeout(function(){
                    var canvas = holder.querySelector('canvas');
                    var img = holder.querySelector('img');
                    var data = '';
                    try{
                        if(canvas && canvas.toDataURL){ data = canvas.toDataURL('image/png'); }
                        else if(img && /^data:image\/png;base64,/.test(img.src || '')){ data = img.src; }
                    }catch(e){ data = ''; }
                    if(data){
                        hidden.value = data;
                        generatedFor = url;
                        setStatus('QR hazır – salon kaydedildiğinde Media Library’ye yazılacak.', 'success');
                        if(callback) callback(true);
                    } else {
                        setStatus('QR önizlemesi oluşmadı. Salon QR olmadan da kaydedilebilir.', 'warning');
                        if(callback) callback(false);
                    }
                }, 80);
            }catch(err){
                setStatus('QR üretilemedi. Salon kaydı yine yapılabilir.', 'warning');
                if(callback) callback(false);
            }
        }

        function schedule(){
            window.clearTimeout(timer);
            timer = window.setTimeout(function(){ render(maps.value); }, 250);
        }

        maps.addEventListener('input', schedule);
        maps.addEventListener('change', schedule);
        maps.addEventListener('blur', schedule);

        if(regenerate){
            regenerate.addEventListener('click', function(){
                if(force) force.value = '1';
                render(maps.value);
            });
        }

        form.addEventListener('submit', function(ev){
            var url = (maps.value || '').trim();
            if(!url) return;

            // Değişmemiş mevcut salon QR'ı için yeniden üretim şart değil.
            var currentUrl = form.getAttribute('data-mdg-current-maps-url') || '';
            var hasExisting = form.getAttribute('data-mdg-has-existing-qr') === '1';
            var forced = force && force.value === '1';
            if(!forced && hasExisting && url === currentUrl) return;
            if(hidden.value && generatedFor === url) return;

            ev.preventDefault();
            setStatus('QR hazırlanıyor…', 'muted');
            render(url, function(){
                HTMLFormElement.prototype.submit.call(form);
            });
        });

        // Yeni kayıt veya URL değişikliği için sayfa açılır açılmaz önizleme hazırlansın.
        var existingUrl = (maps.value || '').trim();
        if(existingUrl && form.getAttribute('data-mdg-has-existing-qr') !== '1'){
            render(existingUrl);
        }
    }

    function initEventForm(){
        var form = document.querySelector('[data-mdg-event-form]');
        if(!form || typeof window.MDG_VENUE_DATA === 'undefined') return;
        var province = form.querySelector('[data-mdg-event-province]');
        var district = form.querySelector('[data-mdg-event-district]');
        var venue = form.querySelector('[data-mdg-event-venue]');
        var snap = form.querySelector('[data-mdg-venue-snapshot]');
        var duration = form.querySelector('[data-mdg-show-duration]');
        var districts = window.MDG_VENUE_DATA.districts || {};
        var venues = Array.isArray(window.MDG_VENUE_DATA.venues) ? window.MDG_VENUE_DATA.venues : [];
        var selectedDistrict = district ? (district.getAttribute('data-selected') || '') : '';
        var selectedVenue = venue ? parseInt(venue.getAttribute('data-selected') || '0',10) : 0;

        function option(select, value, label){
            var o=document.createElement('option'); o.value=value; o.textContent=label; select.appendChild(o);
        }
        function normalizeCode(value){
            var s=String(value==null?'':value).trim();
            return /^\d+$/.test(s) ? s.padStart(2,'0') : s;
        }
        function normalizeTr(value){
            var s=String(value==null?'':value).trim().replace(/\s+/g,' ').toLocaleLowerCase('tr-TR');
            try { s=s.normalize('NFD').replace(/[\u0300-\u036f]/g,''); } catch(e) {}
            return s.replace(/ı/g,'i');
        }
        function selectedProvinceName(){
            if(!province || province.selectedIndex < 0) return '';
            return province.options[province.selectedIndex] ? province.options[province.selectedIndex].textContent : '';
        }
        function sameProvince(v){
            return normalizeCode(v.province_code)===normalizeCode(province ? province.value : '') ||
                normalizeTr(v.province_name)===normalizeTr(selectedProvinceName());
        }
        function sameDistrict(v){
            return normalizeTr(v.district)===normalizeTr(district ? district.value : '');
        }
        function fillDistricts(){
            if(!province || !district) return;
            var list=Array.isArray(districts[province.value]) ? districts[province.value].slice() : [];
            // Excel/import ve uzak ilçe veri kaynağındaki olası Unicode/kod farklarını tolere et.
            venues.forEach(function(v){
                if(!sameProvince(v)) return;
                var exists=list.some(function(item){ return normalizeTr(item)===normalizeTr(v.district); });
                if(!exists && v.district) list.push(v.district);
            });
            list.sort(function(a,b){ return a.localeCompare(b,'tr'); });
            district.innerHTML=''; option(district,'',province.value ? 'İlçe seçiniz' : 'Önce il seçiniz');
            list.forEach(function(name){ option(district,name,name); });
            district.disabled=!province.value;
            if(selectedDistrict){
                var selectedMatch=list.find(function(item){return normalizeTr(item)===normalizeTr(selectedDistrict);});
                if(selectedMatch) district.value=selectedMatch;
            }
            fillVenues();
        }
        function fillVenues(){
            if(!venue) return;
            var hasDistrict=!!(district && district.value);
            venue.innerHTML='';
            var list=venues.filter(function(v){ return sameProvince(v) && sameDistrict(v); });
            option(venue,'',hasDistrict ? (list.length ? 'Salon seçiniz' : 'Bu ilçede kayıtlı aktif salon yok') : 'Önce ilçe seçiniz');
            list.forEach(function(v){ option(venue,String(v.id),v.name + (v.address_complete ? '' : ' ⚠ Adres eksik')); });
            venue.disabled=!hasDistrict;
            if(selectedVenue && list.some(function(v){return v.id===selectedVenue;})) venue.value=String(selectedVenue);
            renderSnapshot();
        }
        function currentVenue(){
            var id=parseInt(venue && venue.value ? venue.value : '0',10);
            return venues.find(function(v){return v.id===id;}) || null;
        }
        function renderSnapshot(){
            if(!snap) return;
            var v=currentVenue();
            if(!v){ snap.innerHTML='<strong>Salon seçildiğinde:</strong> adres, varsayılan kapasite, süre ve Maps bilgisi burada görünecek.'; return; }
            snap.innerHTML='<div><strong>'+escapeHtml(v.name)+'</strong></div>'+
                (v.address_complete ? '<div>'+escapeHtml(v.address || '')+'</div>' : '<div class="mdg-inline-warning">⚠ Açık adres henüz tamamlanmadı. Taslak hazırlanabilir; etkinlik yayına alınmadan önce Salonlar bölümünden adresi tamamlayın.</div>')+
                '<div class="mdg-snapshot-meta"><span>Kapasite: <strong>'+escapeHtml(String(v.default_capacity))+'</strong></span><span>Varsayılan süre: <strong>'+escapeHtml(String(v.default_duration))+' dk</strong></span>'+
                (v.maps_url ? '<a href="'+escapeAttr(v.maps_url)+'" target="_blank" rel="noopener noreferrer">Haritada Aç</a>' : '<span>Maps bağlantısı yok</span>')+'</div>';
            if(duration && !duration.value) duration.value=v.default_duration || '';
            form.querySelectorAll('[data-mdg-session-capacity]').forEach(function(input){
                if(!input.value){ input.value=v.default_capacity || ''; }
            });
        }
        function escapeHtml(str){ var d=document.createElement('div'); d.textContent=str==null?'':str; return d.innerHTML; }
        function escapeAttr(str){ return String(str==null?'':str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

        if(province) province.addEventListener('change',function(){ selectedDistrict=''; selectedVenue=0; fillDistricts(); });
        if(district) district.addEventListener('change',function(){ selectedVenue=0; fillVenues(); });
        if(venue) venue.addEventListener('change',renderSnapshot);
        fillDistricts();

        var heroId=form.querySelector('[data-mdg-hero-id]');
        var heroPreview=form.querySelector('[data-mdg-hero-preview]');
        var chooseHero=form.querySelector('[data-mdg-choose-hero]');
        var clearHero=form.querySelector('[data-mdg-clear-hero]');
        if(chooseHero && window.wp && wp.media){
            chooseHero.addEventListener('click',function(){
                var frame=wp.media({title:'Etkinlik kapak görselini seç',button:{text:'Kapak olarak kullan'},multiple:false,library:{type:'image'}});
                frame.on('select',function(){
                    var a=frame.state().get('selection').first().toJSON();
                    if(heroId) heroId.value=a.id;
                    if(heroPreview) heroPreview.innerHTML='<img src="'+escapeAttr((a.sizes && a.sizes.medium_large ? a.sizes.medium_large.url : a.url))+'" alt="Etkinlik kapak görseli">';
                });
                frame.open();
            });
        }
        if(clearHero) clearHero.addEventListener('click',function(){ if(heroId) heroId.value=''; if(heroPreview) heroPreview.innerHTML='<span>Henüz kapak görseli seçilmedi.</span>'; });

        var galleryIds=form.querySelector('[data-mdg-gallery-ids]');
        var galleryPreview=form.querySelector('[data-mdg-gallery-preview]');
        var chooseGallery=form.querySelector('[data-mdg-choose-gallery]');
        var clearGallery=form.querySelector('[data-mdg-clear-gallery]');
        var galleryCount=form.querySelector('[data-mdg-gallery-count]');
        var galleryUrlCache={};
        function galleryIdList(){
            var seen={};
            return String(galleryIds && galleryIds.value ? galleryIds.value : '').split(',').map(function(raw){return parseInt(raw,10);}).filter(function(id){
                if(!id || seen[id]) return false; seen[id]=true; return true;
            }).slice(0,30);
        }
        function cacheCurrentGalleryUrls(){
            if(!galleryPreview) return;
            galleryPreview.querySelectorAll('img[data-id]').forEach(function(img){
                var id=parseInt(img.getAttribute('data-id')||'0',10); if(id) galleryUrlCache[id]=img.getAttribute('src')||'';
            });
        }
        function attachmentThumb(a){
            return (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : (a.url||''));
        }
        function renderGalleryIds(ids){
            ids=ids.filter(Boolean).slice(0,30);
            if(galleryIds) galleryIds.value=ids.join(',');
            if(galleryCount) galleryCount.textContent=ids.length+' görsel seçili.';
            if(!galleryPreview) return;
            var html='';
            ids.forEach(function(id){
                var u=galleryUrlCache[id]||'';
                if(u){
                    html+='<div class="mdg-gallery-item" data-mdg-gallery-item data-id="'+id+'"><img src="'+escapeAttr(u)+'" data-id="'+id+'" alt="Galeri görseli"><button type="button" class="mdg-gallery-remove" data-mdg-gallery-remove="'+id+'" aria-label="Görseli galeriden kaldır">×</button></div>';
                } else {
                    html+='<div class="mdg-gallery-item is-loading" data-mdg-gallery-item data-id="'+id+'"><span>Görsel #'+id+'</span><button type="button" class="mdg-gallery-remove" data-mdg-gallery-remove="'+id+'" aria-label="Görseli galeriden kaldır">×</button></div>';
                    if(window.wp && wp.media){
                        var att=wp.media.attachment(id);
                        att.fetch().then(function(){
                            var a=att.toJSON(); galleryUrlCache[id]=attachmentThumb(a); renderGalleryIds(galleryIdList());
                        });
                    }
                }
            });
            galleryPreview.innerHTML=html;
        }
        cacheCurrentGalleryUrls();
        renderGalleryIds(galleryIdList());
        if(galleryPreview){
            galleryPreview.addEventListener('click',function(e){
                var btn=e.target.closest('[data-mdg-gallery-remove]'); if(!btn) return;
                var removeId=parseInt(btn.getAttribute('data-mdg-gallery-remove')||'0',10);
                renderGalleryIds(galleryIdList().filter(function(id){return id!==removeId;}));
            });
        }
        if(chooseGallery && window.wp && wp.media){
            chooseGallery.addEventListener('click',function(){
                // "add" mode keeps earlier selections and also lets the user add files in repeated passes.
                var frame=wp.media({title:'Gösteri galeri görsellerini seç',button:{text:'Seçilenleri Galeriye Ekle'},multiple:'add',library:{type:'image'}});
                frame.on('open',function(){
                    var sel=frame.state().get('selection');
                    galleryIdList().forEach(function(id){ var att=wp.media.attachment(id); sel.add(att); });
                });
                frame.on('select',function(){
                    var ids=galleryIdList(), seen={}; ids.forEach(function(id){seen[id]=true;});
                    frame.state().get('selection').each(function(att){
                        var a=att.toJSON(), id=parseInt(a.id,10); if(!id) return;
                        galleryUrlCache[id]=attachmentThumb(a);
                        if(!seen[id] && ids.length<30){ ids.push(id); seen[id]=true; }
                    });
                    renderGalleryIds(ids);
                });
                frame.open();
            });
        }
        if(clearGallery) clearGallery.addEventListener('click',function(){ galleryUrlCache={}; renderGalleryIds([]); });

        // V2.4 - Session repeater. Capacity is canonical per session, not per ticket type.
        var sessionList=form.querySelector('[data-mdg-session-list]');
        var addSession=form.querySelector('[data-mdg-add-session]');
        function sessionRows(){ return sessionList ? Array.prototype.slice.call(sessionList.querySelectorAll('[data-mdg-session-row]')) : []; }
        function bindSessionRow(row){
            var remove=row.querySelector('[data-mdg-remove-session]');
            if(remove){
                remove.addEventListener('click',function(){
                    var rows=sessionRows();
                    if(rows.length<=1){
                        row.querySelectorAll('input').forEach(function(input){ input.value=''; });
                        var v=currentVenue();
                        var cap=row.querySelector('[data-mdg-session-capacity]');
                        if(cap && v){ cap.value=v.default_capacity || ''; }
                        return;
                    }
                    row.remove();
                });
            }
        }
        sessionRows().forEach(bindSessionRow);
        if(addSession && sessionList){
            addSession.addEventListener('click',function(){
                var rows=sessionRows();
                var last=rows.length ? rows[rows.length-1] : null;
                var lastDate=last && last.querySelector('input[name="session_date[]"]') ? last.querySelector('input[name="session_date[]"]').value : '';
                var v=currentVenue();
                var row=document.createElement('div');
                row.className='mdg-session-row'; row.setAttribute('data-mdg-session-row','');
                row.innerHTML='<div><label>Tarih</label><input type="date" name="session_date[]" value="'+escapeAttr(lastDate)+'" required></div>'+ 
                    '<div><label>Seans saati</label><input type="time" name="session_time[]" value="" required></div>'+ 
                    '<div><label>Ortak kapasite</label><input type="number" min="1" max="100000" name="session_capacity[]" data-mdg-session-capacity value="'+escapeAttr(v ? String(v.default_capacity || '') : '')+'" placeholder="Salon kapasitesi" required></div>'+ 
                    '<div class="mdg-row-actions"><button type="button" class="button" data-mdg-remove-session>Seansı Sil</button></div>';
                sessionList.appendChild(row); bindSessionRow(row);
            });
        }

        // V2.4 - Ticket catalogue repeater. Catalogue is cloned to every session in the draft layer.
        var ticketList=form.querySelector('[data-mdg-ticket-list]');
        var addTicket=form.querySelector('[data-mdg-add-ticket]');
        function ticketRows(){ return ticketList ? Array.prototype.slice.call(ticketList.querySelectorAll('[data-mdg-ticket-row]')) : []; }
        function reindexTickets(){
            ticketRows().forEach(function(row,index){
                var enabled=row.querySelector('input[type="checkbox"]');
                var code=row.querySelector('[data-mdg-ticket-code]');
                var label=row.querySelector('input[name^="ticket_label"]');
                var price=row.querySelector('input[name^="ticket_price"]');
                var units=row.querySelector('input[name^="ticket_units"]');
                if(enabled) enabled.name='ticket_enabled['+index+']';
                if(code) code.name='ticket_code['+index+']';
                if(label) label.name='ticket_label['+index+']';
                if(price) price.name='ticket_price['+index+']';
                if(units) units.name='ticket_units['+index+']';
            });
        }
        function bindTicketRow(row){
            var remove=row.querySelector('[data-mdg-remove-ticket]');
            if(remove){ remove.addEventListener('click',function(){
                var rows=ticketRows();
                if(rows.length<=1){ return; }
                row.remove(); reindexTickets();
            }); }
        }
        ticketRows().forEach(bindTicketRow); reindexTickets();
        if(addTicket && ticketList){
            addTicket.addEventListener('click',function(){
                var row=document.createElement('div');
                row.className='mdg-ticket-row'; row.setAttribute('data-mdg-ticket-row','');
                row.innerHTML='<div class="mdg-ticket-enabled"><label>Aktif</label><input type="checkbox" value="1" checked></div>'+ 
                    '<input type="hidden" value="" data-mdg-ticket-code>'+ 
                    '<div><label>Bilet türü</label><input type="text" value="" placeholder="Örn: VIP / Kampanya"></div>'+ 
                    '<div><label>Fiyat (TL)</label><input type="number" min="0" max="1000000" step="0.01" value="" placeholder="0,00"></div>'+ 
                    '<div><label>Kapasite tüketimi</label><input type="number" min="1" max="50" value="1"></div>'+ 
                    '<div class="mdg-row-actions"><button type="button" class="button" data-mdg-remove-ticket>Bilet Türünü Sil</button></div>';
                ticketList.appendChild(row); bindTicketRow(row); reindexTickets();
            });
        }
    }

    function initVenueListFilters(){
        var box=document.querySelector('[data-mdg-venue-filters]');
        var table=document.querySelector('[data-mdg-venue-table]');
        if(!box || !table) return;
        var rows=Array.prototype.slice.call(table.querySelectorAll('[data-mdg-venue-row]'));
        var search=box.querySelector('[data-mdg-venue-search]');
        var province=box.querySelector('[data-mdg-venue-filter-province]');
        var district=box.querySelector('[data-mdg-venue-filter-district]');
        var status=box.querySelector('[data-mdg-venue-filter-status]');
        var info=box.querySelector('[data-mdg-venue-filter-info]');
        var clear=box.querySelector('[data-mdg-venue-filter-clear]');
        var count=box.querySelector('[data-mdg-venue-filter-count]');
        var noResults=document.querySelector('[data-mdg-venue-no-results]');
        function key(v){ return String(v==null?'':v).trim().replace(/\s+/g,' ').toLocaleLowerCase('tr-TR'); }
        function fillDistricts(){
            if(!district) return;
            var selected=district.value, p=province ? province.value : '', found={};
            rows.forEach(function(row){ if(p && row.getAttribute('data-province')!==p) return; var d=row.getAttribute('data-district')||''; if(d) found[d]=true; });
            district.innerHTML='<option value="">Tüm ilçeler</option>';
            Object.keys(found).sort(function(a,b){return a.localeCompare(b,'tr');}).forEach(function(d){ var o=document.createElement('option'); o.value=d; o.textContent=d; district.appendChild(o); });
            if(found[selected]) district.value=selected;
        }
        function apply(){
            var q=key(search ? search.value : ''), p=province ? province.value : '', d=district ? district.value : '', st=status ? status.value : '', inf=info ? info.value : '';
            var visible=0;
            rows.forEach(function(row){
                var show=true;
                if(q && key(row.textContent).indexOf(q)===-1) show=false;
                if(p && row.getAttribute('data-province')!==p) show=false;
                if(d && row.getAttribute('data-district')!==d) show=false;
                if(st && row.getAttribute('data-status')!==st) show=false;
                if(inf==='address_missing' && row.getAttribute('data-address-complete')!=='0') show=false;
                if(inf==='maps_missing' && row.getAttribute('data-maps-complete')!=='0') show=false;
                if(inf==='qr_missing' && row.getAttribute('data-qr-complete')!=='0') show=false;
                if(inf==='complete' && !(row.getAttribute('data-address-complete')==='1' && row.getAttribute('data-maps-complete')==='1' && row.getAttribute('data-qr-complete')==='1')) show=false;
                row.hidden=!show; if(show) visible++;
            });
            if(count) count.textContent=visible+' / '+rows.length+' salon gösteriliyor';
            if(noResults) noResults.hidden=visible!==0;
        }
        [search,district,status,info].forEach(function(el){ if(el){ el.addEventListener(el===search?'input':'change',apply); } });
        if(province){ province.addEventListener('change',function(){ if(district) district.value=''; fillDistricts(); apply(); }); }
        if(clear){ clear.addEventListener('click',function(){ if(search)search.value=''; if(province)province.value=''; if(status)status.value=''; if(info)info.value=''; fillDistricts(); if(district)district.value=''; apply(); }); }
        fillDistricts(); apply();
    }

    document.addEventListener('DOMContentLoaded', function(){
        initDistrictSelector();
        initVenueQr();
        initVenueListFilters();
        initEventForm();

        // V2.5 - FAQ repeater for public event page.
        var faqList=document.querySelector('[data-mdg-faq-list]');
        var addFaq=document.querySelector('[data-mdg-add-faq]');
        function faqRows(){ return faqList ? Array.prototype.slice.call(faqList.querySelectorAll('[data-mdg-faq-row]')) : []; }
        function bindFaqRow(row){
            var remove=row.querySelector('[data-mdg-remove-faq]');
            if(remove){ remove.addEventListener('click',function(){
                var rows=faqRows();
                if(rows.length<=1){ row.querySelectorAll('input,textarea').forEach(function(el){el.value='';}); return; }
                row.remove();
            }); }
        }
        faqRows().forEach(bindFaqRow);
        if(addFaq && faqList){ addFaq.addEventListener('click',function(){
            if(faqRows().length>=30) return;
            var row=document.createElement('div'); row.className='mdg-faq-admin-row'; row.setAttribute('data-mdg-faq-row','');
            row.innerHTML='<div><label>Soru</label><input type="text" name="faq_question[]" value="" placeholder="Örn: Oturma düzeni nasıl?"></div><div><label>Cevap</label><textarea name="faq_answer[]" rows="3" placeholder="Kısa ve net cevap..."></textarea></div><button type="button" class="button" data-mdg-remove-faq>SSS’yi Sil</button>';
            faqList.appendChild(row); bindFaqRow(row);
        }); }
    });
})();
