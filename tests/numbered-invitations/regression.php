<?php
define('ABSPATH','test');function add_action(){}function add_filter(){}
function get_post_meta($id,$key,$single=true){return array('_mdg_protocol_seat'=>'A2','ticket_code'=>'native-qr')[$key]??'';}
class Fixture_Fields{static function resolve_ticket_data($id){return array('ticket_code'=>'native-qr','ticket_id'=>'native-qr','venue_name'=>'Özay Gönlüm Salonu','event_datetime'=>'old','attendee_name'=>'','el_tc_ticket_type_element_custom'=>'adult');}}
class MDG_Ticket_Session_Datetime{static function designer_class($name){return 'Fixture_Fields';}static function correct_ticket_data($data,$id){$data['event_datetime']='8 Ekim 2026 19:30';return $data;}}
require __DIR__.'/../../docs/code-snippets/mdg-denizli-numbered-invitations.php';
function check($ok,$message){if(!$ok)throw new Exception($message);echo "PASS $message\n";}
$seats=MDG_Denizli_Invitations::seats();check(count($seats)===10&&$seats[0]==='A2'&&$seats[9]==='A11','exact requested ten seats');check(count(array_unique($seats))===10,'seat labels unique');
$d=MDG_Denizli_Invitations::data(1);check($d['ticket_code']==='native-qr'&&$d['ticket_id']==='native-qr','native entry QR payload retained');check($d['event_datetime']==='8 Ekim 2026 19:30','canonical second-session datetime retained');check($d['attendee_name']==='Ana salon · A sırası · Koltuk A2','explicit floor/row/seat');check($d['venue_name']==='Özay Gönlüm Salonu','venue retained');
