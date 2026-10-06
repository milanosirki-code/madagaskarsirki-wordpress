<?php
define('ABSPATH','test');function add_action(){}function wp_strip_all_tags($s){return strip_tags($s);}function wp_json_encode($s){return json_encode($s);}
class MDG_Ticket_Session_Datetime{static function designer_class($n){return $n==='TC_Ticket_Designer_Template'?'FakeTemplate':'FakeGenerator';}}
class FakeTemplate{public static $db=array('elements'=>array(array('type'=>'qr_code','dataField'=>'ticket_id')));public $data;function __construct($id){$this->data=self::$db;}function get_template_array(){return $this->data;}function set($k,$v){$this->data=json_decode($v,true);}}
class FakeGenerator{public static $items;static function generate_multi($items,$mode,$name){self::$items=$items;return '%PDF-fixture';}}
require __DIR__.'/../../docs/code-snippets/mdg-protocol-pdf.php';
$before=FakeTemplate::$db;$items=array(array('template'=>19,'data'=>array('ticket_id'=>'one','attendee_name'=>'Koltuk A2')),array('template'=>19,'data'=>array('ticket_id'=>'two','attendee_name'=>'Koltuk A3')));
$pdf=MDG_Protocol_PDF::render_many($items);if($pdf!=='%PDF-fixture'||count(FakeGenerator::$items)!==2||FakeGenerator::$items[1]['ticket_data']['ticket_id']!=='two'||FakeTemplate::$db!==$before)throw new Exception('Native multi composition failed');
foreach(array(array(),array_fill(0,401,$items[0])) as $invalid){try{MDG_Protocol_PDF::render_many($invalid);}catch(Exception $e){continue;}throw new Exception('Invalid batch allowed');}
echo "PASS native multi composition, per-page QR, no template save, empty/oversize guards\n";
