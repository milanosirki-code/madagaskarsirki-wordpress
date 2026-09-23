<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Finance_Service {
    public static function hooks() {
        add_action( 'mmc_program_logged', array( __CLASS__, 'on_program_log' ), 30, 7 );
    }

    public static function entry_classes() {
        return array(
            'income'        => 'Gelir',
            'expense'       => 'Gider',
            'deposit_asset' => 'İade Bekleyen Teminat',
        );
    }

    public static function entry_statuses() {
        return array(
            'planned'   => 'Planlandı',
            'pending'   => 'Bekliyor',
            'incurred'  => 'Gerçekleşti',
            'realized'  => 'Tahsil Edildi / Gerçekleşti',
            'paid'      => 'Ödendi / Tahsil Edildi',
            'refunded'  => 'İade Alındı',
            'cancelled' => 'İptal',
        );
    }

    public static function categories( $class = '' ) {
        $income = array(
            'box_office_cash' => 'Gişe Nakit Bilet',
            'box_office_pos'  => 'Gişe POS / Kart Bilet',
            'biletinial'      => 'Biletinial',
            'eft_ticket'      => 'Havale / EFT Bilet',
            'concessions'     => 'Yiyecek / Mısır',
            'toys'            => 'Oyuncak',
            'sponsorship'     => 'Sponsor / Destek',
            'other_income'    => 'Diğer Gelir',
        );
        $expense = array(
            'venue_rent'          => 'Salon Kirası',
            'cleaning_security'   => 'Temizlik / Güvenlik',
            'artist_personnel'    => 'Sanatçı / Personel',
            'meta_ads'            => 'Reklam / Sosyal Medya',
            'poster_promo'        => 'Afiş / Tanıtım',
            'transport_fuel'      => 'Ulaşım / Yakıt',
            'hotel_accommodation' => 'Otel / Konaklama',
            'meals'               => 'Yemek',
            'ticket_pos_fee'      => 'Bilet / POS Komisyonu',
            'permits_official'    => 'İzin / Resmî Ödeme',
            'operation_other'     => 'Diğer Operasyon Gideri',
            'deposit_deduction'   => 'Teminat Kesintisi',
            'other_expense'       => 'Diğer Gider',
        );
        if ( 'income' === $class ) return $income;
        if ( 'expense' === $class ) return $expense;
        return array_merge( $income, $expense );
    }

    public static function invoice_statuses() {
        return array(
            'pending'      => 'Fatura Bekliyor',
            'issued'       => 'Kesildi',
            'received'     => 'Alındı',
            'not_required' => 'Gerekli Değil',
            'cancelled'    => 'İptal',
        );
    }

    public static function refund_statuses() {
        return array(
            'draft'    => 'Taslak',
            'ready'    => 'Dilekçe Hazır',
            'sent'     => 'Gönderildi',
            'waiting'  => 'İade Bekleniyor',
            'partial'  => 'Kısmi İade',
            'resolved' => 'Sonuçlandı',
            'cancelled'=> 'İptal',
        );
    }

    public static function backfill_existing_programs() {
        foreach ( MMC_Program_Service::all_programs() as $p ) {
            self::ensure_closure( $p->id );
            self::sync_external_sources( $p->id );
            self::ensure_deposit_refunds( $p->id );
        }
    }

    public static function entries( $program_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mmc_finance_entries WHERE program_id=%d ORDER BY entry_class ASC,id ASC",
            absint( $program_id )
        ) );
    }

    public static function add_entry( $program_id, $data ) {
        global $wpdb;
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) return new WP_Error( 'mmc_fin_program', 'Program bulunamadı.' );
        $class = sanitize_key( $data['entry_class'] ?? '' );
        if ( ! in_array( $class, array( 'income','expense' ), true ) ) return new WP_Error( 'mmc_fin_class', 'Manuel kayıtta yalnız gelir veya gider seçilebilir.' );
        $category = sanitize_key( $data['category'] ?? '' );
        if ( ! isset( self::categories( $class )[ $category ] ) ) return new WP_Error( 'mmc_fin_category', 'Geçersiz gelir/gider kategorisi.' );
        $title = sanitize_text_field( $data['title'] ?? '' );
        $amount = self::money( $data['amount'] ?? 0 );
        if ( ! $title || $amount <= 0 ) return new WP_Error( 'mmc_fin_required', 'Başlık ve sıfırdan büyük tutar zorunludur.' );
        $status = sanitize_key( $data['status'] ?? 'planned' );
        if ( ! isset( self::entry_statuses()[ $status ] ) ) $status = 'planned';
        $now = current_time( 'mysql' );
        $row = array(
            'program_id' => absint( $program_id ),
            'related_entity_type' => 'manual',
            'related_entity_id' => null,
            'entry_class' => $class,
            'category' => $category,
            'title' => $title,
            'amount' => $amount,
            'status' => $status,
            'due_date' => self::date_or_null( $data['due_date'] ?? '' ),
            'reference_no' => sanitize_text_field( $data['reference_no'] ?? '' ),
            'notes' => sanitize_textarea_field( $data['notes'] ?? '' ),
            'created_by' => get_current_user_id() ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        );
        if ( in_array( $status, array( 'paid','realized','incurred' ), true ) ) $row['paid_at'] = $now;
        $ok = $wpdb->insert( $wpdb->prefix . 'mmc_finance_entries', $row );
        if ( ! $ok ) return new WP_Error( 'mmc_fin_insert', 'Finans kaydı eklenemedi.' );
        $id = (int) $wpdb->insert_id;
        MMC_Program_Service::add_log( $program_id, 'finance_entry_added', 'finance_entry', $id, null, array( 'class'=>$class,'category'=>$category,'amount'=>$amount ), $title );
        return $id;
    }

    public static function update_entry( $entry_id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_finance_entries';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", absint($entry_id) ) );
        if ( ! $row ) return new WP_Error( 'mmc_fin_missing', 'Finans kaydı bulunamadı.' );
        $status = sanitize_key( $data['status'] ?? $row->status );
        if ( ! isset( self::entry_statuses()[ $status ] ) ) $status = $row->status;
        $amount = isset( $data['amount'] ) ? self::money( $data['amount'] ) : (float) $row->amount;
        $update = array(
            'amount' => $amount,
            'status' => $status,
            'due_date' => self::date_or_null( $data['due_date'] ?? $row->due_date ),
            'reference_no' => sanitize_text_field( $data['reference_no'] ?? $row->reference_no ),
            'notes' => sanitize_textarea_field( $data['notes'] ?? $row->notes ),
            'updated_at' => current_time( 'mysql' ),
        );
        if ( in_array( $status, array( 'paid','realized','incurred' ), true ) && ! $row->paid_at ) $update['paid_at'] = current_time( 'mysql' );
        if ( 'refunded' === $status && ! $row->refunded_at ) $update['refunded_at'] = current_time( 'mysql' );
        $ok = $wpdb->update( $table, $update, array( 'id'=>(int)$row->id ) );
        if ( false === $ok ) return new WP_Error( 'mmc_fin_update', 'Finans kaydı güncellenemedi.' );
        MMC_Program_Service::add_log( $row->program_id, 'finance_entry_updated', 'finance_entry', $row->id, array('amount'=>$row->amount,'status'=>$row->status), array('amount'=>$amount,'status'=>$status), $row->title );
        self::ensure_deposit_refunds( $row->program_id );
        self::refresh_closure_snapshot( $row->program_id );
        return true;
    }

    public static function sync_external_sources( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        // Meta gerçek harcamasını finans defterine otomatik yansıt.
        if ( class_exists( 'MMC_Marketing_Service' ) ) {
            $plan = MMC_Marketing_Service::meta_plan( $program_id );
            if ( $plan ) {
                self::upsert_auto_entry( $program_id, 'meta_plan', (int)$plan->id, 'expense', 'meta_ads', 'Meta reklam harcaması', (float)$plan->spend, (float)$plan->spend > 0 ? 'incurred' : 'planned' );
            }
        }
        self::ensure_deposit_refunds( $program_id );
        self::ensure_closure( $program_id );
        self::refresh_closure_snapshot( $program_id );
        return true;
    }

    private static function upsert_auto_entry( $program_id, $entity_type, $entity_id, $class, $category, $title, $amount, $status ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_finance_entries';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE program_id=%d AND related_entity_type=%s AND related_entity_id=%d AND category=%s LIMIT 1",
            absint($program_id), $entity_type, absint($entity_id), $category
        ) );
        $now = current_time('mysql');
        if ( $row ) {
            if ( 'paid' === $row->status ) return;
            $wpdb->update( $table, array( 'amount'=>$amount, 'status'=>$amount>0?$status:'cancelled', 'title'=>$title, 'updated_at'=>$now ), array( 'id'=>(int)$row->id ) );
            return;
        }
        if ( $amount <= 0 ) return;
        $wpdb->insert( $table, array(
            'program_id'=>absint($program_id),'related_entity_type'=>$entity_type,'related_entity_id'=>absint($entity_id),
            'entry_class'=>$class,'category'=>$category,'title'=>$title,'amount'=>$amount,'status'=>$status,
            'created_by'=>get_current_user_id()?:null,'created_at'=>$now,'updated_at'=>$now,
        ) );
    }

    public static function sync_invoice_queue( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT external_order_id, MAX(order_status) order_status, SUM(net_amount) amount
             FROM {$wpdb->prefix}mmc_sales_ledger
             WHERE program_id=%d AND channel='woocommerce' AND order_status IN ('processing','completed')
             GROUP BY external_order_id",
            $program_id
        ) );
        $table = $wpdb->prefix . 'mmc_invoices';
        $added = 0;
        foreach ( $rows as $r ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE program_id=%d AND source_type='woo_order' AND source_id=%d", $program_id, (int)$r->external_order_id ) );
            if ( $exists ) continue;
            $name=''; $email='';
            if ( function_exists('wc_get_order') ) {
                $order = wc_get_order( (int)$r->external_order_id );
                if ( $order ) {
                    $name = trim( (string)$order->get_billing_first_name() . ' ' . (string)$order->get_billing_last_name() );
                    if ( ! $name ) $name = (string)$order->get_billing_company();
                    $email = (string)$order->get_billing_email();
                }
            }
            $now=current_time('mysql');
            $wpdb->insert($table,array(
                'program_id'=>$program_id,'source_type'=>'woo_order','source_id'=>(int)$r->external_order_id,'direction'=>'income','invoice_type'=>'e_archive',
                'customer_name'=>sanitize_text_field($name),'customer_email'=>sanitize_email($email),'amount'=>round((float)$r->amount,2),'status'=>'pending',
                'created_by'=>get_current_user_id()?:null,'created_at'=>$now,'updated_at'=>$now,
            ));
            if($wpdb->insert_id)$added++;
        }
        self::refresh_closure_snapshot( $program_id );
        MMC_Program_Service::add_log( $program_id, 'finance_invoice_queue_synced', 'finance', null, null, array('added'=>$added), 'WooCommerce siparişlerinden fatura kuyruğu yenilendi.' );
        return $added;
    }

    public static function invoices( $program_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_invoices WHERE program_id=%d ORDER BY status ASC,id DESC", absint($program_id) ) );
    }

    public static function update_invoice( $invoice_id, $data ) {
        global $wpdb;
        $table=$wpdb->prefix.'mmc_invoices';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",absint($invoice_id)));
        if(!$row)return new WP_Error('mmc_invoice_missing','Fatura kaydı bulunamadı.');
        $status=sanitize_key($data['status']??$row->status); if(!isset(self::invoice_statuses()[$status]))$status=$row->status;
        $ok=$wpdb->update($table,array(
            'status'=>$status,'invoice_no'=>sanitize_text_field($data['invoice_no']??$row->invoice_no),
            'invoice_date'=>self::date_or_null($data['invoice_date']??$row->invoice_date),'external_url'=>esc_url_raw($data['external_url']??$row->external_url),
            'notes'=>sanitize_textarea_field($data['notes']??$row->notes),'updated_at'=>current_time('mysql'),
        ),array('id'=>(int)$row->id));
        if(false===$ok)return new WP_Error('mmc_invoice_update','Fatura kaydı güncellenemedi.');
        MMC_Program_Service::add_log($row->program_id,'finance_invoice_updated','invoice',$row->id,$row->status,$status,'Fatura durumu güncellendi.');
        self::refresh_closure_snapshot($row->program_id);
        return true;
    }

    public static function ensure_deposit_refunds( $program_id ) {
        global $wpdb;
        $finance=$wpdb->prefix.'mmc_finance_entries'; $refunds=$wpdb->prefix.'mmc_deposit_refunds';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $finance WHERE program_id=%d AND entry_class='deposit_asset' AND status IN ('paid','refunded') AND amount>0",absint($program_id)));
        foreach($rows as $e){
            $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $refunds WHERE finance_entry_id=%d",(int)$e->id)); if($exists)continue;
            $pv_id='program_venue'===$e->related_entity_type?(int)$e->related_entity_id:null; $now=current_time('mysql');
            $wpdb->insert($refunds,array('program_id'=>absint($program_id),'program_venue_id'=>$pv_id,'finance_entry_id'=>(int)$e->id,'deposit_amount'=>(float)$e->amount,'requested_amount'=>(float)$e->amount,'status'=>'draft','created_by'=>get_current_user_id()?:null,'created_at'=>$now,'updated_at'=>$now));
        }
        return true;
    }

    public static function deposit_refunds( $program_id ) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*,pv.target_institution,pv.venue_id,v.venue_name,v.institution_name,v.province_name,v.district_name
             FROM {$wpdb->prefix}mmc_deposit_refunds r
             LEFT JOIN {$wpdb->prefix}mmc_program_venues pv ON pv.id=r.program_venue_id
             LEFT JOIN {$wpdb->prefix}mmc_venues v ON v.id=pv.venue_id
             WHERE r.program_id=%d ORDER BY r.id ASC",absint($program_id)
        ));
    }

    public static function generate_refund_letter( $refund_id ) {
        global $wpdb;
        $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_deposit_refunds WHERE id=%d",absint($refund_id)));
        if(!$r)return new WP_Error('mmc_refund_missing','Teminat iade kaydı bulunamadı.');
        $program=MMC_Program_Service::get_program($r->program_id); if(!$program)return new WP_Error('mmc_refund_program','Program bulunamadı.');
        $pv=$r->program_venue_id?$wpdb->get_row($wpdb->prepare("SELECT pv.*,v.venue_name,v.institution_name FROM {$wpdb->prefix}mmc_program_venues pv LEFT JOIN {$wpdb->prefix}mmc_venues v ON v.id=pv.venue_id WHERE pv.id=%d",(int)$r->program_venue_id)):null;
        $institution=$pv?trim((string)($pv->target_institution?:$pv->institution_name)):''; if(!$institution)$institution=$program->province_name.' Gençlik ve Spor İl Müdürlüğü';
        $venue=$pv?(string)$pv->venue_name:'';
        $body="T.C.\n".mb_strtoupper($institution,'UTF-8')."\n\n";
        $body.="Konu: Salon Teminat Bedelinin İadesi Talebi\n\n";
        $body.=$program->province_name.' / '.($program->district_name?:'Merkez')." bölgesinde düzenlenen Madagaskar Sirki programı kapsamında";
        if($venue)$body.=" $venue için";
        $body.=" yatırılmış bulunan ".number_format_i18n((float)$r->deposit_amount,2)." TL teminat bedelinin, salon kullanım ve teslim işlemlerinin tamamlanmış olması nedeniyle şirketimize iade edilmesi hususunda gereğini arz ederiz.\n\n";
        $body.="Saygılarımızla,\nDünya Organizasyon Medya Turizm Eğitim Danışmanlık Reklam Seyahat Acenteliği Ltd. Şti.\n";
        $body.="Kızılay Mahallesi, Menekşe 1 Caddesi, Şeref Apartmanı No: 3, İç Kapı No: 11, Çankaya / Ankara\nTelefon: +90 506 034 38 74\n";
        $docs=$wpdb->prefix.'mmc_documents'; $now=current_time('mysql');
        if($r->request_document_id){$wpdb->update($docs,array('title'=>'Salon Teminat İade Talep Dilekçesi — '.$program->program_code,'content'=>$body,'status'=>'draft','updated_at'=>$now),array('id'=>(int)$r->request_document_id));$doc_id=(int)$r->request_document_id;}
        else{$wpdb->insert($docs,array('program_id'=>$r->program_id,'entity_type'=>'deposit_refund','entity_id'=>$r->id,'doc_type'=>'deposit_refund_request','title'=>'Salon Teminat İade Talep Dilekçesi — '.$program->program_code,'content'=>$body,'status'=>'draft','created_by'=>get_current_user_id()?:null,'created_at'=>$now,'updated_at'=>$now));$doc_id=(int)$wpdb->insert_id;}
        $wpdb->update($wpdb->prefix.'mmc_deposit_refunds',array('request_document_id'=>$doc_id,'status'=>'ready','updated_at'=>$now),array('id'=>(int)$r->id));
        MMC_Program_Service::add_log($r->program_id,'deposit_refund_letter_generated','document',$doc_id,null,null,'Teminat iade dilekçesi hazırlandı.');
        return $doc_id;
    }

    public static function get_document( $document_id ) {
        global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_documents WHERE id=%d",absint($document_id)));
    }

    public static function update_deposit_refund( $refund_id, $data ) {
        global $wpdb;
        $table=$wpdb->prefix.'mmc_deposit_refunds'; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",absint($refund_id)));
        if(!$r)return new WP_Error('mmc_refund_missing','Teminat iade kaydı bulunamadı.');
        $status=sanitize_key($data['status']??$r->status); if(!isset(self::refund_statuses()[$status]))$status=$r->status;
        $refunded=self::money($data['refunded_amount']??$r->refunded_amount); $deduction=self::money($data['deduction_amount']??$r->deduction_amount);
        $max=(float)$r->deposit_amount; if($refunded+$deduction>$max+0.01)return new WP_Error('mmc_refund_amount','İade + kesinti toplamı teminat tutarını aşamaz.');
        if($refunded+$deduction>=$max-0.01)$status='resolved'; elseif($refunded>0)$status='partial';
        $now=current_time('mysql');
        $update=array('status'=>$status,'refunded_amount'=>$refunded,'deduction_amount'=>$deduction,'request_date'=>self::date_or_null($data['request_date']??$r->request_date),'outgoing_reference'=>sanitize_text_field($data['outgoing_reference']??$r->outgoing_reference),'refund_reference'=>sanitize_text_field($data['refund_reference']??$r->refund_reference),'deduction_reason'=>sanitize_textarea_field($data['deduction_reason']??$r->deduction_reason),'notes'=>sanitize_textarea_field($data['notes']??$r->notes),'updated_at'=>$now);
        if('resolved'===$status)$update['refunded_at']=$r->refunded_at?:$now;
        $ok=$wpdb->update($table,$update,array('id'=>(int)$r->id)); if(false===$ok)return new WP_Error('mmc_refund_update','Teminat iade kaydı güncellenemedi.');
        if('resolved'===$status){
            $wpdb->update($wpdb->prefix.'mmc_finance_entries',array('status'=>'refunded','refunded_at'=>$now,'updated_at'=>$now),array('id'=>(int)$r->finance_entry_id));
            self::upsert_deposit_deduction($r,$deduction,sanitize_textarea_field($data['deduction_reason']??''));
        }
        MMC_Program_Service::add_log($r->program_id,'deposit_refund_updated','deposit_refund',$r->id,$r->status,$status,'Teminat iade takibi güncellendi.');
        self::refresh_closure_snapshot($r->program_id);
        self::finalize_if_ready($r->program_id);
        return true;
    }

    private static function upsert_deposit_deduction( $refund, $amount, $reason ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_finance_entries';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE program_id=%d AND related_entity_type='deposit_refund' AND related_entity_id=%d AND category='deposit_deduction'",(int)$refund->program_id,(int)$refund->id));
        if($amount<=0){if($row&&'paid'!==$row->status)$wpdb->update($table,array('status'=>'cancelled','amount'=>0,'updated_at'=>current_time('mysql')),array('id'=>(int)$row->id));return;}
        $data=array('entry_class'=>'expense','category'=>'deposit_deduction','title'=>'Salon teminat kesintisi','amount'=>$amount,'status'=>'incurred','notes'=>$reason,'paid_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'));
        if($row){$wpdb->update($table,$data,array('id'=>(int)$row->id));}
        else{$data=array_merge($data,array('program_id'=>(int)$refund->program_id,'related_entity_type'=>'deposit_refund','related_entity_id'=>(int)$refund->id,'created_by'=>get_current_user_id()?:null,'created_at'=>current_time('mysql')));$wpdb->insert($table,$data);}
    }

    public static function ensure_closure( $program_id ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_financial_closures'; $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE program_id=%d",absint($program_id))); if($existing)return $existing;
        $now=current_time('mysql');$wpdb->insert($table,array('program_id'=>absint($program_id),'created_at'=>$now,'updated_at'=>$now));
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE program_id=%d",absint($program_id)));
    }

    public static function closure( $program_id ) { global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_financial_closures WHERE program_id=%d",absint($program_id))); }

    public static function save_closure_inputs( $program_id, $data ) {
        global $wpdb; self::ensure_closure($program_id);
        $ok=$wpdb->update($wpdb->prefix.'mmc_financial_closures',array('manual_paid_audience'=>absint($data['manual_paid_audience']??0),'free_audience'=>absint($data['free_audience']??0),'notes'=>sanitize_textarea_field($data['notes']??''),'updated_at'=>current_time('mysql')),array('program_id'=>absint($program_id)));
        if(false===$ok)return new WP_Error('mmc_close_input','Kapanış bilgileri kaydedilemedi.'); self::refresh_closure_snapshot($program_id); return true;
    }

    public static function summary( $program_id ) {
        global $wpdb; $program_id=absint($program_id); self::sync_external_sources($program_id);
        $sales=$wpdb->get_row($wpdb->prepare("SELECT COUNT(DISTINCT external_order_id) orders_count,COALESCE(SUM(net_quantity),0) tickets,COALESCE(SUM(capacity_units),0) web_audience,COALESCE(SUM(gross_amount),0) gross_revenue,COALESCE(SUM(refunded_amount),0) refunds,COALESCE(SUM(net_amount),0) web_revenue FROM {$wpdb->prefix}mmc_sales_ledger WHERE program_id=%d",$program_id),ARRAY_A);
        $entries=self::entries($program_id); $manual_income=0;$actual_expense=0;$planned_expense=0;$pending_finance=0;$deposit_paid=0;$unpaid_deposits=0;
        foreach($entries as $e){
            if('cancelled'===$e->status)continue;
            if('income'===$e->entry_class && in_array($e->status,array('paid','realized','incurred'),true))$manual_income+=(float)$e->amount;
            if('expense'===$e->entry_class){if(in_array($e->status,array('paid','incurred','realized'),true))$actual_expense+=(float)$e->amount; else $planned_expense+=(float)$e->amount;}
            if('deposit_asset'===$e->entry_class && in_array($e->status,array('paid','refunded'),true))$deposit_paid+=(float)$e->amount;
            if('deposit_asset'===$e->entry_class && in_array($e->status,array('planned','pending'),true) && (float)$e->amount>0)$unpaid_deposits++;
            if(in_array($e->status,array('planned','pending'),true) && 'deposit_asset'!==$e->entry_class)$pending_finance++;
        }
        $refund_rows=self::deposit_refunds($program_id);$dep_refunded=0;$dep_deducted=0;$dep_outstanding=0;$unresolved=0;
        foreach($refund_rows as $r){$dep_refunded+=(float)$r->refunded_amount;$dep_deducted+=(float)$r->deduction_amount;$remaining=max(0,(float)$r->deposit_amount-(float)$r->refunded_amount-(float)$r->deduction_amount);$dep_outstanding+=$remaining;if('resolved'!==$r->status&&'cancelled'!==$r->status)$unresolved++;}
        $pending_invoices=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}mmc_invoices WHERE program_id=%d AND status='pending'",$program_id));
        $closure=self::ensure_closure($program_id); $web_aud=(int)($sales['web_audience']??0);$manual=(int)$closure->manual_paid_audience;$free=(int)$closure->free_audience;$total_aud=$web_aud+$manual+$free;
        $total_rev=(float)($sales['web_revenue']??0)+$manual_income;$profit=$total_rev-$actual_expense;$margin=$total_rev>0?($profit/$total_rev)*100:0;
        return array('sales'=>$sales,'manual_income'=>$manual_income,'total_revenue'=>$total_rev,'actual_expense'=>$actual_expense,'planned_expense'=>$planned_expense,'pending_finance'=>$pending_finance,'net_profit'=>$profit,'profit_margin'=>$margin,'deposit_paid'=>$deposit_paid,'unpaid_deposits'=>$unpaid_deposits,'deposit_refunded'=>$dep_refunded,'deposit_deducted'=>$dep_deducted,'deposit_outstanding'=>$dep_outstanding,'unresolved_deposits'=>$unresolved,'pending_invoices'=>$pending_invoices,'web_audience'=>$web_aud,'manual_paid_audience'=>$manual,'free_audience'=>$free,'total_audience'=>$total_aud,'revenue_per_person'=>$total_aud>0?$total_rev/$total_aud:0,'expense_per_person'=>$total_aud>0?$actual_expense/$total_aud:0,'closure'=>$closure);
    }

    public static function readiness( $program_id ) {
        $s=self::summary($program_id); $blockers=array();
        if($s['pending_finance']>0)$blockers[]=$s['pending_finance'].' gelir/gider kaydı planlandı/bekliyor durumunda.';
        if($s['unpaid_deposits']>0)$blockers[]=$s['unpaid_deposits'].' salon teminatı henüz yatırılmamış / ödeme bekliyor.';
        if($s['pending_invoices']>0)$blockers[]=$s['pending_invoices'].' satış faturası henüz sonuçlandırılmadı.';
        if($s['total_revenue']<=0)$blockers[]='Toplam gerçekleşmiş gelir sıfır; satış/gelir mutabakatını kontrol edin.';
        return array('ready'=>empty($blockers),'blockers'=>$blockers,'summary'=>$s);
    }

    public static function request_close( $program_id ) {
        self::sync_external_sources($program_id); self::sync_invoice_queue($program_id); self::ensure_deposit_refunds($program_id);
        $r=self::readiness($program_id); if(!$r['ready'])return new WP_Error('mmc_close_blocked',implode(' ', $r['blockers']));
        if($r['summary']['unresolved_deposits']>0 || $r['summary']['deposit_outstanding']>0.01){
            self::set_close_status($program_id,'awaiting_deposit');
            MMC_Program_Service::set_status($program_id,'deposit_refund','Gelir-gider mutabakatı tamamlandı; salon teminat iadesi bekleniyor.');
            return 'awaiting_deposit';
        }
        self::finalize($program_id); return 'completed';
    }

    public static function finalize_if_ready( $program_id ) {
        $r=self::readiness($program_id); if(!$r['ready'])return false;
        if($r['summary']['unresolved_deposits']>0 || $r['summary']['deposit_outstanding']>0.01)return false;
        return self::finalize($program_id);
    }

    private static function finalize( $program_id ) {
        global $wpdb; $s=self::summary($program_id); $now=current_time('mysql');
        $snapshot=wp_json_encode($s,JSON_UNESCAPED_UNICODE);
        $wpdb->update($wpdb->prefix.'mmc_financial_closures',array('total_revenue'=>$s['total_revenue'],'total_expense'=>$s['actual_expense'],'net_profit'=>$s['net_profit'],'profit_margin'=>$s['profit_margin'],'total_audience'=>$s['total_audience'],'revenue_per_person'=>$s['revenue_per_person'],'expense_per_person'=>$s['expense_per_person'],'outstanding_deposit'=>$s['deposit_outstanding'],'pending_invoice_count'=>$s['pending_invoices'],'close_status'=>'closed','snapshot_json'=>$snapshot,'closed_by'=>get_current_user_id()?:null,'closed_at'=>$now,'updated_at'=>$now),array('program_id'=>absint($program_id)));
        MMC_Program_Service::set_status($program_id,'completed','Gelir-gider, fatura ve teminat süreçleri tamamlandı; program finansal olarak kapatıldı.');
        MMC_Program_Service::add_log($program_id,'financial_close_completed','financial_closure',null,null,array('net_profit'=>$s['net_profit'],'total_revenue'=>$s['total_revenue'],'total_expense'=>$s['actual_expense']),'Program finansal olarak kapatıldı.');
        return true;
    }

    private static function set_close_status($program_id,$status){global $wpdb;$s=self::summary($program_id);$wpdb->update($wpdb->prefix.'mmc_financial_closures',array('total_revenue'=>$s['total_revenue'],'total_expense'=>$s['actual_expense'],'net_profit'=>$s['net_profit'],'profit_margin'=>$s['profit_margin'],'total_audience'=>$s['total_audience'],'revenue_per_person'=>$s['revenue_per_person'],'expense_per_person'=>$s['expense_per_person'],'outstanding_deposit'=>$s['deposit_outstanding'],'pending_invoice_count'=>$s['pending_invoices'],'close_status'=>$status,'snapshot_json'=>wp_json_encode($s,JSON_UNESCAPED_UNICODE),'updated_at'=>current_time('mysql')),array('program_id'=>absint($program_id)));}
    private static function refresh_closure_snapshot($program_id){$c=self::ensure_closure($program_id);if('closed'===$c->close_status)return; $s=self::summary_light($program_id);global $wpdb;$wpdb->update($wpdb->prefix.'mmc_financial_closures',array('total_revenue'=>$s['total_revenue'],'total_expense'=>$s['actual_expense'],'net_profit'=>$s['net_profit'],'profit_margin'=>$s['profit_margin'],'total_audience'=>$s['total_audience'],'revenue_per_person'=>$s['revenue_per_person'],'expense_per_person'=>$s['expense_per_person'],'outstanding_deposit'=>$s['deposit_outstanding'],'pending_invoice_count'=>$s['pending_invoices'],'snapshot_json'=>wp_json_encode($s,JSON_UNESCAPED_UNICODE),'updated_at'=>current_time('mysql')),array('program_id'=>absint($program_id)));}
    private static function summary_light($program_id){global $wpdb;$sales=$wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(capacity_units),0) web_audience,COALESCE(SUM(net_amount),0) web_revenue FROM {$wpdb->prefix}mmc_sales_ledger WHERE program_id=%d",absint($program_id)),ARRAY_A);$entries=self::entries($program_id);$inc=0;$exp=0;foreach($entries as $e){if('cancelled'===$e->status)continue;if('income'===$e->entry_class&&in_array($e->status,array('paid','realized','incurred'),true))$inc+=(float)$e->amount;if('expense'===$e->entry_class&&in_array($e->status,array('paid','incurred','realized'),true))$exp+=(float)$e->amount;}$dep=0;foreach(self::deposit_refunds($program_id) as $r)$dep+=max(0,(float)$r->deposit_amount-(float)$r->refunded_amount-(float)$r->deduction_amount);$pending=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}mmc_invoices WHERE program_id=%d AND status='pending'",absint($program_id)));$c=self::ensure_closure($program_id);$aud=(int)($sales['web_audience']??0)+(int)$c->manual_paid_audience+(int)$c->free_audience;$rev=(float)($sales['web_revenue']??0)+$inc;$profit=$rev-$exp;return array('total_revenue'=>$rev,'actual_expense'=>$exp,'net_profit'=>$profit,'profit_margin'=>$rev>0?$profit/$rev*100:0,'total_audience'=>$aud,'revenue_per_person'=>$aud>0?$rev/$aud:0,'expense_per_person'=>$aud>0?$exp/$aud:0,'deposit_outstanding'=>$dep,'pending_invoices'=>$pending);}

    public static function on_program_log( $program_id, $action, $entity_type, $entity_id, $old_value, $new_value, $note ) {
        if(!$program_id || 0===strpos((string)$action,'finance_') || 0===strpos((string)$action,'deposit_refund_'))return;
        if(in_array($action,array('meta_plan_updated','sales_manual_sync','venue_finance_updated','operation_plan_saved','program_status_changed'),true))self::sync_external_sources($program_id);
    }

    private static function date_or_null($v){$v=sanitize_text_field((string)$v);return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)?$v:null;}
    private static function money($v){if(is_string($v)){$v=trim($v);if(false!==strpos($v,',')){$v=str_replace('.','',$v);$v=str_replace(',','.',$v);}}return round(max(0,(float)$v),2);}
}
