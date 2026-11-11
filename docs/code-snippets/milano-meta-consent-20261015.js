(function () {
  'use strict';
  var pixel = '1145349931481017';
  if (location.hostname !== 'milanosirki.com') return;
  if (typeof cmplz_has_consent !== 'function' || !cmplz_has_consent('marketing')) return;
  if (window.__milanoMetaPixelStarted) return;
  window.__milanoMetaPixelStarted = true;
  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
  fbq('set', 'autoConfig', false, pixel);
  fbq('init', pixel);
  fbq('consent', 'grant');
  fbq('trackSingle', pixel, 'PageView');
  var events = {
        '/milano-sirki-yalova-22-ekim-2026/': 'Milano Sirki Yalova 22 Ekim 2026',
'/milano-sirki-karamursel-21-ekim-2026/': 'Milano Sirki Karamürsel 21 Ekim 2026',
    '/milano-sirki-darica-23-ekim-2026/': 'Milano Sirki Darıca 23 Ekim 2026',
    '/milano-sirki-derince-24-ekim-2026/': 'Milano Sirki Derince 24 Ekim 2026'
  };
    if (events[location.pathname] && document.querySelector('main h1')) {
    fbq('trackSingle', pixel, 'ViewContent', {
      content_name: events[location.pathname],
      content_ids: [location.pathname.replace(/^\/|\/$/g, '')],
      content_type: 'product'
    });
  }
  document.addEventListener('cmplz_status_change', function () {
    fbq('consent', cmplz_has_consent('marketing') ? 'grant' : 'revoke');
  });
}());