<?php
define('ABSPATH',__DIR__);
function add_filter(...$a) {}
require __DIR__.'/../../integrations/milano/pdf-layout-20261018/milano-layout-fields.php';
$checks=0;
function check($ok,$message){global $checks;$checks++;if(!$ok)throw new Exception($message);}
$terms="Cumhuriyet Mahallesi, Şehit Öğretmen Ayşegül Sokak\nİkinci adres satırı\n\nBilgi & Destek WhatsApp Hattı:\n+90 312 911 37 10\n\nLütfen seans saatinden en az 30 dakika önce salonda hazır bulununuz.\n\nKoltuk numarası bulunmamaktadır.";
foreach(array('Çocuk','Yetişkin','Aile Paketi') as $kind){
 $d=array('event_name'=>'Milano Sirki — Karamürsel','event_terms'=>$terms,'attendee_name'=>'Şükran İpek Çağrı Ayşegül Öztürk Yılmaz Çetinkaya','ticket_id'=>'TEST000001','ticket_code'=>'TEST000001','ticket_type'=>$kind,'event_datetime'=>'21 Ekim 2026 19:30 - 20:30');
 $a=milano_pdf_layout_fields_v3($d);
 foreach($d as $k=>$v)check($a[$k]===$v,'Original field changed: '.$k);
 check($a['milano_layout_address']==="Cumhuriyet Mahallesi, Şehit Öğretmen Ayşegül Sokak\nİkinci adres satırı",'Full address preserved');
 check(strpos($a['milano_layout_notes'],'Hattı: +90 312 911 37 10')!==false,'Phone preserved');
 check(milano_pdf_layout_fields_v3($a)===$a,'Idempotent display derivation');
 $d['event_name']='Madagaskar Sirki — Ankara';check(milano_pdf_layout_fields_v3($d)===$d,'Madagaskar unchanged');
}
check(milano_pdf_layout_fields_v3(null)===null,'Non-array unchanged');
$before=json_decode(file_get_contents(__DIR__.'/../../integrations/milano/pdf-layout-20261018/templates-before.json'),true);
$after=json_decode(file_get_contents(__DIR__.'/../../integrations/milano/pdf-layout-20261018/templates-after.json'),true);
check(array_column($after,'id')===array('28','29','31','32','33'),'Only known Milano IDs');
foreach($after as $i=>$row){
 $a=json_decode($row['template_data'],true);$b=json_decode($before[$i]['template_data'],true);$old=array_column($b['elements'],null,'id');
 check($a['width']===595&&$a['height']===420,'Original size preserved');
 check($a['background']===$b['background'],'Navy background preserved');
 foreach($a['elements'] as $e){
  check($e['x']>=0&&$e['y']>=0&&$e['x']+$e['width']<=595&&$e['y']+$e['height']<=420,'Element inside page');
  if(($e['type']??'')==='qr_code')check($e===$old[$e['id']],'Entry QR configuration identical');
  if(($e['type']??'')==='custom_image')check($e['src']===$old[$e['id']]['src'],'Logo/map image source identical');
 }
 check($row['settings']===$before[$i]['settings'],'Settings unchanged');
}
echo "PASS $checks Milano layout protection assertions\n";
