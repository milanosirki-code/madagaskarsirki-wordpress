<?php
define('ABSPATH','test');function add_action(){}function wp_strip_all_tags($v){return strip_tags($v);}
require __DIR__.'/../../docs/code-snippets/mdg-protocol-pdf.php';
$n=0;function check($v,$label){global $n;if(!$v)throw new Exception($label);$n++;echo "PASS $label\n";}
$native=array('elements'=>array(array('type'=>'qr_code','dataField'=>'ticket_id','errorCorrectionLevel'=>'M','padding'=>5),array('type'=>'custom_image','mdgVenueQr'=>true,'src'=>'https://example.test/map.png')));
$l=MDG_Protocol_PDF::layout($native);check($native['elements'][0]['padding']===5,'native input not mutated');
$fields=array_column($l['elements'],null,'dataField');check(isset($fields['ticket_id'])&&$fields['ticket_id']['errorCorrectionLevel']==='M','native entry QR field retained');check($fields['venue_name']['y']+$fields['venue_name']['height']<$fields['protocol_address']['y'],'venue and address separated');check($fields['protocol_address']['y']+$fields['protocol_address']['height']<$fields['attendee_name']['y'],'address and attendee separated');
check(!isset($fields['event_terms']),'raw commercial terms not rendered');$images=array_values(array_filter($l['elements'],function($e){return ($e['type']??'')==='custom_image';}));check($images[0]['src']==='https://example.test/map.png','location QR image retained');
$d=array('ticket_id'=>'native-payload','ticket_code'=>'native-code','attendee_name'=>'Ana salon · Koltuk A9','event_terms'=>'Adres: Örnek Sokak<br>Bilgi & Destek WhatsApp Hattı: 123<br>Koltuk numarası bulunmamaktadır. Fatura Bilgilendirmesi');
$c=MDG_Protocol_PDF::clean_data($d);check($c['protocol_address']==='Örnek Sokak','address stripped from commercial boilerplate');check($c['ticket_id']===$d['ticket_id']&&$c['ticket_code']===$d['ticket_code'],'QR payload unchanged');check(strpos($c['protocol_terms'],'Fatura')===false,'invoice text excluded');
$d['attendee_name']='Numarasız / serbest oturma';$c=MDG_Protocol_PDF::clean_data($d);check(strpos($c['protocol_terms'],'Numarasız bilet')!==false&&strpos($c['protocol_terms'],'koltuk bilginizi')===false,'unnumbered terms appropriate');
echo "All $n PDF layout checks passed.\n";
