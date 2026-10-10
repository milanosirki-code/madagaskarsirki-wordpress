<?php
if (!defined('ABSPATH')) exit;

/** School-only exports, reviewed student imports and annual paper stock. */
class Mad_Okul_Records {
    public static function hooks() {
        add_action('admin_init', [__CLASS__, 'upgrade']);
        add_action('admin_menu', [__CLASS__, 'menus'], 30);
        add_action('admin_notices', [__CLASS__, 'help']);
        foreach (['export', 'preview', 'accept', 'stock_save'] as $action) {
            add_action('admin_post_mad_okul_records_'.$action, [__CLASS__, $action]);
        }
    }
    public static function history_table() { global $wpdb; return $wpdb->prefix.'mad_okul_student_history'; }
    public static function stock_table() { global $wpdb; return $wpdb->prefix.'mad_okul_print_stock'; }
    public static function upgrade() {
        if (get_option('mad_okul_records_schema') === '1') return;
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $charset=$wpdb->get_charset_collate();
        $history=self::history_table(); $stock=self::stock_table();
        dbDelta("CREATE TABLE $history (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            school_id bigint unsigned NOT NULL,
            data_year int unsigned NOT NULL,
            student_count int unsigned NULL,
            data_status varchar(30) NOT NULL DEFAULT '',
            source_type varchar(100) NOT NULL DEFAULT '',
            source_url text NULL,
            source_note text NULL,
            verified_at datetime NULL,
            recorded_by bigint unsigned NOT NULL DEFAULT 0,
            recorded_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY school_year (school_id,data_year)
        ) ENGINE=InnoDB $charset;");
        dbDelta("CREATE TABLE $stock (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint unsigned NOT NULL,
            stock_year int unsigned NOT NULL,
            material varchar(30) NOT NULL DEFAULT 'davetiye',
            opening_qty int unsigned NOT NULL DEFAULT 0,
            printed_qty int unsigned NOT NULL DEFAULT 0,
            distributed_qty int unsigned NOT NULL DEFAULT 0,
            waste_qty int unsigned NOT NULL DEFAULT 0,
            notes text NULL,
            updated_by bigint unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY program_year_material (program_id,stock_year,material)
        ) ENGINE=InnoDB $charset;");
        $main=mad_okul_table();
        $cols=$wpdb->get_col("SHOW COLUMNS FROM $main",0);
        if (!in_array('student_data_year',$cols,true)) $wpdb->query("ALTER TABLE $main ADD student_data_year int unsigned NULL");
        if (!in_array('student_source_note',$cols,true)) $wpdb->query("ALTER TABLE $main ADD student_source_note text NULL");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$history)) === $history
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$stock)) === $stock
            && count(array_intersect(['student_source_note','student_data_year'],$wpdb->get_col("SHOW COLUMNS FROM $main",0)))===2) update_option('mad_okul_records_schema','1',false);
    }
    private static function guard() { if (!current_user_can('manage_options')) wp_die('Bu işlem için yönetici yetkisi gerekli.'); }
    public static function year($value) {
        if (!preg_match('/^20\d{2}$/D',(string)$value)) return new WP_Error('year','Veri yılı 2000–2099 arasında dört rakam olmalı.');
        return (int)$value;
    }
    public static function count_value($value) {
        $s=trim((string)$value);
        if ($s==='') return null;
        if (!preg_match('/^\d{1,7}$/D',$s) || (int)$s>1000000) return new WP_Error('count','Öğrenci sayısı 0–1000000 arasında tam sayı olmalı; bilinmiyorsa boş bırakın.');
        return (int)$s;
    }
    public static function balance($opening,$printed,$distributed,$waste) {
        foreach ([$opening,$printed,$distributed,$waste] as $v) if (!preg_match('/^\d{1,9}$/D',(string)$v)) return new WP_Error('quantity','Adetler negatif olmayan tam sayı olmalı.');
        $left=(int)$opening+(int)$printed-(int)$distributed-(int)$waste;
        return $left<0 ? new WP_Error('balance','Dağıtılan + fire, devreden + basılan toplamını aşamaz.') : $left;
    }
    public static function save_student($school,$data,$year,$note='') {
        global $wpdb;
        $year=self::year($year); if (is_wp_error($year)) return $year;
        $count=self::count_value($data['ogrenci_sayisi'] ?? ''); if (is_wp_error($count)) return $count;
        // Missing input is not an instruction to erase a known student count.
        if ($count===null && ($school->ogrenci_sayisi!==null || isset($school->student_count))) return true;
        $now=current_time('mysql');
        $data['ogrenci_sayisi']=$count; $data['student_count']=$count;
        $data['student_data_year']=$year; $data['student_source_note']=sanitize_textarea_field($note); $data['updated_at']=$now;
        $latest_year=isset($school->student_data_year) ? (int)$school->student_data_year : 0;
        $snapshot=['school_id'=>(int)$school->id,'data_year'=>$year,'student_count'=>$count,
            'data_status'=>$data['ogrenci_sayi_durumu'] ?? '', 'source_type'=>$data['ogrenci_kaynak_turu'] ?? '',
            'source_url'=>$data['ogrenci_kaynak_url'] ?? '', 'source_note'=>$data['student_source_note'],
            'verified_at'=>$data['ogrenci_dogrulama_tarihi'] ?? null,'recorded_by'=>get_current_user_id(),'recorded_at'=>$now];
        $wpdb->query('START TRANSACTION');
        $old=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::history_table().' WHERE school_id=%d AND data_year=%d ORDER BY id DESC LIMIT 1',$school->id,$year),ARRAY_A);
        $same=$old !== null;
        foreach (['student_count','data_status','source_type','source_url','source_note','verified_at'] as $k) {
            if (!$old || ($old[$k]===null)!==($snapshot[$k]===null) || (string)$old[$k] !== (string)$snapshot[$k]) $same=false;
        }
        // A backdated historical entry must not replace the newer current count.
        if (($year >= $latest_year && false===$wpdb->update(mad_okul_table(),$data,['id'=>(int)$school->id]))
            || (!$same && false===$wpdb->insert(self::history_table(),$snapshot))) {
            $wpdb->query('ROLLBACK'); return new WP_Error('save','Öğrenci kaydı/yıllık arşiv kaydedilemedi.');
        }
        $wpdb->query('COMMIT'); return true;
    }
    public static function menus() {
        add_submenu_page('mad-okul','Öğrenci Aktarımı ve Geçmiş','Öğrenci Aktarımı / Geçmiş','manage_options','mad-okul-records',[__CLASS__,'student_page']);
        add_submenu_page('mmc-dashboard','Baskı / Stok ve Geçmiş','Baskı / Stok ve Geçmiş','manage_options','mad-okul-stock',[__CLASS__,'stock_page']);
    }
    private static function url($page,$args=[]) { return add_query_arg(array_merge(['page'=>$page],$args),admin_url('admin.php')); }
    public static function export_url($kind,$mmc=0,$program=0) {
        return wp_nonce_url(add_query_arg(['action'=>'mad_okul_records_export','kind'=>$kind,'mmc_program_id'=>$mmc,'program_id'=>$program],admin_url('admin-post.php')),'mad_okul_records_export');
    }
    public static function help() {
        $page=sanitize_key($_GET['page'] ?? '');
        $messages=[
            'mad-okul-route'=>'Rota oluşturduktan sonra Rota Planı / PDF ekranından Excel indirebilir veya Yazdır → PDF olarak kaydet seçebilirsiniz. Rota sırası ve personel bilgisi çıktıya dahil edilir.',
            'mad-okul-import'=>'MEBBİS kurum listesi okul adı/adres/telefon kaynağıdır. Araştırılan öğrenci sayıları için Öğrenci Aktarımı / Geçmiş ekranındaki tek şablonu kullanın.',
            'mad-okul-students'=>'Öğrenci sayısını elle girin; MEB il/ilçe müdürlüğü, okul sitesi veya resmî yazı kaynağını, veri yılını ve kaynak notunu belirtin. Geçmiş yıllar arşivde kalır. Bilinmeyen sayıyı boş bırakın.',
            'mmc-school-data'=>'Bu ekran aynı kampüsteki okul birimlerini tek ziyaret noktasında toplar. Öğrenci Sayıları ana okul kaydını; Okul/Saha personel ve ziyaret durumunu tutar.',
            'mmc-school-print-plan'=>'Bu ekran okul/kampüs dağıtım planıdır. Planlanan bir hedef, Basılan fiilî baskı, Dağıtılan teslim edilen adettir. Yıllık toplam fiilî baskı, devreden, fire ve kalan stok ayrıca Baskı / Stok ve Geçmiş ekranında tutulur. Bu toplamları okul satırlarıyla iki kez toplamayın.'
        ];
        if (!isset($messages[$page])) return;
        $mmc=absint($_GET['mmc_program_id'] ?? $_GET['program_id'] ?? 0);
        echo '<div class="notice notice-info"><p>'.esc_html($messages[$page]).'</p><p><a class="button" href="'.esc_url(self::url('mad-okul-records',['mmc_program_id'=>$mmc])).'">Öğrenci şablonu / aktarım / geçmiş</a> <a class="button" href="'.esc_url(self::url('mad-okul-stock',['program_id'=>$mmc])).'">Baskı / stok / geçmiş</a></p></div>';
    }
    /** OOXML reader: named sheets and sparse cells; never executes formulas. */
    public static function xlsx_sheets($path) {
        if (!class_exists('ZipArchive')) return new WP_Error('zip','XLSX için ZIP desteği gerekli.');
        $zip=new ZipArchive(); if ($zip->open($path)!==true) return new WP_Error('zip','XLSX dosyası açılamadı.');
        $size=0; for ($i=0;$i<$zip->numFiles;$i++) { $st=$zip->statIndex($i); $size+=$st['size']; }
        if ($zip->numFiles>1000 || $size>50*1024*1024) { $zip->close(); return new WP_Error('limit','Dosya sınırı aşıldı.'); }
        $xml=function($name) use($zip) {
            $s=$zip->getFromName($name); if (!$s || stripos($s,'<!DOCTYPE')!==false || stripos($s,'<!ENTITY')!==false) return false;
            return simplexml_load_string($s,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
        };
        $shared=[]; $sx=$xml('xl/sharedStrings.xml');
        if ($sx!==false) foreach($sx->xpath('//*[local-name()="si"]') as $si) { $parts=[]; foreach($si->xpath('.//*[local-name()="t"]') as $t) $parts[]=(string)$t; $shared[]=implode('',$parts); }
        $wx=$xml('xl/workbook.xml'); $rx=$xml('xl/_rels/workbook.xml.rels');
        if ($wx===false || $rx===false) { $zip->close(); return new WP_Error('xml','Çalışma kitabı okunamadı.'); }
        $rels=[]; foreach($rx->xpath('//*[local-name()="Relationship"]') as $r) $rels[(string)$r['Id']]=(string)$r['Target'];
        $out=[];
        foreach($wx->xpath('//*[local-name()="sheet"]') as $sheet) {
            $ra=$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $target=$rels[(string)$ra['id']] ?? '';
            if (strpos($target,'..')!==false) continue;
            $target=substr($target,0,1)==='/' ? ltrim($target,'/') : 'xl/'.$target;
            if (!preg_match('#^xl/worksheets/[^/]+\.xml$#D',$target)) continue;
            $sx=$xml($target); if ($sx===false) continue;
            $grid=[];
            foreach($sx->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $vals=[];
                foreach($row->xpath('./*[local-name()="c"]') as $cell) {
                    preg_match('/^([A-Z]+)\d+$/D',(string)$cell['r'],$m); if (!$m) continue;
                    $col=0; foreach(str_split($m[1]) as $letter) $col=$col*26+ord($letter)-64;
                    if ($col>100) continue;
                    if ($cell->xpath('./*[local-name()="f"]')) { $vals[$col-1]='FORMÜL — değer olarak yapıştırın'; continue; }
                    $type=(string)$cell['t']; $v=$cell->xpath('./*[local-name()="v"]'); $raw=$v ? (string)$v[0] : '';
                    if ($type==='inlineStr') { $parts=[]; foreach($cell->xpath('.//*[local-name()="t"]') as $t) $parts[]=(string)$t; $raw=implode('',$parts); }
                    $vals[$col-1]=$type==='s' ? ($shared[(int)$raw] ?? '') : $raw;
                }
                if ($vals) $grid[]=$vals;
                if (count($grid)>5000) { $zip->close(); return new WP_Error('limit','Bir seferde en fazla 5000 satır.'); }
            }
            $out[(string)$sheet['name']]=$grid;
        }
        $zip->close(); return $out;
    }
    public static function grid_rows($grid) {
        $headers=null; $out=[];
        foreach($grid as $row) {
            if ($headers===null) {
                $keys=array_map('mad_okul_header_key',$row);
                if (!array_intersect($keys,['KURUM_ADI','OKUL_ADI','KURUM_ADLARI','BIRLESEN_KURUM'])) continue;
                $headers=$row; continue;
            }
            $assoc=[]; foreach($headers as $i=>$h) $assoc[mad_okul_header_key($h)]=$row[$i] ?? '';
            $out[]=$assoc;
        }
        return $out;
    }
    public static function file_rows($path,$ext) {
        if ($ext==='xlsx') {
            $sheets=self::xlsx_sheets($path); if (is_wp_error($sheets)) return $sheets;
            if (isset($sheets['Temiz Liste'])) {
                $rows=self::grid_rows($sheets['Temiz Liste']);
                $components=self::grid_rows($sheets['Birleştirme Kontrolü'] ?? []);
                $out=[];
                foreach($rows as $r) {
                    $names=trim((string)($r['KURUM_ADLARI'] ?? ''));
                    $parts=preg_split('/\s+[+|]\s+/u',$names);
                    $matched=array_values(array_filter($components,static function($c) use($r) {
                        return mad_okul_header_key($c['ZIYARET_NOKTASI_KAMPUS'] ?? '')===mad_okul_header_key($r['ZIYARET_NOKTASI_KAMPUS'] ?? '');
                    }));
                    if ($matched) {
                        foreach($matched as $c) { $x=$r; $x['KURUM_ADI']=$c['BIRLESEN_KURUM'] ?? ''; $x['OGRENCI_SAYISI']=$c['OGRENCI_SAYISI'] ?? ''; $x['KURUM_ADLARI']=''; $x['WEB_ADRES']=$c['WEB'] ?? ''; $x['OGRENCI_KAYNAK_URL']=$c['WEB'] ?? ''; $x['OGRENCI_SAYISI_WEB']=''; $out[]=$x; }
                    } elseif(count($parts)>1 || strpos($names,' / ')!==false) {
                        $r['_IMPORT_ERROR']='Birleşik kampüs için okul bazında öğrenci sayısı gerekli; toplam ayrı okullara kopyalanmadı.'; $out[]=$r;
                    } else $out[]=$r;
                }
                return $out;
            }
            foreach($sheets as $grid) { $rows=self::grid_rows($grid); if ($rows) return $rows; }
            return new WP_Error('header','Okul adı sütunu yok. Öğrenci şablonunu kullanın.');
        }
        if ($ext==='csv') {
            $fh=fopen($path,'r'); if (!$fh) return new WP_Error('file','Dosya okunamadı.');
            $first=fgets($fh); $sep=substr_count($first,';')>substr_count($first,',') ? ';' : ','; rewind($fh);
            $grid=[]; while(($row=fgetcsv($fh,0,$sep))!==false) { $row[0]=preg_replace('/^\xEF\xBB\xBF/','',$row[0] ?? ''); $grid[]=$row; if(count($grid)>5001) break; }
            fclose($fh); return self::grid_rows($grid);
        }
        if ($ext==='xls') return mad_okul_html_xls_rows($path);
        return new WP_Error('format','Yalnız XLSX, CSV veya MEBBİS HTML XLS dosyası kullanılabilir.');
    }
    public static function match_rows($rows,$schools) {
        $index=[];
        foreach($schools as $s) { $key=mad_okul_header_key($s->il).'|'.mad_okul_header_key($s->ilce).'|'.mad_okul_header_key($s->kurum_adi); $index[$key][]=$s; }
        $out=[]; $used=[];
        foreach($rows as $row) {
            $r=mad_okul_canonical_row($row); $key=mad_okul_header_key($r['IL_ADI']).'|'.mad_okul_header_key($r['ILCE_ADI']).'|'.mad_okul_header_key($r['KURUM_ADI']);
            $matches=$index[$key] ?? []; $count=self::count_value($r['OGRENCI_SAYISI']);
            $error=$row['_IMPORT_ERROR'] ?? '';
            if (!$error && count($matches)!==1) $error=count($matches)>1 ? 'Birden fazla okul eşleşti; manuel kontrol gerekli.' : 'Mevcut okul eşleşmedi; yeni okul oluşturulmadı.';
            if (!$error && is_wp_error($count)) $error=$count->get_error_message();
            if (!$error && $count===null) $error='Öğrenci sayısı yok; mevcut sayı korunacak.';
            if (!$error && isset($used[$matches[0]->id])) $error='Aynı okul dosyada birden fazla; tekrar satırı işlenmedi.';
            if (!$error) $used[$matches[0]->id]=true;
            $note=''; foreach($row as $k=>$v) if(mad_okul_header_key($k)==='KAYNAK_NOTU') $note=sanitize_textarea_field($v);
            $out[]=['row'=>$r,'school'=>$matches[0] ?? null,'count'=>$count,'error'=>$error,'note'=>$note];
        }
        return $out;
    }
    public static function preview() {
        self::guard(); check_admin_referer('mad_okul_records_preview'); global $wpdb;
        $year=self::year($_POST['data_year'] ?? ''); if(is_wp_error($year)) wp_die(esc_html($year->get_error_message()));
        $file=$_FILES['student_file'] ?? [];
        if (($file['error'] ?? 1)!==UPLOAD_ERR_OK || ($file['size'] ?? 0)>8*1024*1024 || !is_uploaded_file($file['tmp_name'] ?? '')) wp_die('Dosya yüklenemedi. En fazla 8 MB.');
        $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        $raw=self::file_rows($file['tmp_name'],$ext); if(is_wp_error($raw)) wp_die(esc_html($raw->get_error_message()));
        if (!$raw || count($raw)>5000) wp_die('Geçerli öğrenci satırı bulunamadı veya 5000 satır sınırı aşıldı.');
        // Match only relevant existing areas; no school creates or route writes.
        $areas=[]; foreach($raw as $r) { $c=mad_okul_canonical_row($r); $areas[mad_okul_place_title($c['IL_ADI']).'|'.mad_okul_place_title($c['ILCE_ADI'])]=[$c['IL_ADI'],$c['ILCE_ADI']]; }
        $schools=[]; foreach($areas as $a) if($a[0] && $a[1]) $schools=array_merge($schools,$wpdb->get_results($wpdb->prepare('SELECT * FROM '.mad_okul_table().' WHERE il=%s AND ilce=%s',mad_okul_place_title($a[0]),mad_okul_place_title($a[1]))));
        $rows=self::match_rows($raw,$schools);
        foreach($rows as &$item) {
            $row_year=self::year($item['row']['VERI_YILI'] ?: $year);
            if (!$item['error'] && is_wp_error($row_year)) $item['error']=$row_year->get_error_message();
        }
        unset($item);
        $token=wp_generate_password(32,false,false);
        set_transient('mad_okul_preview_'.get_current_user_id().'_'.$token,['rows'=>$rows,'year'=>$year,'source'=>sanitize_text_field(wp_unslash($_POST['source_type'] ?? 'MEB il/ilçe müdürlüğü')),'note'=>sanitize_textarea_field(wp_unslash($_POST['source_note'] ?? ''))],20*MINUTE_IN_SECONDS);
        wp_safe_redirect(self::url('mad-okul-records',['preview'=>$token,'mmc_program_id'=>absint($_POST['mmc_program_id'] ?? 0)])); exit;
    }
    public static function accept() {
        self::guard(); $token=sanitize_text_field($_POST['preview'] ?? ''); check_admin_referer('mad_okul_records_accept_'.$token); global $wpdb;
        $key='mad_okul_preview_'.get_current_user_id().'_'.$token; $preview=get_transient($key);
        if (!$preview || empty($_POST['confirmed'])) wp_die('Önizleme süresi doldu veya onay verilmedi.');
        $saved=0; $changed=0;
        foreach($preview['rows'] as $item) {
            if ($item['error']) continue;
            $old=$item['school']; $s=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.mad_okul_table().' WHERE id=%d',$old->id));
            if (!$s || serialize($s)!==serialize($old)) { $changed++; continue; }
            $r=$item['row']; $data=['ogrenci_sayisi'=>$item['count'],'ogrenci_sayi_durumu'=>sanitize_key($r['OGRENCI_SAYI_DURUMU'] ?: 'tam'),
                'ogrenci_kaynak_turu'=>sanitize_text_field($r['OGRENCI_KAYNAK_TURU'] ?: $preview['source']),
                'ogrenci_kaynak_url'=>esc_url_raw($r['OGRENCI_KAYNAK_URL'] ?: $s->ogrenci_kaynak_url),
                'ogrenci_dogrulama_tarihi'=>mad_okul_normalize_datetime($r['OGRENCI_DOGRULAMA_TARIHI']) ?: current_time('mysql')];
            $row_year=$r['VERI_YILI'] ?: $preview['year'];
            $result=self::save_student($s,$data,$row_year,$item['note'] ?: $preview['note']);
            if(is_wp_error($result)) wp_die(esc_html($result->get_error_message().' Önceki başarılı satırlar kaydedildi; yeniden önizleyin.'));
            $saved++;
        }
        delete_transient($key);
        wp_safe_redirect(self::url('mad-okul-records',['saved'=>$saved,'changed'=>$changed,'mmc_program_id'=>absint($_POST['mmc_program_id'] ?? 0)])); exit;
    }
    /** Shared geographic scope for history and student exports. */
    private static function student_scope_where($mmc,$alias='') {
        global $wpdb;
        if (!$mmc) return '';
        $ctx=Mad_Okul_Operations::mmc_program_context($mmc);
        if (is_wp_error($ctx)) return $ctx;
        $districts=class_exists('MMC_Region_Service') ? MMC_Region_Service::get_program_targets($mmc) : [];
        if (!$districts && !empty($ctx->program->district_name)) $districts=[$ctx->program->district_name];
        $districts=array_values(array_unique(array_filter(array_map('mad_okul_place_title',(array)$districts))));
        $province=mad_okul_place_title($ctx->program->province_name);
        // Invalid program geography must not fall through to an unfiltered export.
        if (!$province || !$districts) return new WP_Error('scope','Program okul bölgesi bulunamadı.');
        $prefix=$alias==='s' ? 's.' : '';
        $placeholders=implode(',',array_fill(0,count($districts),'%s'));
        return $wpdb->prepare(' WHERE '.$prefix.'il=%s AND '.$prefix.'ilce IN ('.$placeholders.')',array_merge([$province],$districts));
    }
    public static function student_page() {
        self::guard(); global $wpdb; $mmc=absint($_GET['mmc_program_id'] ?? 0);
        echo '<div class="wrap"><h1>Öğrenci Aktarımı ve Yıllık Geçmiş</h1><p>Okul araştırması ve MEB il/ilçe müdürlüğü verisi aynı şablonla işlenir. Önce eşleşme/aday sayı gösterilir, açık onaydan sonra yalnız mevcut okulun öğrenci kaydı güncellenir. Boş sayılar mevcut sayıyı silmez. Kampüs toplamları ayrı okullara çoğaltılmaz.</p><p><a class="button" href="'.esc_url(self::export_url('student',$mmc)).'">Öğrenci Excel Şablonunu / Mevcut Veriyi İndir</a> <a class="button" href="'.esc_url(self::url('mad-okul-students',['mmc_program_id'=>$mmc])).'">Manuel Öğrenci Girişi</a></p>';
        if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>'.absint($_GET['saved']).' okul kaydedildi; '.absint($_GET['changed'] ?? 0).' sonradan değişen okul korunup atlandı.</p></div>';
        echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mad_okul_records_preview"><input type="hidden" name="mmc_program_id" value="'.$mmc.'">'; wp_nonce_field('mad_okul_records_preview');
        echo '<p><label>Veri yılı <input name="data_year" type="number" min="2000" max="2099" value="'.esc_attr(wp_date('Y')).'" required></label> <label>Kaynak türü <select name="source_type"><option>MEB il/ilçe müdürlüğü</option><option>Okul resmî sitesi</option><option>Okuldan resmî yazı</option><option>Manuel doğrulama</option></select></label></p><p><label>Kaynak notu / evrak bilgisi <input class="large-text" name="source_note" placeholder="Evrak no, veri dönemi, teslim eden birim; öğrenci kişisel verisi eklemeyin"></label></p><input type="file" name="student_file" accept=".xlsx,.csv,.xls" required> <button class="button button-primary">Eşleştirmeyi Önizle</button></form>';
        $token=sanitize_text_field($_GET['preview'] ?? ''); $preview=$token ? get_transient('mad_okul_preview_'.get_current_user_id().'_'.$token) : null;
        if ($preview) {
            echo '<h2>Aday sonuçlar — henüz okul verisine yazılmadı</h2><table class="widefat striped"><thead><tr><th>Okul</th><th>Mevcut sayı</th><th>Aday sayı</th><th>Kaynak / Veri yılı</th><th>İşlem</th></tr></thead><tbody>';
            foreach($preview['rows'] as $i) echo '<tr><td>'.esc_html($i['row']['KURUM_ADI']).'</td><td>'.esc_html($i['school']->ogrenci_sayisi ?? 'Bilinmiyor').'</td><td>'.esc_html(is_wp_error($i['count']) ? 'Geçersiz' : ($i['count'] ?? 'Bilinmiyor')).'</td><td>'.esc_html(($i['row']['OGRENCI_KAYNAK_TURU'] ?: $preview['source']).' · '.($i['row']['VERI_YILI'] ?: $preview['year']).' · '.$i['row']['OGRENCI_KAYNAK_URL']).'</td><td>'.esc_html($i['error'] ?: 'Onay bekliyor').'</td></tr>';
            echo '</tbody></table><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mad_okul_records_accept"><input type="hidden" name="preview" value="'.esc_attr($token).'"><input type="hidden" name="mmc_program_id" value="'.$mmc.'">'; wp_nonce_field('mad_okul_records_accept_'.$token);
            echo '<p><label><input type="checkbox" name="confirmed" value="1" required> Aday sayıları ve kaynaklarını kontrol ettim; uygun eşleşmeleri kaydet.</label></p><button class="button button-primary">Onaylanan Öğrenci Verilerini Kaydet</button></form>';
        }
        $where=self::student_scope_where($mmc,'s');
        if (is_wp_error($where)) wp_die(esc_html($where->get_error_message()));
        $history=$wpdb->get_results('SELECT h.*,s.kurum_adi FROM '.self::history_table().' h JOIN '.mad_okul_table().' s ON s.id=h.school_id'.$where.' ORDER BY h.data_year DESC,h.id DESC LIMIT 200');
        echo '<h2>Geçmiş yıllar ve düzeltme kayıtları</h2><p>Son 200 kayıt. Yeni yıl girişi eski yılın kaydını silmez.</p><table class="widefat striped"><thead><tr><th>Okul</th><th>Veri yılı</th><th>Öğrenci</th><th>Kaynak</th><th>Kaynak notu</th><th>Kaydedildi</th></tr></thead><tbody>';
        foreach($history as $h) echo '<tr><td>'.esc_html($h->kurum_adi).'</td><td>'.esc_html($h->data_year).'</td><td>'.esc_html($h->student_count ?? 'Bilinmiyor').'</td><td>'.esc_html($h->source_type).'</td><td>'.esc_html($h->source_note).'</td><td>'.esc_html($h->recorded_at).'</td></tr>';
        echo '</tbody></table></div>';
    }
    public static function stock_save() {
        self::guard(); check_admin_referer('mad_okul_stock_save'); global $wpdb;
        $pid=absint($_POST['program_id'] ?? 0); if(!class_exists('MMC_Program_Service') || !MMC_Program_Service::get_program($pid)) wp_die('Mevcut MMC programını seçin.');
        $year=self::year($_POST['stock_year'] ?? ''); if(is_wp_error($year)) wp_die(esc_html($year->get_error_message()));
        $material=sanitize_key($_POST['material'] ?? ''); if(!in_array($material,['davetiye','tanitim_bileti','afis','brosur'],true)) wp_die('Baskı türü geçersiz.');
        $record=['program_id'=>$pid,'stock_year'=>$year,'material'=>$material];
        foreach(['opening_qty','printed_qty','distributed_qty','waste_qty'] as $k) $record[$k]=trim((string)($_POST[$k] ?? ''));
        $left=self::balance($record['opening_qty'],$record['printed_qty'],$record['distributed_qty'],$record['waste_qty']);
        if(is_wp_error($left)) wp_die(esc_html($left->get_error_message()));
        $record['notes']=sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')); $record['updated_by']=get_current_user_id(); $record['updated_at']=current_time('mysql');
        $existing=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::stock_table().' WHERE program_id=%d AND stock_year=%d AND material=%s',$pid,$year,$material));
        if ($existing && (string)($_POST['expected_at'] ?? '')!==$existing->updated_at) wp_die('Stok kaydı başka oturumda değişti. Sayfayı yenileyip tekrar kontrol edin.');
        $ok=$existing ? $wpdb->update(self::stock_table(),$record,['id'=>$existing->id]) : $wpdb->insert(self::stock_table(),$record);
        if(false===$ok) wp_die('Stok kaydı kaydedilemedi.');
        wp_safe_redirect(self::url('mad-okul-stock',['program_id'=>$pid,'stock_year'=>$year,'material'=>$material,'saved'=>1])); exit;
    }
    public static function stock_page() {
        self::guard(); global $wpdb;
        $programs=class_exists('MMC_Program_Service') ? MMC_Program_Service::all_programs() : [];
        $pid=absint($_GET['program_id'] ?? 0); $year=absint($_GET['stock_year'] ?? wp_date('Y')); $material=sanitize_key($_GET['material'] ?? 'davetiye');
        $types=['davetiye'=>'Davetiye','tanitim_bileti'=>'Tanıtım kâğıt bileti','afis'=>'Afiş','brosur'=>'Broşür'];
        if(!isset($types[$material])) $material='davetiye';
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::stock_table().' WHERE program_id=%d AND stock_year=%d AND material=%s',$pid,$year,$material));
        echo '<div class="wrap"><h1>Baskı / Stok ve Yıllık Geçmiş</h1><p>Yalnız fiziksel kâğıt baskılarıdır. WooCommerce / Tickera satış bileti veya QR üretmez. Kalan = devreden + bu yıl basılan − dağıtılan − fire. Adetler birikimli toplamdır; yeniden kaydetmek üstüne eklemez. Her program/yıl/tür ayrı tutulur; eski yıllar korunur.</p>';
        if(isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Fiilî baskı/stok kaydı kaydedildi.</p></div>';
        echo '<form method="get"><input type="hidden" name="page" value="mad-okul-stock"><label>Program <select name="program_id" required><option value="">Seçin</option>';
        foreach($programs as $p) echo '<option value="'.(int)$p->id.'" '.selected($pid,$p->id,false).'>'.esc_html($p->program_code.' — '.$p->province_name.' / '.$p->district_name).'</option>';
        echo '</select></label> <label>Yıl <input type="number" name="stock_year" min="2000" max="2099" value="'.$year.'"></label> <select name="material">';
        foreach($types as $k=>$label) echo '<option value="'.$k.'" '.selected($material,$k,false).'>'.esc_html($label).'</option>';
        echo '</select> <button class="button">Kaydı Aç</button></form>';
        if($pid && MMC_Program_Service::get_program($pid)) {
            echo '<h2>Manuel Fiilî Baskı / Stok Girişi</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mad_okul_records_stock_save"><input type="hidden" name="program_id" value="'.$pid.'"><input type="hidden" name="stock_year" value="'.$year.'"><input type="hidden" name="material" value="'.esc_attr($material).'"><input type="hidden" name="expected_at" value="'.esc_attr($row->updated_at ?? '').'">'; wp_nonce_field('mad_okul_stock_save');
            foreach(['opening_qty'=>'Önceki yıldan devreden','printed_qty'=>'Bu yıl fiilen basılan toplam','distributed_qty'=>'Dağıtılan toplam','waste_qty'=>'Fire / kullanılamayan'] as $k=>$label) echo '<p><label>'.esc_html($label).' <input type="number" min="0" max="999999999" name="'.$k.'" value="'.esc_attr($row->$k ?? 0).'" required></label></p>';
            echo '<p><label>Not <textarea name="notes" rows="3" class="large-text">'.esc_textarea($row->notes ?? '').'</textarea></label></p><button class="button button-primary">Baskı / Stok Kaydını Kaydet</button></form>';
            if($row) echo '<p><strong>Elimizde kalan: '.esc_html(self::balance($row->opening_qty,$row->printed_qty,$row->distributed_qty,$row->waste_qty)).'</strong></p>';
        }
        $rows=$wpdb->get_results('SELECT s.*,p.program_code FROM '.self::stock_table().' s LEFT JOIN '.$wpdb->prefix.'mmc_programs p ON p.id=s.program_id ORDER BY s.stock_year DESC,s.program_id,s.material');
        $totals=[];
        foreach($rows as $r) { $key=$r->stock_year.'|'.$r->material; if(!isset($totals[$key])) $totals[$key]=['year'=>$r->stock_year,'type'=>$r->material,'printed'=>0,'distributed'=>0,'left'=>0]; $totals[$key]['printed']+=(int)$r->printed_qty; $totals[$key]['distributed']+=(int)$r->distributed_qty; $totals[$key]['left']+=(int)self::balance($r->opening_qty,$r->printed_qty,$r->distributed_qty,$r->waste_qty); }
        echo '<h2>Yıl / Baskı Türü Toplamları</h2><p>Yalnız girilmiş fiilî stok kayıtları toplanır. Eksik programlar için miktar tahmin edilmez. Farklı kâğıt türleri ayrı gösterilir.</p><table class="widefat striped"><thead><tr><th>Yıl</th><th>Tür</th><th>Toplam basılan</th><th>Toplam dağıtılan</th><th>Kayıtlardaki kalan</th></tr></thead><tbody>';
        foreach($totals as $t) echo '<tr><td>'.esc_html($t['year']).'</td><td>'.esc_html($types[$t['type']] ?? $t['type']).'</td><td>'.esc_html($t['printed']).'</td><td>'.esc_html($t['distributed']).'</td><td>'.esc_html($t['left']).'</td></tr>';
        if(!$totals) echo '<tr><td colspan="5">Henüz fiilî stok kaydı girilmedi; basılan/kalan miktar bilinmiyor.</td></tr>';
        echo '</tbody></table>';
        echo '<h2>Yıllık Baskı ve Kalan Stok Arşivi</h2><p>Stok kayıtları program/yıl/tür düzeyindedir. Okul dağıtım planı ayrı tutulur. Devreden stok otomatik varsayılmaz; yıl başında fiziksel sayımdan elle girilir.</p><p><a class="button" href="'.esc_url(self::export_url('stock')).'">Baskı / Stok Excel İndir</a></p><table class="widefat striped"><thead><tr><th>Yıl</th><th>Program</th><th>Tür</th><th>Devreden</th><th>Basılan</th><th>Dağıtılan</th><th>Fire</th><th>Kalan</th><th>İşlem</th></tr></thead><tbody>';
        foreach($rows as $r) echo '<tr><td>'.esc_html($r->stock_year).'</td><td>'.esc_html($r->program_code).'</td><td>'.esc_html($types[$r->material] ?? $r->material).'</td><td>'.esc_html($r->opening_qty).'</td><td>'.esc_html($r->printed_qty).'</td><td>'.esc_html($r->distributed_qty).'</td><td>'.esc_html($r->waste_qty).'</td><td>'.esc_html(self::balance($r->opening_qty,$r->printed_qty,$r->distributed_qty,$r->waste_qty)).'</td><td><a href="'.esc_url(self::url('mad-okul-stock',['program_id'=>$r->program_id,'stock_year'=>$r->stock_year,'material'=>$r->material])).'">Düzenle</a></td></tr>';
        echo '</tbody></table></div>';
    }
    public static function export() {
        self::guard(); check_admin_referer('mad_okul_records_export'); global $wpdb;
        $kind=sanitize_key($_GET['kind'] ?? ''); $mmc=absint($_GET['mmc_program_id'] ?? 0); $pid=absint($_GET['program_id'] ?? 0);
        if($kind==='route') {
            if($mmc) { $ctx=Mad_Okul_Operations::mmc_program_context($mmc); if(is_wp_error($ctx)) wp_die(esc_html($ctx->get_error_message())); $pid=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.Mad_Okul_Operations::programs_table().' WHERE mmc_program_id=%d LIMIT 1',$mmc)); }
            if(!$pid) wp_die('Önce mevcut rotayı oluşturup programı seçin.');
            $rows=$wpdb->get_results($wpdb->prepare('SELECT s.*,u.display_name,p.program_adi,p.salon_adi,p.salon_adresi,p.etkinlik_tarihi FROM '.mad_okul_table().' s LEFT JOIN '.$wpdb->users.' u ON u.ID=s.assigned_user_id JOIN '.Mad_Okul_Operations::programs_table().' p ON p.id=s.program_id WHERE s.program_id=%d ORDER BY s.assigned_user_id,s.route_group,s.route_order,s.kurum_adi',$pid));
            $grid=[['Program','Tarih','Başlangıç salonu','Salon adresi','Personel','Grup','Rota sırası','Okul','İl','İlçe','Adres','Telefon','Öğrenci','Google Maps']];
            foreach($rows as $r) $grid[]=[$r->program_adi,$r->etkinlik_tarihi,$r->salon_adi,$r->salon_adresi,$r->display_name,$r->route_group,$r->route_order,$r->kurum_adi,$r->il,$r->ilce,$r->adres,$r->telefon,$r->student_count,'https://www.google.com/maps/search/?api=1&query='.rawurlencode($r->kurum_adi.', '.$r->adres.', '.$r->ilce.'/'.$r->il)];
        } elseif($kind==='student') {
            $where=self::student_scope_where($mmc);
            if(is_wp_error($where)) wp_die(esc_html($where->get_error_message()));
            $rows=$wpdb->get_results('SELECT * FROM '.mad_okul_table().$where.' ORDER BY il,ilce,kurum_adi LIMIT 5000');
            $grid=[['IL_ADI','ILCE_ADI','KURUM_ADI','OGRENCI_SAYISI','OGRENCI_SAYI_DURUMU','OGRENCI_KAYNAK_TURU','OGRENCI_KAYNAK_URL','VERI_YILI','KAYNAK_NOTU']];
            foreach($rows as $r) $grid[]=[$r->il,$r->ilce,$r->kurum_adi,$r->ogrenci_sayisi,$r->ogrenci_sayi_durumu,$r->ogrenci_kaynak_turu,$r->ogrenci_kaynak_url,$r->student_data_year ?? wp_date('Y'),$r->student_source_note ?? ''];
        } elseif($kind==='stock') {
            $rows=$wpdb->get_results('SELECT s.*,p.program_code FROM '.self::stock_table().' s LEFT JOIN '.$wpdb->prefix.'mmc_programs p ON p.id=s.program_id ORDER BY s.stock_year DESC,s.program_id,s.material');
            $grid=[['Yıl','Program','Tür','Devreden','Basılan','Dağıtılan','Fire','Kalan','Not']];
            foreach($rows as $r) $grid[]=[$r->stock_year,$r->program_code,$r->material,$r->opening_qty,$r->printed_qty,$r->distributed_qty,$r->waste_qty,self::balance($r->opening_qty,$r->printed_qty,$r->distributed_qty,$r->waste_qty),$r->notes];
        } else wp_die('Çıktı türü geçersiz.');
        self::download_xlsx($grid,'madagaskar-'.$kind.'.xlsx');
    }
    public static function xlsx_bytes($grid) {
        if(!class_exists('ZipArchive')) return new WP_Error('zip','Excel çıktısı için ZIP desteği gerekli.');
        $path=wp_tempnam('mad-school-export'); $zip=new ZipArchive();
        if(!$path || $zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) return new WP_Error('file','Excel dosyası oluşturulamadı.');
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Veri" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $sheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="20" width="25" customWidth="1"/></cols><sheetData>';
        foreach($grid as $i=>$row) { $n=$i+1; $sheet.='<row r="'.$n.'">'; foreach(array_values($row) as $j=>$v) { $col=''; $k=$j+1; while($k>0) { $k--; $col=chr(65+$k%26).$col; $k=intdiv($k,26); } $v=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$v); $sheet.='<c r="'.$col.$n.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($v,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>'; } $sheet.='</row>'; }
        $sheet.='</sheetData></worksheet>'; $zip->addFromString('xl/worksheets/sheet1.xml',$sheet); $zip->close(); $bytes=file_get_contents($path); unlink($path); return $bytes;
    }
    private static function download_xlsx($grid,$name) {
        $bytes=self::xlsx_bytes($grid); if(is_wp_error($bytes)) wp_die(esc_html($bytes->get_error_message()));
        nocache_headers(); header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="'.$name.'"'); header('Content-Length: '.strlen($bytes)); echo $bytes; exit;
    }
}
