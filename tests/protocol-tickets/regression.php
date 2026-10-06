<?php
define('ABSPATH','test');function add_action(){}function add_filter(){}function sanitize_text_field($s){return trim(strip_tags((string)$s));}function remove_accents($s){return strtr($s,array('ö'=>'o','ü'=>'u','ı'=>'i','ğ'=>'g','ş'=>'s','ç'=>'c','İ'=>'I'));}
$GLOBALS['opts']=array();function get_option($k,$d=array()){return $GLOBALS['opts'][$k]??$d;}function get_current_user_id(){return 1;}
class MDG_Ticket_Session_Datetime{static function designer_class($n){return 'FixtureFields';}static function correct_ticket_data($data,$id){$data['event_datetime']='6 Kasım 2026 19:30';return $data;}}
class FixtureFields{static function resolve_ticket_data($id){return array('ticket_code'=>'native-code','ticket_id'=>'native-entry-payload','venue_name'=>'Salon','attendee_name'=>'','event_datetime'=>'old');}}
require __DIR__.'/../../docs/code-snippets/mdg-protocol-ticket-menu.php';
$n=0;function check($ok,$label){global $n;if(!$ok)throw new Exception($label);$n++;echo "PASS $label\n";}
function rejects($callback,$label){try{$callback();}catch(Exception $e){check(true,$label);return;}throw new Exception('Expected rejection: '.$label);}
$c='MDG_Protocol_Tickets';
check(count($c::participants('plain','10','',''))===10,'unnumbered requested count');
foreach(array('0','-1','1.5','101','foo') as $v)rejects(function()use($c,$v){$c::participants('plain',$v,'','');},'invalid count '.$v);
$p=$c::participants('named','',"Ahmet Yılmaz\nAyşe Öztürk",'');check(count($p)===2&&$p[1]['name']==='Ayşe Öztürk'&&$p[0]['seat']==='','manual named list');
$p=$c::participants('seat','',"A2\nA3\nA4",'Ana salon');check(count($p)===3&&$p[0]['seat']==='A2'&&$p[2]['section']==='Ana salon','numbered seats');
rejects(function()use($c){$c::participants('seat','',"A2\nA02",'Ana salon');},'duplicate normalized seats blocked');
rejects(function()use($c){$c::participants('seat','','A2','');},'section required for seat');
rejects(function()use($c){$c::participants('named','','','');},'empty names blocked');
rejects(function()use($c){$c::participants('named','',implode("\n",array_fill(0,101,'A B')),'');},'overlong list blocked');
$p=$c::participants('named','','','',array(array('name'=>'Ahmet Yılmaz','section'=>'Balkon','seat'=>'A2')));check($p[0]['section']==='Balkon','named and numbered combined');
$rows=$c::table_rows(array(array("\xEF\xBB\xBFad_soyad",'bolum','koltuk'),array('Ahmet Yılmaz','Ana salon','A2'),array('','','')));check(count($rows)===1&&$rows[0]['seat']==='A2','BOM headers and empty rows');
$rows=$c::table_rows(array(array('Koltuk','Ad Soyad','Bölüm'),array('B5','Ayşe Öztürk','Balkon')));check($rows[0]['name']==='Ayşe Öztürk'&&$rows[0]['section']==='Balkon','Turkish reordered headers');
rejects(function()use($c){$c::table_rows(array(array('unknown'),array('abc')));},'missing name column rejected');
rejects(function()use($c){$c::seat('<script>');},'invalid seat characters');
$d=$c::ticket_data(1,array('kind'=>'protocol'),array('name'=>'Ahmet Yılmaz','seat'=>'A2','section'=>'Ana salon'));check($d['ticket_code']==='native-code'&&$d['ticket_id']==='native-entry-payload','entry QR data unchanged');check($d['event_datetime']==='6 Kasım 2026 19:30','canonical session date retained');check(strpos($d['attendee_name'],'Ahmet Yılmaz')!==false&&strpos($d['attendee_name'],'Ana salon · A2')!==false,'PDF includes name and seat');
$d=$c::ticket_data(1,array('kind'=>'free'),array('name'=>'','seat'=>'','section'=>''));check($d['el_tc_ticket_type_element_custom']==='ÜCRETSİZ BİLET'&&strpos($d['attendee_name'],'Numarasız')!==false,'free unnamed PDF label');
$id=str_repeat('a',32);$GLOBALS['opts'][$c::PREFIX.$id]=array('id'=>$id,'user'=>1,'state'=>'ready','orders'=>array(99=>123));check($c::issue_batch($id)['orders'][99]===123,'ready batch replay does not create orders');
$GLOBALS['opts'][$c::PREFIX.$id]['user']=2;rejects(function()use($c,$id){$c::issue_batch($id);},'another user cannot issue draft');

foreach(array('5320000000','0532 000 00 00','+90 (532) 000-00-00','905320000000') as $phone)check($c::phone($phone)==='905320000000','Turkish phone normalization '.$phone);
check($c::phone('+44 7700 900000')==='447700900000','international phone');check($c::phone('')==='','optional phone');
foreach(array('abc','0532','90;bad','1e12','+000000000','1234567890') as $phone)rejects(function()use($c,$phone){$c::phone($phone);},'invalid phone '.$phone);
$p=$c::participants('named','',"Ahmet Yılmaz | 05320000000\nAyşe Öztürk",'');check($p[0]['phone']==='905320000000'&&$p[1]['phone']==='','manual names and phones');
$rows=$c::table_rows(array(array('Ad Soyad','Cep Telefonu'),array('Kontrol','+905320000000')));$p=$c::participants('named','','','',$rows);check($p[0]['phone']==='905320000000','Excel phone column parsed and validated');
rejects(function()use($c){$c::participants('named','','','',array(array('name'=>'Kontrol','phone'=>'foo')));},'bad Excel phone blocks preview');
$b=array('id'=>str_repeat('c',32),'token'=>str_repeat('d',48),'state'=>'ready','kind'=>'protocol','event_name'=>'Denizli','venue'=>'Salon','sessions'=>array(array('time'=>'08.10.2026 17:30')),'recipient_phone'=>'905320000001','people'=>array(array('name'=>'Birinci','phone'=>'905320000000'),array('name'=>'İkinci','phone'=>'905320000002')));
$key=$c::person_key($b,0);check($c::authorized_person($b,$key,'0')===0,'recipient key grants own index');
rejects(function()use($c,$b,$key){$c::authorized_person($b,$key,'1');},'recipient cannot change index');rejects(function()use($c,$b,$key){$c::authorized_person($b,$key,null);},'recipient cannot open entire package');rejects(function()use($c,$b){$c::authorized_person($b,$b['token'],'0');},'root key not accepted as personal key');
check($c::authorized_person($b,$b['token'],null)===null,'package key retains full access');
foreach(array('-1','01','2','x') as $target)rejects(function()use($c,$b,$target){$c::target($b,$target);},'invalid sharing target '.$target);
check($c::target($b,'all')==='all'&&$c::target($b,'1')===1,'valid sharing targets');$draft=$b;$draft['state']='preview';rejects(function()use($c,$draft){$c::target($draft,'all');},'unissued package cannot be shared');
function home_url($p){return 'https://example.test'.$p;}function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
$url=$c::whatsapp_url($b,0);parse_str(parse_url($url,PHP_URL_QUERY),$q);check(strpos($url,'https://wa.me/905320000000?text=')===0&&strpos($q['text'],'kisi=0')!==false&&strpos($q['text'],$b['token'])===false&&strpos($q['text'],'İkinci')===false,'individual WhatsApp uses restricted link');check(strpos($q['text'],"\n")!==false&&strpos($q['text'],'08.10.2026 17:30')!==false,'WhatsApp readable message and session');
$url=$c::whatsapp_url($b,'all');check(strpos($url,'https://wa.me/905320000001?text=')===0,'package recipient number');$b['people'][0]['phone']='';rejects(function()use($c,$b){$c::whatsapp_url($b,0);},'missing recipient phone blocked');
echo "All $n protocol checks passed.\n";

