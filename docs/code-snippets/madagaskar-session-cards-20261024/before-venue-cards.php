<?php
/** Madagaskar session cards: scoped, read-only UI bridge. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'MDG_Session_Cards_20261024' ) ) {
    final class MDG_Session_Cards_20261024 {
        public static function render() {
            if ( is_admin() || ! class_exists( 'MDG_Public_Tickets' ) || ! class_exists( 'MDG_Sessions' ) || ! class_exists( 'MDG_Public_Event' ) ) { return; }
            $slug = (string) get_query_var( 'mdg_event_slug' );
            if ( ! is_front_page() && ! MDG_Public_Tickets::is_ticket_page_request() && '' === $slug ) { return; }
            $events = (array) MDG_Public_Tickets::active_events();
            if ( '' !== $slug ) {
                $current = MDG_Events::get_by_public_slug( sanitize_title( $slug ) );
                if ( $current && 'onsale' === (string) $current->status ) { $events[] = $current; }
            }
            $manifest = array();
            foreach ( $events as $event ) {
                $id = (int) $event->id;
                if ( isset( $manifest[$id] ) ) { continue; }
                $url = MDG_Public_Event::live_url( $event );
                if ( ! $url ) { continue; }
                $sessions = array();
                foreach ( (array) MDG_Sessions::by_event( $id ) as $session ) {
                    if ( empty( $session->start_at ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', (string) $session->start_at ) ) { continue; }
                    try {
                        $start = new DateTimeImmutable( (string) $session->start_at, new DateTimeZone( 'UTC' ) );
                        $end = ! empty( $session->end_at ) ? new DateTimeImmutable( (string) $session->end_at, new DateTimeZone( 'UTC' ) ) : $start->modify( '+60 minutes' );
                    } catch ( Exception $e ) { continue; }
                    list( $date, $time ) = MDG_Sessions::local_parts( $session->start_at );
                    $sessions[] = array( 'id'=>(int)$session->id, 'time'=>$time, 'start'=>$start->format( DATE_ATOM ), 'end'=>$end->format( DATE_ATOM ), 'closed'=>MDG_Sessions::sales_closed_by_time( $session ) || ( isset( $session->status ) && 'onsale' !== $session->status ) );
                }
                $manifest[$id] = array( 'id'=>$id, 'title'=>(string)$event->title, 'url'=>$url, 'sessions'=>$sessions );
            }
            $data = array( 'serverNow'=>time()*1000, 'events'=>array_values( $manifest ) );
            ?>
            <script type="application/json" id="mdg-session-cards-data"><?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
            <style id="mdg-session-cards-style">.ms-session-card-list{display:flex!important;flex-wrap:wrap;gap:10px!important}
.ms-session-card-list .ms-session-time-card{display:inline-flex!important;flex-direction:column;align-items:center;justify-content:center;gap:5px;min-width:110px;min-height:84px;padding:12px 15px!important;background:#eaf2ff!important;border:2px solid #1e4b8f!important;border-radius:12px!important;color:#12366a!important;text-decoration:none!important;line-height:1.35}
.ms-session-card-list .ms-session-time-card strong{color:inherit!important;font-size:26px!important;font-weight:850!important}
.ms-session-card-list .ms-session-time-card small{color:inherit!important;font-size:12px!important;font-weight:750!important;max-width:150px;text-align:center}
.ms-session-card-list .ms-session-time-card[data-state="started"]{background:#fff0d5!important;color:#763400!important;border-color:#ad4b00!important}
.ms-session-card-list .ms-session-time-card:is([data-state="ended"],[data-state="closed"]){background:#f0f0f0!important;color:#545454!important;border-color:#aaa!important}
.ms-session-card-list a.ms-session-time-card:hover{background:#12366a!important;color:white!important}
.ms-session-time-card:focus-visible{outline:3px solid #b51e36;outline-offset:4px}
.ms-session-help{font-size:14px!important;font-weight:750!important;color:#17243a!important;margin:8px 0 12px!important}
.mdg-ticket-event-card .ms-session-help{color:#f4f1e8!important}
.mdg-session-picker{display:flex!important;flex-wrap:wrap!important;gap:12px!important}
.mdg-session-picker .mdg-session-option{min-width:120px!important;min-height:98px!important;display:flex!important;flex-direction:column;align-items:center;justify-content:center;gap:4px;background:#eaf2ff!important;color:#12366a!important;border:2px solid #1e4b8f!important;border-radius:12px!important;padding:14px 18px!important}
.mdg-session-picker .mdg-session-option strong{font-size:28px!important;font-weight:850!important;color:inherit!important}
.mdg-session-picker .mdg-session-option small{font-size:12px!important;font-weight:750!important;color:inherit!important}
.mdg-session-picker .mdg-session-option.is-selected{background:#12366a!important;color:#fff!important;outline:3px solid #b51e36;outline-offset:3px}
.mdg-session-picker .mdg-session-option[data-state="started"]{background:#fff0d5!important;color:#763400!important;border-color:#ad4b00!important}
.mdg-session-picker .mdg-session-option:is([data-state="closed"],[data-state="ended"]){background:#f0f0f0!important;color:#545454!important;border-color:#aaa!important}
.mdg-session-picker .mdg-session-option:disabled{opacity:1!important;cursor:not-allowed}
.ms-selected-session-note{padding:12px 16px;background:#b51e36;color:#fff!important;font-size:16px;font-weight:800;border-radius:9px;margin:12px 0 18px!important}
#bilet-secimi{scroll-margin-top:45px}
#bilet-secimi.ms-requested-session{border:3px solid #b51e36!important}
@media(max-width:480px){.ms-session-card-list .ms-session-time-card{flex:1 1 85px;min-width:85px;padding:10px 8px!important}.mdg-session-picker .mdg-session-option{flex:1 1 90px;min-width:90px!important;padding:12px 8px!important}}
</style>
            <script id="mdg-session-cards-ui">(function(){
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
</script>
            <?php
        }
    }
    add_action( 'wp_footer', array( 'MDG_Session_Cards_20261024', 'render' ), 99 );
}
