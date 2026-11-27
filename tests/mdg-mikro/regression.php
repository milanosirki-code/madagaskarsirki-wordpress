<?php
// Isolated fixtures, no WordPress/database/portal access.
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/wp-content/plugins/mdg-mikro-taslak/draft.php';
require dirname( __DIR__, 2 ) . '/wp-content/plugins/mdg-mikro-taslak/workbook.php';
function wp_date( $format, $ts, $tz ) { return (new DateTimeImmutable( '@'.$ts ))->setTimezone( $tz )->format( $format ); }
function get_temp_dir() { return sys_get_temp_dir(); }
function is_email( $v ) { return filter_var( $v, FILTER_VALIDATE_EMAIL ); }
function sanitize_title( $v ) { return strtolower( $v ); }
function get_option( $key, $default ) { return $default; }
function get_post_meta( $id, $key, $single ) { return $id === 8 ? 'yes' : ''; }
function WC() { return (object) array( 'countries'=>new class {
    function get_states( $country ) { return array( 'TR06'=>'Ankara', 'TR46'=>'Kahramanmaraş' ); }
} ); }
function expect( $ok, $message ) { if ( ! $ok ) { throw new Exception( $message ); } }
function rejected( $code, $expected ) {
    try { $code(); } catch ( RuntimeException $e ) { expect( strpos( $e->getMessage(), $expected ) !== false, $e->getMessage() ); return; }
    throw new Exception( 'Expected refusal: ' . $expected );
}
class Example {
    public $data, $meta;
    function __construct( $data, $meta = array() ) { $this->data=$data; $this->meta=$meta; }
    function __call( $key, $args ) { return $this->data[$key] ?? ''; }
    function get_meta( $key ) { return $this->meta[$key] ?? ''; }
    function get_items( $type='line_item' ) { return $this->data['items'][$type] ?? array(); }
}
$time = new DateTimeImmutable( '2026-11-27 22:30:00+00:00' );
$item = new Example( array( 'get_quantity'=>2, 'get_product_id'=>8, 'get_total'=>'980', 'get_total_tax'=>0,
    'get_name'=>'=TEST & Bilet', 'get_variation_id'=>0 ) );
$order = new Example( array( 'get_id'=>123, 'get_date_created'=>$time, 'is_paid'=>true, 'get_date_paid'=>$time,
    'get_status'=>'completed','get_refunds'=>array(), 'get_created_via'=>'store-api', 'get_payment_method'=>'paytr_payment_gateway',
    'get_currency'=>'TRY','get_billing_first_name'=>'Deneme','get_billing_last_name'=>'Alıcı','get_billing_email'=>'test@example.invalid',
    'get_billing_city'=>'Çankaya','get_billing_state'=>'TR06','get_billing_country'=>'TR',
    'get_total'=>'980','get_total_tax'=>0, 'items'=>array( 'line_item'=>array( $item ) ) ), array( '_mdg_invoice_type'=>'individual' ) );
$before = $time->getTimestamp()-1;
$doc = MDG_Mikro_Draft::build( $order, $before );
expect( $doc['customer']['M']==='11111111111' && $doc['customer']['R']==='ANKARA', 'Buyer fields' );
expect( $doc['gross']===98000 && (int) round( $doc['lines'][0]['G']*2*100 ) ===89091, 'Discounted gross/net' );
rejected( function() use($order,$time){ MDG_Mikro_Draft::build( $order,$time->getTimestamp() ); }, 'öncesi' );
foreach (array( array('get_billing_city','','il/ilçe'),array('get_total','900','eşleşmiyor'),
    array('get_refunds',array(1),'iade'),array('get_currency','USD','para birimi') ) as $case ) {
    $o=clone $order; $o->data[$case[0]]=$case[1]; rejected(function() use($o,$before){MDG_Mikro_Draft::build($o,$before);},$case[2]);
}
foreach (array( array('_mdg_invoice_type','corporate','Alıcı türü'),array('_dof_invoice_status','sent','belge'),
    array('_mdg_invoice_status','issued','belge'),array('_mps_parasut_invoice_id','44','Paraşüt'),
    array('_mdg_invoice_tckn','12345678901','TCKN') ) as $case ) {
    $o=clone $order;$o->meta[$case[0]]=$case[1];rejected(function() use($o,$before){MDG_Mikro_Draft::build($o,$before);},$case[2]);
}
// Tickets and fees are synthetic. Package amounts come from checkout metadata.
function family_example( $item, $role, $total, $group = 'example-group' ) {
    $member = clone $item;
    $member->data['get_total'] = $total;
    $member->data['get_name'] = 'Örnek seans ' . $role;
    $member->meta = array( '_mdg_family22'=>'1', '_mdg_family22_group'=>$group,
        '_mdg_family22_component'=>$role, '_mdg_family22_packages'=>'1',
        '_mdg_family22_package_price'=>'1100', '_mdg_family22_event_id'=>'10', '_mdg_family22_session_id'=>'11' );
    return $member;
}
$adult = family_example( $item, 'adult', 980 );
$child = family_example( $item, 'child', 490 );
$fee = new Example( array( 'get_name'=>'Aile Paketi 2+2 İndirimi', 'get_total'=>-370, 'get_total_tax'=>0 ) );
$family = clone $order;
$family->data['items'] = array( 'line_item'=>array( $child, $adult ), 'fee'=>array( $fee ) );
$family->data['get_total'] = 1100;
$package = MDG_Mikro_Draft::build( $family, $before );
expect( $package['gross'] === 110000 && count( $package['lines'] ) === 1, 'Family checkout total' );
$line = $package['lines'][0];
expect( $line['E'] === 1 && $line['G'] === 1000.0 && $line['H'] === 10, 'Single package with exact tax' );
expect( strpos( $line['O'], 'Aile Paketi 2+2' ) === 0, 'Family line description' );

$mixed = clone $family;
$mixed->data['items']['line_item'][] = $item;
$mixed->data['get_total'] = 2080;
$mix = MDG_Mikro_Draft::build( $mixed, $before );
expect( $mix['gross'] === 208000 && $mix['lines'][0] === $doc['lines'][0], 'Standalone tickets retain their price' );
$second_fee = clone $fee;
$second_fee->data['get_total'] = -740;
$second_fee->data['get_name'] .= ' (2 paket)';
$two = clone $family;
$two->data['items']['fee'] = array( $second_fee );
$two->data['items']['line_item'][] = family_example( $item, 'adult', 980, 'another-group' );
$two->data['items']['line_item'][] = family_example( $item, 'child', 490, 'another-group' );
$two->data['get_total'] = 2200;
$pair = MDG_Mikro_Draft::build( $two, $before );
expect( $pair['gross'] === 220000 && count( $pair['lines'] ) === 2, 'Aggregate fee matches both groups' );
foreach ( array( array('get_total',-371), array('get_total',370), array('get_name','Başka İndirim'), array('get_total_tax',1) ) as $change ) {
    $bad = clone $family;
    $changed = clone $fee;
    $changed->data[ $change[0] ] = $change[1];
    $bad->data['items']['fee'] = array( $changed );
    rejected( function() use ($bad,$before) { MDG_Mikro_Draft::build( $bad, $before ); }, 'indirim satırı' );
}
foreach ( array( array('_mdg_family22_group','unmatched','eksik'), array('_mdg_family22_session_id','9','tutarsız'),
    array('_mdg_family22_component','adult','tutarsız'), array('_mdg_family22_packages','2','içeriğini'),
    array('_mdg_family22_package_price','100','tutarsız') ) as $change ) {
    $bad = clone $family;
    $changed = clone $child;
    $changed->meta[ $change[0] ] = $change[1];
    $bad->data['items']['line_item'] = array( $adult, $changed );
    rejected( function() use ($bad,$before) { MDG_Mikro_Draft::build( $bad, $before ); }, $change[2] );
}
foreach ( array( array('fee',array($fee,$fee),'Bilet dışı'), array('shipping',array($fee),'Teslimat'),
    array('line_item',array($item),'Bilet dışı') ) as $change ) {
    $bad = clone $family;
    $bad->data['items'][ $change[0] ] = $change[1];
    rejected( function() use ($bad,$before) { MDG_Mikro_Draft::build( $bad, $before ); }, $change[2] );
}
$doc2=$doc; $doc2['lines'][]=$doc2['lines'][0]; $doc2['customer']['O']='=Test';
// Small synthetic test template. The operator's workbook remains on their site.
$fixture = tempnam(sys_get_temp_dir(),'mikro-fixture-');
$z = new ZipArchive(); $z->open($fixture,ZipArchive::OVERWRITE);
foreach(array(1=>'V',2=>'O') as $i=>$last) {
    $sheet = '<worksheet xmlns="'.MDG_Mikro_Workbook::NS.'"><sheetData>';
    for($r=1;$r<=5;$r++){
        $sheet.='<row r="'.$r.'">';
        foreach(range('A',$last) as $col){$sheet.='<c r="'.$col.$r.'"><v>20</v></c>';}
        $sheet.='</row>';
    }
    $z->addFromString('xl/worksheets/sheet'.$i.'.xml',$sheet.'</sheetData></worksheet>');
}
$z->close();
$template = $argv[1] ?? $fixture;
$bytes=MDG_Mikro_Workbook::bytes(array($doc,$doc2),'TST',17,$time->getTimestamp(),$template);
unlink($fixture);
$tmp=tempnam(sys_get_temp_dir(),'xlsx-test-'); file_put_contents($tmp,$bytes);
$zip=new ZipArchive(); expect($zip->open($tmp)===true,'Open generated XLSX');
foreach(array(1,2) as $i){
    $dom=new DOMDocument();expect($dom->loadXML($zip->getFromName('xl/worksheets/sheet'.$i.'.xml')),'Sheet XML');
    $xp=new DOMXPath($dom);$xp->registerNamespace('s',MDG_Mikro_Workbook::NS);
    $cell=static function($r)use($xp){return $xp->evaluate("string(//s:c[@r='".$r."'])");};
    if($i===1){expect($cell('M2')==='11111111111' && $cell('O3')==='=Test' && $cell('O4')==='', 'Customer cells');
        expect($cell('E2')==='28/11/2026' && (int)$cell('D3')===18,'Timezone/series');
    }else{expect((int)$cell('A2')===1 && (int)$cell('A3')===2 && (int)$cell('B4')===2 && $cell('C5')==='', 'Line linkage');
        expect(strpos($cell('O2'),'=TEST & Bilet')===0, 'Safe XML text');}
    expect($xp->query('//s:sheetData//s:f')->length===0,'No formulas from customer fields');
}
$zip->close();unlink($tmp);
rejected(function()use($doc){MDG_Mikro_Workbook::bytes(array_fill(0,101,$doc),'TST',1,time());},'100');
echo "Mikro draft regression passed.\n";
