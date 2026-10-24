(function(){
  'use strict';
  var data=JSON.parse(document.getElementById('mdg-session-cards-data').textContent);
  var began=performance.now();
  function now(){return Math.max(Date.now(),data.serverNow+performance.now()-began);}
  function sessionState(s,at){
    var start=Date.parse(s.start),end=Date.parse(s.end);
    if(!Number.isFinite(start))return 'closed';
    if(Number.isFinite(end) && at>=end)return 'ended';
    if(at>=start)return 'started';
    return s.closed?'closed':'upcoming';
  }
  function label(state){return state==='started'?'Seans başladı · Satış kapandı':state==='ended'?'Tamamlandı':state==='closed'?'Satış kapandı':'Bilet seç →';}
  function eventFor(url){try{var u=new URL(url,location.origin);if(u.origin!==location.origin)return null;return data.events.find(function(e){return new URL(e.url).pathname===u.pathname;})||null;}catch(e){return null;}}
  function buildCards(){
    document.querySelectorAll('.msh-event,.mdg-ticket-event-card').forEach(function(card){
      var source=card.querySelector('a[href*="/etkinlik/"]');
      var container=card.querySelector('.msh-sessions,.mdg-ticket-event-card__sessions');
      if(!source||!container)return;
      var event=eventFor(source.href);if(!event)return;
      var states=event.sessions.map(function(s){return sessionState(s,now());});
      var signature=event.id+':'+states.join(',');if(container.dataset.msSessionSignature===signature)return;
      container.dataset.msSessionSignature=signature;container.classList.add('ms-session-card-list');container.replaceChildren();
      if(!card.querySelector('.ms-session-help')){var help=document.createElement('p');help.className='ms-session-help';help.textContent='Seans saatine dokunun, doğrudan biletinizi seçin.';container.before(help);}
      event.sessions.forEach(function(s,i){
        var state=states[i],open=state==='upcoming';
        var node=document.createElement(open?'a':'span');node.className='ms-session-time-card';node.dataset.state=state;
        if(open){var url=new URL(event.url);url.searchParams.set('seans',String(s.id));url.hash='bilet-secimi';node.href=url.href;node.setAttribute('aria-label',event.title+' '+s.time+' seansı — Bilet seç');}
        else node.setAttribute('aria-disabled','true');
        var time=document.createElement('strong');time.textContent=s.time;
        var status=document.createElement('small');status.textContent=label(state);node.append(time,status);container.append(node);
      });
    });
  }
  function enhanceEvent(){
    var card=document.getElementById('bilet-secimi'),picker=document.querySelector('[data-mdg-session-picker]');
    var event=eventFor(location.href);if(!card||!picker||!event)return;
    if(!card.querySelector('.ms-selected-session-note')){var note=document.createElement('p');note.className='ms-selected-session-note';note.setAttribute('role','status');picker.before(note);}
    event.sessions.forEach(function(s){
      var button=picker.querySelector('[data-session-id="'+s.id+'"]');
      if(!button){
        var state=sessionState(s,now());if(state==='upcoming')return;
        button=document.createElement('button');button.type='button';button.className='mdg-session-option';button.dataset.sessionId=String(s.id);button.dataset.sessionTime=s.time;
        var time=document.createElement('strong');time.textContent=s.time;button.append(time);picker.append(button);
      }
      button.dataset.msStart=s.start;button.dataset.msEnd=s.end;button.dataset.msClosed=s.closed?'1':'0';
      if(!button.querySelector('.ms-session-label')){var text=document.createElement('small');text.className='ms-session-label';button.append(text);}
      picker.append(button);
    });
    function refreshEvent(){
      var selected=picker.querySelector('.mdg-session-option.is-selected');
      event.sessions.forEach(function(s){var b=picker.querySelector('[data-session-id="'+s.id+'"]');if(!b)return;var state=sessionState(s,now());b.dataset.state=state;b.disabled=state!=='upcoming';b.querySelector('.ms-session-label').textContent=state==='upcoming'?(b===selected?'Seçili seans':'Seçmek için dokunun'):label(state);});
      var note=card.querySelector('.ms-selected-session-note');
      note.textContent=selected?(selected.disabled?'Bu seansın satışı kapandı. Başka bir seans seçin.':'Seçtiğiniz seans · '+selected.dataset.sessionTime):'Seansınızı seçin';
    }
    picker.addEventListener('click',function(){setTimeout(refreshEvent,0);});
    var requested=new URLSearchParams(location.search).get('seans');
    function selectRequested(){
      refreshEvent();if(!requested||!/^\d+$/.test(requested))return;
      var target=picker.querySelector('[data-session-id="'+requested+'"]');
      if(!target)return;
      if(!target.disabled){target.click();card.classList.add('ms-requested-session');}
      else card.querySelector('.ms-selected-session-note').textContent='Seçtiğiniz seansın satışı kapandı. Lütfen başka bir saat seçin.';
      card.scrollIntoView({behavior:'smooth',block:'start'});
    }
    var checkout=document.querySelector('[data-mdg-checkout-preview]');
    if(checkout)checkout.addEventListener('click',function(ev){var b=picker.querySelector('.is-selected');if(b && sessionState({start:b.dataset.msStart,end:b.dataset.msEnd,closed:b.dataset.msClosed==='1'},now())!=='upcoming'){ev.preventDefault();ev.stopImmediatePropagation();refreshEvent();}},true);
    refreshEvent();setTimeout(selectRequested,0);window.addEventListener('pageshow',function(){setTimeout(selectRequested,0);});
    document.addEventListener('visibilitychange',refreshEvent);setInterval(refreshEvent,15000);
  }
  function init(){buildCards();enhanceEvent();setInterval(buildCards,15000);document.addEventListener('visibilitychange',buildCards);}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
