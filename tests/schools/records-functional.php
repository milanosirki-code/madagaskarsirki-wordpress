<?php
/** Real parser/writer/validation and write boundary tests; no production fixtures. */
define('ABSPATH', __DIR__.'/'); define('MINUTE_IN_SECONDS',60); define('ARRAY_A','ARRAY_A');
class WP_Error { private $message; public function __construct($code,$message){$this->message=$message;} public function get_error_message(){return $this->message;} }
function is_wp_error($x){return $x instanceof WP_Error;}
function add_action(...$args){} function add_filter(...$args){} function register_activation_hook(...$args){}
function plugin_dir_path($p){return dirname($p).'/';}
function absint($x){return abs((int)$x);} function sanitize_textarea_field($x){return trim((string)$x);}
function current_time($x){return '2026-10-08 06:00:00';} function get_current_user_id(){return 1;}
function wp_tempnam($x){return tempnam(sys_get_temp_dir(),'school-records-');}
require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-okul-tanitim/madagaskar-okul-tanitim.php';
function check($ok,$message){if(!$ok) throw new RuntimeException($message);echo "PASS $message\n";}
check(Mad_Okul_Records::count_value('')===null,'unknown remains NULL');
check(Mad_Okul_Records::count_value('0')===0,'known zero retained');
check(is_wp_error(Mad_Okul_Records::count_value('-10')),'negative student count rejected');
check(is_wp_error(Mad_Okul_Records::count_value('30 öğrenci')),'mixed text count rejected');
check(Mad_Okul_Records::balance('100','1000','700','20')===380,'stock reconciliation with carry and waste');
check(is_wp_error(Mad_Okul_Records::balance('0','10','11','0')),'oversold physical stock rejected');
check(is_wp_error(Mad_Okul_Records::balance('-1','10','0','0')),'negative stock input rejected');
check(is_wp_error(Mad_Okul_Records::year('2026-2027')),'ambiguous year rejected');

$grid=[['IL_ADI','ILCE_ADI','KURUM_ADI','OGRENCI_SAYISI','VERI_YILI'],['Uşak','Merkez','TEST İLKOKULU','306','2026'],['Uşak','Merkez','=HYPERLINK("https://example.com")','','2026']];
$bytes=Mad_Okul_Records::xlsx_bytes($grid); check(is_string($bytes),'real XLSX bytes generated');
$path=wp_tempnam('roundtrip');file_put_contents($path,$bytes);
$sheets=Mad_Okul_Records::xlsx_sheets($path);check(!is_wp_error($sheets),'OOXML parses own export');
$rows=Mad_Okul_Records::file_rows($path,'xlsx');check(count($rows)===2,'export template roundtrip rows');
check($rows[0]['OGRENCI_SAYISI']==='306','numeric student count roundtrip');
check($rows[1]['KURUM_ADI']==='=HYPERLINK("https://example.com")','formula-like text stays literal');
$z=new ZipArchive();$z->open($path);check(strpos($z->getFromName('xl/worksheets/sheet1.xml'),'<f>')===false,'export never creates Excel formulas');$z->close();
file_put_contents($path,"IL_ADI;ILCE_ADI;KURUM_ADI;OGRENCI_SAYISI\nUşak;Merkez;TEST İLKOKULU;306\n");
check(count(Mad_Okul_Records::file_rows($path,'csv'))===1,'semicolon CSV accepted');
file_put_contents($path,"IL_ADI,ILCE_ADI,KURUM_ADI,OGRENCI_SAYISI\nUşak,Merkez,TEST İLKOKULU,306\n");
check(count(Mad_Okul_Records::file_rows($path,'csv'))===1,'comma CSV accepted');

// Minimal multi-sheet analogue of the supplied grouped research workbook.
$bytes=Mad_Okul_Records::xlsx_bytes([['İl','İlçe','Ziyaret Noktası / Kampüs','Kurum Adları','Öğrenci Sayısı (Web)'],['UŞAK','MERKEZ','TEST KAMPÜSÜ','TEST İLKOKULU + TEST ORTAOKULU','795']]);file_put_contents($path,$bytes);
$z=new ZipArchive();$z->open($path);$z->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Temiz Liste" sheetId="1" r:id="rId1"/><sheet name="Birleştirme Kontrolü" sheetId="2" r:id="rId2"/></sheets></workbook>');
$z->addFromString('xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Target="worksheets/sheet2.xml"/></Relationships>');
$comp=Mad_Okul_Records::xlsx_bytes([['Ziyaret Noktası / Kampüs','Birleşen Kurum','Öğrenci Sayısı'],['TEST KAMPÜSÜ','TEST İLKOKULU','295'],['TEST KAMPÜSÜ','TEST ORTAOKULU','500']]);$p2=wp_tempnam('component');file_put_contents($p2,$comp);$z2=new ZipArchive();$z2->open($p2);$z->addFromString('xl/worksheets/sheet2.xml',$z2->getFromName('xl/worksheets/sheet1.xml'));$z2->close();$z->close();unlink($p2);
$rows=Mad_Okul_Records::file_rows($path,'xlsx');check(count($rows)===2,'grouped campus expands to school records');
check(array_column($rows,'OGRENCI_SAYISI')===['295','500'],'campus aggregate never copied to both schools');
$school=(object)['id'=>7,'il'=>'Uşak','ilce'=>'Merkez','kurum_adi'=>'TEST İLKOKULU','ogrenci_sayisi'=>100,'student_data_year'=>2026];
$matched=Mad_Okul_Records::match_rows($rows,[$school]);check($matched[0]['error']==='' && $matched[1]['error']!=='','only exact existing schools accepted');
$duplicate=clone $school;$duplicate->id=8;
check(Mad_Okul_Records::match_rows([$rows[0]],[$school,$duplicate])[0]['error']!=='','ambiguous duplicate school blocked');
$again=Mad_Okul_Records::match_rows([$rows[0],$rows[0]],[$school]);check($again[1]['error']!=='','duplicate input row blocked');
unlink($path);

class FakeDB {
    public $prefix='wp_'; public $updates=[]; public $inserts=[]; public $queries=[]; public $old=null; public $fail=false;
    public function prepare($sql,...$args){return $sql;}
    public function query($sql){$this->queries[]=$sql;return 1;}
    public function get_row($sql,$format){return $this->old;}
    public function update($table,$data,$where){$this->updates[]=$data;return 1;}
    public function insert($table,$data){$this->inserts[]=$data;return $this->fail ? false : 1;}
}
$wpdb=new FakeDB();Mad_Okul_Records::save_student($school,['ogrenci_sayisi'=>120],2027,'MEB yazısı');
check($wpdb->updates[0]['student_count']===120 && $wpdb->updates[0]['ogrenci_sayisi']===120,'both current count fields stay compatible');
check($wpdb->inserts[0]['data_year']===2027,'student snapshot retained by year');
$wpdb=new FakeDB();Mad_Okul_Records::save_student($school,['ogrenci_sayisi'=>80],2025,'Eski yıl');
check(count($wpdb->updates)===0 && $wpdb->inserts[0]['data_year']===2025,'historical input cannot overwrite newer current record');
$wpdb=new FakeDB();Mad_Okul_Records::save_student($school,['ogrenci_sayisi'=>''],2026);
check($wpdb->updates[0]['student_count']===100,'blank manual input never erases existing known count');
$wpdb=new FakeDB();$wpdb->fail=true;$saved=Mad_Okul_Records::save_student($school,['ogrenci_sayisi'=>120],2027);
check(is_wp_error($saved) && in_array('ROLLBACK',$wpdb->queries,true),'history insert failure rolls back school update');
$wpdb=new FakeDB();$wpdb->old=['student_count'=>120,'data_status'=>'','source_type'=>'','source_url'=>'','source_note'=>'','verified_at'=>null];
Mad_Okul_Records::save_student($school,['ogrenci_sayisi'=>120],2027);
check(count($wpdb->inserts)===0,'identical yearly snapshot idempotent');
echo "School records functional tests passed.\n";
