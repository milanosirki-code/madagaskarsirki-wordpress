<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Kommo + AI bridge for Madagaskar Management Center.
 *
 * Design goals:
 * - MMC remains the source of truth.
 * - Kommo receives a stable, tokenized URL that always renders the current
 *   verified Program/Event facts.
 * - CRM program tracking is optional and only enabled when a pipeline id is set.
 * - Kommo API secrets are read from wp-config constants; they are never stored
 *   by this plugin in plain text.
 * - Public Kommo AI docs do not expose a safe update method for text/file
 *   sources. URL sources are therefore used. If an already-added URL source
 *   needs reparsing, MMC marks it as refresh_needed instead of creating stale
 *   duplicate sources.
 */
class MMC_Kommo_Service {
    const CRON_HOOK = 'mmc_kommo_process_queue';

    public static function hooks() {
        add_action( 'init', array( __CLASS__, 'register_rewrite' ) );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_source' ) );
        add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
        add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ) );
        add_action( 'mmc_program_logged', array( __CLASS__, 'on_program_log' ), 10, 7 );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 300, 'mmc_15min', self::CRON_HOOK );
        }
    }

    public static function register_rewrite() {
        add_rewrite_tag( '%mmc_kommo_source%', '([A-Za-z0-9_-]{32,80})' );
        add_rewrite_rule( '^madagaskar-kommo-source/([A-Za-z0-9_-]{32,80})/?$', 'index.php?mmc_kommo_source=$matches[1]', 'top' );
    }

    public static function query_vars( $vars ) {
        $vars[] = 'mmc_kommo_source';
        return $vars;
    }

    public static function cron_schedules( $schedules ) {
        if ( ! isset( $schedules['mmc_15min'] ) ) {
            $schedules['mmc_15min'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'MMC her 15 dakika' );
        }
        return $schedules;
    }

    public static function maybe_serve_source() {
        $token = get_query_var( 'mmc_kommo_source' );
        if ( ! $token ) return;

        $profile = self::profile_by_token( $token );
        if ( ! $profile ) {
            status_header( 404 );
            nocache_headers();
            header( 'Content-Type: text/plain; charset=utf-8' );
            echo 'Kaynak bulunamadı.'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        $text = self::build_source_text( (int) $profile->program_id );
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
        header( 'Referrer-Policy: no-referrer', true );
        echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentionally plain text source.
        exit;
    }

    public static function ensure_profile( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) return new WP_Error( 'mmc_kommo_program_missing', 'Program bulunamadı.' );

        $table = $wpdb->prefix . 'mmc_kommo_profiles';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE program_id=%d LIMIT 1", $program_id ) );
        $event = class_exists( 'MMC_Event_Service' ) ? MMC_Event_Service::event_for_program( $program_id ) : null;
        $now = current_time( 'mysql' );

        if ( ! $row ) {
            $token = wp_generate_password( 48, false, false );
            $wpdb->insert( $table, array(
                'program_id'       => $program_id,
                'event_id'         => $event ? (int) $event->id : null,
                'source_token'     => $token,
                'source_url'       => self::source_url_from_token( $token ),
                'source_hash'      => '',
                'ai_synced_hash'   => '',
                'search_keywords'  => '',
                'ai_source_status' => 'not_synced',
                'crm_status'       => 'not_synced',
                'created_at'       => $now,
                'updated_at'       => $now,
            ) );
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", (int) $wpdb->insert_id ) );
            self::seed_templates( $program_id, $event ? (int)$event->id : 0 );
        }

        $text = self::build_source_text( $program_id );
        $hash = hash( 'sha256', $text );
        $keywords = implode( ', ', self::search_keywords( $program_id ) );
        $event_id = $event ? (int)$event->id : null;
        $ai_status = (string) $row->ai_source_status;
        if ( $row->ai_source_id && $row->ai_synced_hash && $row->ai_synced_hash !== $hash && 'error' !== $ai_status ) {
            $ai_status = 'refresh_needed';
        }

        $wpdb->update( $table, array(
            'event_id'         => $event_id,
            'source_url'       => self::source_url_from_token( $row->source_token ),
            'source_hash'      => $hash,
            'search_keywords'  => $keywords,
            'ai_source_status' => $ai_status,
            'updated_at'       => $now,
        ), array( 'id' => (int)$row->id ) );

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", (int)$row->id ) );
    }

    public static function get_profile( $program_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_kommo_profiles WHERE program_id=%d LIMIT 1", absint($program_id) ) );
    }

    public static function profile_by_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_kommo_profiles WHERE source_token=%s LIMIT 1", sanitize_text_field($token) ) );
    }

    public static function source_url_from_token( $token ) {
        return home_url( '/madagaskar-kommo-source/' . rawurlencode( $token ) . '/' );
    }

    public static function search_keywords( $program_id ) {
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) return array();
        $event = class_exists('MMC_Event_Service') ? MMC_Event_Service::event_for_program($program_id) : null;
        $venue = ( $event && $event->program_venue_id && class_exists('MMC_Venue_Service') ) ? MMC_Venue_Service::get_program_venue($event->program_venue_id) : null;
        $values = array(
            'Madagaskar Sirki', 'Madagaskar', 'sirk',
            $program->province_name,
            $program->district_name,
            $program->province_name . ' sirki',
            $program->district_name ? $program->district_name . ' sirki' : '',
            $event ? $event->event_title : '',
            $venue ? $venue->venue_name : '',
            $venue ? $venue->institution_name : '',
        );
        if ( $event && $event->event_date ) {
            $values[] = wp_date( 'd.m.Y', strtotime($event->event_date) );
            $values[] = wp_date( 'j F Y', strtotime($event->event_date) );
        }
        $expanded = array();
        foreach ( $values as $v ) {
            $v = trim( (string)$v );
            if ( '' === $v ) continue;
            $expanded[] = $v;
            $ascii = remove_accents( $v );
            if ( $ascii !== $v ) $expanded[] = $ascii;
        }
        $expanded = array_values( array_unique( array_filter( array_map( 'trim', $expanded ) ) ) );
        return $expanded;
    }

    public static function build_source_text( $program_id ) {
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) return 'Program bulunamadı.';
        $statuses = MMC_Program_Service::statuses();
        $event = class_exists('MMC_Event_Service') ? MMC_Event_Service::event_for_program($program_id) : null;
        $venue = ( $event && $event->program_venue_id && class_exists('MMC_Venue_Service') ) ? MMC_Venue_Service::get_program_venue($event->program_venue_id) : null;
        $sessions = $event ? MMC_Event_Service::sessions($event->id) : array();
        $tickets  = $event ? MMC_Event_Service::ticket_types($event->id) : array();
        $biletinial = $event ? MMC_Event_Service::channel_prices($event->id, 'biletinial') : array();
        $bmap = array(); foreach ( $biletinial as $b ) { $bmap[(int)$b->ticket_type_id] = $b; }
        $sales = ( $event && class_exists('MMC_Sales_Service') ) ? MMC_Sales_Service::summary($event->id) : array();
        $cancelled = 'cancelled' === $program->status || ( $event && 'cancelled' === $event->status );

        $lines = array();
        $lines[] = 'MADAGASKAR SİRKİ — GÜNCEL PROGRAM KAYNAĞI';
        $lines[] = 'Program Kodu: ' . $program->program_code;
        $lines[] = 'Program Durumu: ' . ( $statuses[$program->status] ?? $program->status );
        $lines[] = 'Son Güncelleme: ' . wp_date( 'd.m.Y H:i' );
        $lines[] = '';

        if ( $cancelled ) {
            $lines[] = 'KRİTİK: BU PROGRAM İPTALDİR. Müşteriye aktif etkinlik veya satış seçeneği olarak sunulmaz.';
            $lines[] = 'İl: ' . $program->province_name;
            $lines[] = 'İlçe: ' . ( $program->district_name ?: 'Genel' );
            if ( $event && $event->event_date ) $lines[] = 'Eski Etkinlik Tarihi: ' . wp_date( 'd.m.Y', strtotime($event->event_date) );
            return implode( "\n", $lines );
        }

        $lines[] = 'Marka: Madagaskar Sirki';
        $lines[] = 'Konsept: Uluslararası sanatçılarla hazırlanan, tamamen hayvansız, ailelere uygun canlı sirk deneyimi.';
        $lines[] = 'İl: ' . $program->province_name;
        $lines[] = 'İlçe: ' . ( $program->district_name ?: 'Genel' );
        if ( $event ) {
            $lines[] = 'Etkinlik: ' . $event->event_title;
            $lines[] = 'Etkinlik Durumu: ' . ( MMC_Event_Service::event_statuses()[$event->status] ?? $event->status );
            $lines[] = 'Tarih: ' . ( $event->event_date ? wp_date('d.m.Y', strtotime($event->event_date)) : 'Kesinleşmedi' );
        }
        if ( $venue ) {
            $lines[] = 'Salon: ' . $venue->venue_name;
            $lines[] = 'Adres: ' . trim( $venue->address . ' ' . $venue->district_name . ' / ' . $venue->province_name );
            if ( $venue->maps_url ) $lines[] = 'Konum: ' . $venue->maps_url;
        } else {
            $lines[] = 'Salon: Henüz kesinleşmedi.';
        }

        $lines[] = '';
        $lines[] = 'SEANSLAR';
        if ( $sessions ) {
            foreach ( $sessions as $s ) {
                $lines[] = '- ' . wp_date( 'H:i', strtotime($s->session_time) ) . ' | Kapasite: ' . (int)$s->capacity;
            }
        } else {
            $lines[] = '- Seanslar henüz kesinleşmedi.';
        }

        $lines[] = '';
        $lines[] = 'BİLET VE YAŞ POLİTİKASI';
        $lines[] = '- 0–2 yaş: Ücretsiz';
        $lines[] = '- 3–12 yaş: Çocuk bileti';
        $lines[] = '- 13 yaş ve üzeri: Yetişkin bileti';
        $lines[] = '- Çocuklar yetişkin eşliğinde katılır.';
        foreach ( $tickets as $t ) {
            if ( ! (int)$t->is_active ) continue;
            $row = '- ' . $t->ticket_name . ': ' . number_format_i18n((float)$t->price, 2) . ' TL (madagaskarsirki.com)';
            if ( isset($bmap[(int)$t->id]) && null !== $bmap[(int)$t->id]->channel_price ) {
                $row .= ' | Biletinial: ' . number_format_i18n((float)$bmap[(int)$t->id]->channel_price, 2) . ' TL + varsa hizmet bedeli';
            }
            if ( 'family_2_2' === $t->ticket_code ) $row .= ' | 2 yetişkin + 2 çocuk, 4 kişi kapasite tüketir';
            $lines[] = $row;
        }
        $lines[] = 'Resmî merkezi bilet sayfası: https://madagaskarsirki.com/bilet-al/';
        $lines[] = 'Kapıda nakit ve kredi kartı ile ödeme yapılabilir.';

        if ( $sales ) {
            $lines[] = '';
            $lines[] = 'CANLI SATIŞ ÖZETİ';
            $lines[] = '- Satılan kişi kapasitesi: ' . (int)($sales['sold_capacity'] ?? 0);
            $lines[] = '- Net ciro: ' . number_format_i18n((float)($sales['net_revenue'] ?? 0), 2) . ' TL';
        }

        $lines[] = '';
        $lines[] = 'MÜŞTERİ İLETİŞİM KURALLARI';
        $lines[] = '- Müşteriye bütün aktif seansları göster; müşteri adına seans seçme.';
        $lines[] = '- Kesinleşmemiş salon, tarih, seans veya fiyatı kesinmiş gibi söyleme.';
        $lines[] = '- İade, ödeme uyuşmazlığı, başarısız ödeme, yanlış bilet ve özel kampanya uyuşmazlıklarını canlı temsilciye aktar.';
        $lines[] = '- WhatsApp / dijital bilgi hattı: +90 312 911 37 10';
        $lines[] = 'Arama kelimeleri: ' . implode( ', ', self::search_keywords($program_id) );

        return implode( "\n", $lines );
    }

    public static function enqueue_program_sync( $program_id, $reason = '' ) {
        $profile = self::ensure_profile( $program_id );
        if ( is_wp_error($profile) ) return $profile;
        self::enqueue_job( $program_id, (int)$profile->event_id, 'program_lead_sync', array('reason'=>$reason) );
        self::enqueue_job( $program_id, (int)$profile->event_id, 'ai_source_sync', array('reason'=>$reason) );
        return true;
    }

    public static function enqueue_job( $program_id, $event_id, $job_type, $payload = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_kommo_queue';
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE program_id=%d AND job_type=%s AND status IN ('queued','waiting_config') ORDER BY id DESC LIMIT 1",
            absint($program_id), sanitize_key($job_type)
        ) );
        $now = current_time('mysql');
        $data = array(
            'program_id'=>absint($program_id),'event_id'=>$event_id?absint($event_id):null,
            'job_type'=>sanitize_key($job_type),'payload'=>wp_json_encode($payload,JSON_UNESCAPED_UNICODE),
            'status'=>'queued','available_at'=>$now,'last_error'=>'','updated_at'=>$now,
        );
        if ( $existing ) return $wpdb->update($table,$data,array('id'=>(int)$existing));
        $data['attempts']=0; $data['created_at']=$now;
        return $wpdb->insert($table,$data);
    }

    public static function queue_rows( $program_id, $limit = 25 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mmc_kommo_queue WHERE program_id=%d ORDER BY id DESC LIMIT %d",
            absint($program_id), max(1,min(100,absint($limit)))
        ) );
    }

    public static function on_program_log( $program_id, $action, $entity_type, $entity_id, $old_value, $new_value, $note ) {
        if ( ! $program_id || 0 === strpos( (string)$action, 'kommo_' ) ) return;
        $interesting = array('program','event','session','ticket_type','integration','program_venue','finance_entry');
        if ( ! in_array( $entity_type, $interesting, true ) ) return;
        self::enqueue_program_sync( $program_id, $action );
    }

    public static function process_queue( $limit = 10 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_kommo_queue';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE status='queued' AND available_at<=%s ORDER BY id ASC LIMIT %d",
            current_time('mysql'), max(1,min(25,absint($limit)))
        ) );
        foreach ( $rows as $job ) {
            self::process_job( $job );
        }
    }

    private static function process_job( $job ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_kommo_queue';
        $wpdb->update($table,array('status'=>'running','attempts'=>(int)$job->attempts+1,'updated_at'=>current_time('mysql')),array('id'=>(int)$job->id));
        $result = 'ai_source_sync' === $job->job_type ? self::sync_ai_source((int)$job->program_id) : self::sync_program_lead((int)$job->program_id);
        if ( is_wp_error($result) ) {
            $code = $result->get_error_code();
            $waiting = in_array($code,array('mmc_kommo_not_configured','mmc_kommo_pipeline_missing'),true);
            $wpdb->update($table,array(
                'status'=>$waiting?'waiting_config':'error',
                'last_error'=>$result->get_error_message(),
                'processed_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')
            ),array('id'=>(int)$job->id));
            return;
        }
        $wpdb->update($table,array('status'=>'done','last_error'=>'','processed_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')),array('id'=>(int)$job->id));
    }

    public static function sync_now( $program_id ) {
        self::enqueue_program_sync( $program_id, 'manual_sync' );
        self::process_queue( 10 );
        return true;
    }

    public static function sync_ai_source( $program_id ) {
        global $wpdb;
        $profile = self::ensure_profile($program_id);
        if ( is_wp_error($profile) ) return $profile;
        if ( ! self::configured() ) return new WP_Error('mmc_kommo_not_configured','Kommo subdomain/token yapılandırılmadı.');

        // Existing URL source: do not create duplicate stale sources. Official public docs
        // describe reparsing URL sources but do not expose a documented AI-source refresh endpoint.
        if ( $profile->ai_source_id ) {
            if ( $profile->ai_synced_hash !== $profile->source_hash ) {
                $wpdb->update($wpdb->prefix.'mmc_kommo_profiles',array(
                    'ai_source_status'=>'refresh_needed','updated_at'=>current_time('mysql')
                ),array('id'=>(int)$profile->id));
                return true;
            }
            return true;
        }

        $mode = get_option('mmc_kommo_ai_mode','suggested_reply');
        if ( ! in_array($mode,array('suggested_reply','agent'),true) ) $mode='suggested_reply';
        $response = self::api_request(
            'https://airewriter.kommo.com/api/v2/sources/url',
            'POST',
            array('url'=>$profile->source_url,'with_nested'=>false,'available_functions'=>array($mode))
        );
        if ( is_wp_error($response) ) {
            self::profile_error($profile->id,'ai_source_status',$response->get_error_message());
            return $response;
        }
        $id = isset($response['id']) ? (string)$response['id'] : '';
        $wpdb->update($wpdb->prefix.'mmc_kommo_profiles',array(
            'ai_source_id'=>$id,'ai_source_status'=>'synced','ai_synced_hash'=>$profile->source_hash,
            'last_synced_at'=>current_time('mysql'),'last_error'=>'','updated_at'=>current_time('mysql')
        ),array('id'=>(int)$profile->id));
        MMC_Program_Service::add_log($program_id,'kommo_ai_source_added','program',$program_id,null,array('source_id'=>$id),'Kommo AI URL kaynağı eklendi.');
        return true;
    }

    public static function mark_ai_refreshed( $program_id ) {
        global $wpdb;
        $profile=self::ensure_profile($program_id); if(is_wp_error($profile))return $profile;
        $wpdb->update($wpdb->prefix.'mmc_kommo_profiles',array(
            'ai_source_status'=>'synced','ai_synced_hash'=>$profile->source_hash,'last_synced_at'=>current_time('mysql'),'last_error'=>'','updated_at'=>current_time('mysql')
        ),array('id'=>(int)$profile->id));
        MMC_Program_Service::add_log($program_id,'kommo_ai_source_refreshed','program',$program_id,null,null,'Kommo AI URL kaynağı yeniden tarandı olarak işaretlendi.');
        return true;
    }

    public static function sync_program_lead( $program_id ) {
        global $wpdb;
        $profile=self::ensure_profile($program_id); if(is_wp_error($profile))return $profile;
        if ( ! self::configured() ) return new WP_Error('mmc_kommo_not_configured','Kommo subdomain/token yapılandırılmadı.');
        $pipeline_id=absint(get_option('mmc_kommo_pipeline_id',0));
        if(!$pipeline_id) return new WP_Error('mmc_kommo_pipeline_missing','Kommo Program pipeline ID tanımlanmadı; CRM program kaydı atlandı.');
        $status_id=absint(get_option('mmc_kommo_status_id',0));
        $program=MMC_Program_Service::get_program($program_id); if(!$program)return new WP_Error('mmc_kommo_program_missing','Program bulunamadı.');
        $event=MMC_Event_Service::event_for_program($program_id);
        $name='Madagaskar | '.$program->province_name.' / '.($program->district_name?:'Genel');
        if($event&&$event->event_date)$name.=' | '.wp_date('d.m.Y',strtotime($event->event_date));
        $lead=array('name'=>$name,'pipeline_id'=>$pipeline_id);
        if($status_id)$lead['status_id']=$status_id;
        if($profile->kommo_lead_id){
            $lead['id']=(int)$profile->kommo_lead_id;
            $response=self::api_request(self::crm_base().'/leads','PATCH',array($lead));
        }else{
            $response=self::api_request(self::crm_base().'/leads','POST',array($lead));
        }
        if(is_wp_error($response)){
            self::profile_error($profile->id,'crm_status',$response->get_error_message()); return $response;
        }
        $lead_id=$profile->kommo_lead_id;
        if(!$lead_id && isset($response['_embedded']['leads'][0]['id']))$lead_id=(string)$response['_embedded']['leads'][0]['id'];
        $wpdb->update($wpdb->prefix.'mmc_kommo_profiles',array(
            'kommo_lead_id'=>$lead_id,'crm_status'=>'synced','last_synced_at'=>current_time('mysql'),'last_error'=>'','updated_at'=>current_time('mysql')
        ),array('id'=>(int)$profile->id));
        MMC_Program_Service::add_log($program_id,'kommo_program_lead_synced','program',$program_id,null,array('lead_id'=>$lead_id),'Kommo program takip kaydı senkronlandı.');
        return true;
    }

    public static function test_connection() {
        if(!self::configured()) return new WP_Error('mmc_kommo_not_configured','Kommo subdomain/token yapılandırılmadı.');
        return self::api_request(self::crm_base().'/account','GET');
    }

    public static function connection_diagnostics( $force = false ) {
        $cfg = self::configuration_status();

        if ( ! $cfg['configured'] ) {
            return array_merge( $cfg, array(
                'connected' => false,
                'http_ok' => false,
                'account_id' => 0,
                'account_name' => '',
                'current_user_id' => 0,
                'error' => 'Kommo subdomain veya token yapılandırılmadı.',
                'checked_at' => '',
            ) );
        }

        $cache_key = 'mmc_kommo_diag_' . md5( $cfg['subdomain'] . '|' . self::token_fingerprint() );
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                return array_merge( $cfg, $cached );
            }
        }

        $r = self::test_connection();
        if ( is_wp_error( $r ) ) {
            $diag = array(
                'connected' => false,
                'http_ok' => false,
                'account_id' => 0,
                'account_name' => '',
                'current_user_id' => 0,
                'error' => $r->get_error_message(),
                'checked_at' => current_time( 'mysql' ),
            );
        } else {
            $diag = array(
                'connected' => true,
                'http_ok' => true,
                'account_id' => isset( $r['id'] ) ? absint( $r['id'] ) : 0,
                'account_name' => sanitize_text_field( $r['name'] ?? '' ),
                'current_user_id' => isset( $r['current_user_id'] ) ? absint( $r['current_user_id'] ) : 0,
                'error' => '',
                'checked_at' => current_time( 'mysql' ),
            );
        }

        set_transient( $cache_key, $diag, 10 * MINUTE_IN_SECONDS );
        return array_merge( $cfg, $diag );
    }

    public static function pipeline_diagnostics( $force = false ) {
        $cfg = self::configuration_status();
        $pipeline_id = (int) $cfg['pipeline_id'];
        $status_id = (int) $cfg['status_id'];

        if ( ! $pipeline_id ) {
            return array(
                'configured' => false,
                'valid' => null,
                'pipeline_id' => 0,
                'pipeline_name' => '',
                'status_id' => $status_id,
                'status_valid' => null,
                'status_name' => '',
                'error' => '',
            );
        }

        if ( ! $cfg['configured'] ) {
            return array(
                'configured' => true,
                'valid' => false,
                'pipeline_id' => $pipeline_id,
                'pipeline_name' => '',
                'status_id' => $status_id,
                'status_valid' => null,
                'status_name' => '',
                'error' => 'Kommo API bağlantısı yapılandırılmadan pipeline doğrulanamaz.',
            );
        }

        $cache_key = 'mmc_kommo_pipeline_' . md5( $cfg['subdomain'] . '|' . $pipeline_id . '|' . $status_id . '|' . self::token_fingerprint() );
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $r = self::api_request( self::crm_base() . '/leads/pipelines/' . $pipeline_id, 'GET' );
        if ( is_wp_error( $r ) ) {
            $out = array(
                'configured' => true,
                'valid' => false,
                'pipeline_id' => $pipeline_id,
                'pipeline_name' => '',
                'status_id' => $status_id,
                'status_valid' => null,
                'status_name' => '',
                'error' => $r->get_error_message(),
            );
            set_transient( $cache_key, $out, 10 * MINUTE_IN_SECONDS );
            return $out;
        }

        $status_valid = null;
        $status_name = '';
        if ( $status_id ) {
            $status_valid = false;
            $statuses = $r['_embedded']['statuses'] ?? array();
            foreach ( (array) $statuses as $row ) {
                if ( (int) ( $row['id'] ?? 0 ) === $status_id ) {
                    $status_valid = true;
                    $status_name = sanitize_text_field( $row['name'] ?? '' );
                    break;
                }
            }
        }

        $out = array(
            'configured' => true,
            'valid' => true,
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => sanitize_text_field( $r['name'] ?? '' ),
            'status_id' => $status_id,
            'status_valid' => $status_valid,
            'status_name' => $status_name,
            'error' => '',
        );
        set_transient( $cache_key, $out, 10 * MINUTE_IN_SECONDS );
        return $out;
    }

    public static function admin_write_readiness( $force = false ) {
        $diag = self::connection_diagnostics( $force );

        if ( empty( $diag['connected'] ) ) {
            return array(
                'verified' => false,
                'is_admin' => null,
                'user_id' => 0,
                'user_name' => '',
                'detail' => $diag['error'] ?: 'Kommo API bağlantısı doğrulanamadı.',
            );
        }

        $user_id = absint( $diag['current_user_id'] ?? 0 );
        if ( ! $user_id ) {
            return array(
                'verified' => false,
                'is_admin' => null,
                'user_id' => 0,
                'user_name' => '',
                'detail' => 'Kommo /account yanıtında current_user_id bulunamadı; yönetici yazma yetkisi ön kontrolde doğrulanamadı.',
            );
        }

        $cache_key = 'mmc_kommo_admin_ready_' . md5( self::subdomain() . '|' . $user_id . '|' . self::token_fingerprint() );
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $r = self::api_request( self::crm_base() . '/users/' . $user_id, 'GET' );
        if ( is_wp_error( $r ) ) {
            $data = $r->get_error_data();
            $status = is_array( $data ) ? absint( $data['status'] ?? 0 ) : 0;
            $out = array(
                'verified' => false,
                'is_admin' => null,
                'user_id' => $user_id,
                'user_name' => '',
                'detail' => $status === 403
                    ? 'Kommo kullanıcı yetkisi endpoint’i 403 döndürdü. Pipeline oluşturma yalnız Kommo yöneticilerine açıktır; mevcut tokenın yönetici kullanıcı adına yetkilendirildiğini kontrol edin.'
                    : 'Kommo kullanıcı yönetici yetkisi doğrulanamadı: ' . $r->get_error_message(),
            );
            set_transient( $cache_key, $out, 10 * MINUTE_IN_SECONDS );
            return $out;
        }

        $is_admin = ! empty( $r['rights']['is_admin'] );
        $out = array(
            'verified' => true,
            'is_admin' => $is_admin,
            'user_id' => $user_id,
            'user_name' => sanitize_text_field( $r['name'] ?? '' ),
            'detail' => $is_admin
                ? 'Kommo current user yönetici olarak doğrulandı.'
                : 'Kommo current user yönetici değil. Pipeline ve stage oluşturma API çağrıları bu kullanıcıyla çalışmaz.',
        );
        set_transient( $cache_key, $out, 10 * MINUTE_IN_SECONDS );
        return $out;
    }

    public static function last_pipeline_install_result() {
        $row = get_option( 'mmc_kommo_last_pipeline_install', array() );
        return is_array( $row ) ? $row : array();
    }

    public static function record_pipeline_install_result( $status, $message, $meta = array() ) {
        $safe_meta = array();
        foreach ( (array) $meta as $key => $value ) {
            if ( is_scalar( $value ) || null === $value ) {
                $safe_meta[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
            }
        }
        update_option(
            'mmc_kommo_last_pipeline_install',
            array(
                'status' => sanitize_key( $status ),
                'message' => sanitize_textarea_field( $message ),
                'meta' => $safe_meta,
                'at' => current_time( 'mysql' ),
            ),
            false
        );
    }

    public static function program_pipeline_blueprint() {
        return array(
            'name' => 'MMC — Program Yönetimi',
            'stages' => array(
                array( 'name'=>'Hazırlık',               'sort'=>10,  'color'=>'#d6eaff' ),
                array( 'name'=>'Bölge Planlandı',        'sort'=>20,  'color'=>'#c1e0ff' ),
                array( 'name'=>'Salon/Tahsis Hazır',     'sort'=>30,  'color'=>'#98cbff' ),
                array( 'name'=>'Etkinlik/Seans Hazır',   'sort'=>40,  'color'=>'#fffeb2' ),
                array( 'name'=>'Satış Hazır',            'sort'=>50,  'color'=>'#fffd7f' ),
                array( 'name'=>'Tanıtım/Saha Aktif',     'sort'=>60,  'color'=>'#ffeab2' ),
                array( 'name'=>'Operasyon Hazır',        'sort'=>70,  'color'=>'#ffdc7f' ),
                array( 'name'=>'Gösteri Tamamlandı',     'sort'=>80,  'color'=>'#deff81' ),
                array( 'name'=>'Finans/Kapanış',         'sort'=>90,  'color'=>'#87f2c0' ),
                array( 'name'=>'Arşiv',                  'sort'=>100, 'color'=>'#e6e8ea' ),
            ),
        );
    }

    public static function find_program_pipeline( $force = false ) {
        $blueprint = self::program_pipeline_blueprint();
        $catalog = self::pipeline_catalog( $force );
        if ( is_wp_error( $catalog ) ) {
            return $catalog;
        }

        $target = self::normalize_pipeline_label( $blueprint['name'] );
        foreach ( (array) $catalog as $pipeline ) {
            if ( self::normalize_pipeline_label( $pipeline['name'] ?? '' ) === $target ) {
                return $pipeline;
            }
        }

        return null;
    }

    public static function install_program_pipeline() {
        if ( ! self::configured() ) {
            return new WP_Error( 'mmc_kommo_not_configured', 'Kommo API yapılandırılmadı.' );
        }

        $diag = self::connection_diagnostics( true );
        if ( empty( $diag['connected'] ) ) {
            return new WP_Error( 'mmc_kommo_not_connected', $diag['error'] ?: 'Kommo API bağlantısı doğrulanamadı.' );
        }

        $readiness = self::admin_write_readiness( true );
        if ( true === $readiness['verified'] && false === $readiness['is_admin'] ) {
            return new WP_Error(
                'mmc_kommo_admin_required',
                'Kommo pipeline kurulumu yapılamaz: tokenın bağlı olduğu Kommo kullanıcısı yönetici değil. Pipeline/stage oluşturma yalnız yönetici yetkisiyle kullanılabilir.'
            );
        }

        $blueprint = self::program_pipeline_blueprint();
        $pipeline = self::find_program_pipeline( true );
        if ( is_wp_error( $pipeline ) ) {
            return $pipeline;
        }

        $created_pipeline = false;

        if ( ! $pipeline ) {
            $embedded_statuses = array();
            foreach ( (array) $blueprint['stages'] as $stage ) {
                $embedded_statuses[] = array(
                    'name'  => $stage['name'],
                    'sort'  => (int) $stage['sort'],
                    'color' => $stage['color'],
                );
            }

            $payload = array(
                array(
                    'name'           => $blueprint['name'],
                    'sort'           => 900,
                    'is_main'        => false,
                    'is_unsorted_on' => false,
                    '_embedded'      => array(
                        'statuses' => $embedded_statuses,
                    ),
                ),
            );

            $response = self::api_request(
                self::crm_base() . '/leads/pipelines',
                'POST',
                $payload
            );
            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $created_pipeline = true;
            delete_transient( 'mmc_kommo_pipeline_catalog_' . md5( self::subdomain() . '|' . self::token_fingerprint() ) );
            $pipeline = self::find_program_pipeline( true );
            if ( is_wp_error( $pipeline ) ) {
                return $pipeline;
            }
            if ( ! $pipeline || empty( $pipeline['id'] ) ) {
                return new WP_Error(
                    'mmc_kommo_pipeline_create_unverified',
                    'Kommo pipeline oluşturma çağrısı tamamlandı ancak yeni MMC pipeline yeniden okunarak doğrulanamadı.'
                );
            }
        }

        $pipeline_id = absint( $pipeline['id'] );
        $existing = array();
        foreach ( (array) ( $pipeline['statuses'] ?? array() ) as $status ) {
            $existing[ self::normalize_pipeline_label( $status['name'] ?? '' ) ] = $status;
        }

        $missing_payload = array();
        foreach ( $blueprint['stages'] as $stage ) {
            $key = self::normalize_pipeline_label( $stage['name'] );
            if ( isset( $existing[ $key ] ) ) {
                continue;
            }
            $missing_payload[] = array(
                'name'  => $stage['name'],
                'sort'  => (int) $stage['sort'],
                'color' => $stage['color'],
            );
        }

        $added_stage_count = $created_pipeline ? count( (array) $blueprint['stages'] ) : 0;
        if ( $missing_payload ) {
            $response = self::api_request(
                self::crm_base() . '/leads/pipelines/' . $pipeline_id . '/statuses',
                'POST',
                $missing_payload
            );
            if ( is_wp_error( $response ) ) {
                return new WP_Error(
                    'mmc_kommo_stage_create_failed',
                    'MMC Program Pipeline oluşturuldu/bulundu ancak aşamalar tamamlanamadı: ' . $response->get_error_message(),
                    array( 'pipeline_id'=>$pipeline_id )
                );
            }
            $added_stage_count += count( $missing_payload );
        }

        $verified = self::pipeline_catalog( true );
        if ( is_wp_error( $verified ) ) {
            return $verified;
        }

        $final = null;
        $target = self::normalize_pipeline_label( $blueprint['name'] );
        foreach ( $verified as $row ) {
            if ( self::normalize_pipeline_label( $row['name'] ?? '' ) === $target ) {
                $final = $row;
                break;
            }
        }

        if ( ! $final ) {
            return new WP_Error( 'mmc_kommo_pipeline_verify_failed', 'MMC Program Pipeline son doğrulamada bulunamadı.' );
        }

        $final_statuses = array();
        foreach ( (array) $final['statuses'] as $status ) {
            $final_statuses[ self::normalize_pipeline_label( $status['name'] ?? '' ) ] = $status;
        }

        $missing_names = array();
        foreach ( $blueprint['stages'] as $stage ) {
            if ( ! isset( $final_statuses[ self::normalize_pipeline_label( $stage['name'] ) ] ) ) {
                $missing_names[] = $stage['name'];
            }
        }

        if ( $missing_names ) {
            return new WP_Error(
                'mmc_kommo_pipeline_incomplete',
                'Pipeline mevcut ancak bazı MMC aşamaları doğrulanamadı: ' . implode( ', ', $missing_names ),
                array( 'pipeline_id'=>(int)$final['id'] )
            );
        }

        $first = $final_statuses[ self::normalize_pipeline_label( 'Hazırlık' ) ] ?? null;
        $default_status_id = $first ? absint( $first['id'] ) : 0;

        update_option( 'mmc_kommo_pipeline_id', (int)$final['id'], false );
        update_option( 'mmc_kommo_status_id', $default_status_id, false );

        return array(
            'pipeline_id'         => (int)$final['id'],
            'pipeline_name'       => (string)$final['name'],
            'default_status_id'   => $default_status_id,
            'default_status_name' => $first ? (string)$first['name'] : '',
            'created_pipeline'    => $created_pipeline,
            'added_stage_count'   => $added_stage_count,
            'stage_count'         => count( $blueprint['stages'] ),
        );
    }

    private static function normalize_pipeline_label( $text ) {
        $text = strtolower( remove_accents( wp_strip_all_tags( (string) $text ) ) );
        $text = str_replace( array( '—', '–', '/', '\\' ), ' ', $text );
        $text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
        return trim( preg_replace( '/\\s+/', ' ', $text ) );
    }

    public static function pipeline_catalog( $force = false ) {
        $cfg = self::configuration_status();

        if ( ! $cfg['configured'] ) {
            return new WP_Error( 'mmc_kommo_not_configured', 'Kommo API yapılandırılmadı.' );
        }

        $cache_key = 'mmc_kommo_pipeline_catalog_' . md5( $cfg['subdomain'] . '|' . self::token_fingerprint() );
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $r = self::api_request( self::crm_base() . '/leads/pipelines?limit=250', 'GET' );
        if ( is_wp_error( $r ) ) {
            return $r;
        }

        $rows = isset( $r['_embedded']['pipelines'] ) && is_array( $r['_embedded']['pipelines'] )
            ? $r['_embedded']['pipelines']
            : array();

        $catalog = array();

        foreach ( $rows as $row ) {
            $pipeline_id = absint( $row['id'] ?? 0 );
            if ( ! $pipeline_id ) {
                continue;
            }

            $statuses = array();
            $raw_statuses = isset( $row['_embedded']['statuses'] ) && is_array( $row['_embedded']['statuses'] )
                ? $row['_embedded']['statuses']
                : array();

            foreach ( $raw_statuses as $status ) {
                $status_id = absint( $status['id'] ?? 0 );
                if ( ! $status_id ) {
                    continue;
                }

                $statuses[] = array(
                    'id'          => $status_id,
                    'name'        => sanitize_text_field( $status['name'] ?? '' ),
                    'sort'        => isset( $status['sort'] ) ? (int) $status['sort'] : 0,
                    'is_editable' => isset( $status['is_editable'] ) ? (bool) $status['is_editable'] : null,
                );
            }

            usort( $statuses, function( $a, $b ) {
                return (int) $a['sort'] <=> (int) $b['sort'];
            } );

            $catalog[] = array(
                'id'          => $pipeline_id,
                'name'        => sanitize_text_field( $row['name'] ?? '' ),
                'sort'        => isset( $row['sort'] ) ? (int) $row['sort'] : 0,
                'is_main'     => ! empty( $row['is_main'] ),
                'is_unsorted' => ! empty( $row['is_unsorted_on'] ),
                'statuses'    => $statuses,
            );
        }

        usort( $catalog, function( $a, $b ) {
            return (int) $a['sort'] <=> (int) $b['sort'];
        } );

        set_transient( $cache_key, $catalog, 10 * MINUTE_IN_SECONDS );
        return $catalog;
    }

    public static function pipeline_catalog_index( $force = false ) {
        $catalog = self::pipeline_catalog( $force );
        if ( is_wp_error( $catalog ) ) {
            return $catalog;
        }

        $index = array();
        foreach ( $catalog as $pipeline ) {
            $index[ (int) $pipeline['id'] ] = $pipeline;
        }
        return $index;
    }

    public static function discover_pipeline_selection( $pipeline_id = 0, $status_id = 0, $force = false ) {
        $pipeline_id = absint( $pipeline_id );
        $status_id   = absint( $status_id );

        $index = self::pipeline_catalog_index( $force );
        if ( is_wp_error( $index ) ) {
            return $index;
        }

        if ( ! $pipeline_id ) {
            return array(
                'pipeline_valid' => false,
                'status_valid'   => false,
                'pipeline'       => null,
                'status'         => null,
            );
        }

        if ( empty( $index[ $pipeline_id ] ) ) {
            return array(
                'pipeline_valid' => false,
                'status_valid'   => false,
                'pipeline'       => null,
                'status'         => null,
            );
        }

        $pipeline = $index[ $pipeline_id ];
        $matched_status = null;

        if ( $status_id ) {
            foreach ( (array) $pipeline['statuses'] as $status ) {
                if ( (int) $status['id'] === $status_id ) {
                    $matched_status = $status;
                    break;
                }
            }
        }

        return array(
            'pipeline_valid' => true,
            'status_valid'   => $status_id ? (bool) $matched_status : true,
            'pipeline'       => $pipeline,
            'status'         => $matched_status,
        );
    }

    public static function configuration_status() {
        $subdomain = self::subdomain();
        $token_source = self::token_source();
        $legacy_pipeline = defined( 'MS_KOMMO_PIPELINE_ID' ) ? absint( MS_KOMMO_PIPELINE_ID ) : 0;
        $pipeline = absint( get_option( 'mmc_kommo_pipeline_id', 0 ) );
        $status = absint( get_option( 'mmc_kommo_status_id', 0 ) );

        return array(
            'configured' => (bool) ( $subdomain && self::token() ),
            'subdomain' => $subdomain,
            'subdomain_source' => self::subdomain_source(),
            'token_source' => $token_source,
            'uses_legacy_token' => 'MS_KOMMO_TOKEN' === $token_source,
            'pipeline_id' => $pipeline,
            'status_id' => $status,
            'legacy_pipeline_id' => $legacy_pipeline,
            'legacy_bilet_field_id' => defined( 'MS_KOMMO_BILET_FIELD_ID' ) ? absint( MS_KOMMO_BILET_FIELD_ID ) : 0,
        );
    }

    public static function token_source() {
        if ( defined( 'MMC_KOMMO_TOKEN' ) && trim( (string) MMC_KOMMO_TOKEN ) !== '' ) {
            return 'MMC_KOMMO_TOKEN';
        }
        if ( defined( 'MS_KOMMO_TOKEN' ) && trim( (string) MS_KOMMO_TOKEN ) !== '' ) {
            return 'MS_KOMMO_TOKEN';
        }
        return '';
    }

    private static function token_fingerprint() {
        $token = self::token();
        return $token ? substr( hash( 'sha256', $token ), 0, 16 ) : 'none';
    }

    private static function api_request( $url, $method='GET', $body=null ) {
        $token=self::token();
        if(!$token)return new WP_Error('mmc_kommo_not_configured','Kommo token bulunamadı.');
        $args=array(
            'method'=>$method,'timeout'=>25,
            'headers'=>array('Authorization'=>'Bearer '.$token,'Accept'=>'application/json','Content-Type'=>'application/json'),
        );
        if(null!==$body)$args['body']=wp_json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $r=wp_remote_request($url,$args);
        if(is_wp_error($r))return $r;
        $code=(int)wp_remote_retrieve_response_code($r); $raw=wp_remote_retrieve_body($r);
        $data=$raw!==''?json_decode($raw,true):array();
        if($code<200||$code>=300){
            $msg=self::api_error_message($data,$code);
            $path=(string)wp_parse_url($url,PHP_URL_PATH);
            return new WP_Error(
                'mmc_kommo_api',
                'Kommo API '.$code.' ['.$path.']: '.$msg,
                array('status'=>$code,'endpoint'=>$path)
            );
        }
        return is_array($data)?$data:array();
    }

    private static function api_error_message( $data, $code ) {
        if ( ! is_array( $data ) ) {
            return 'HTTP ' . (int) $code . ' hata yanıtı.';
        }

        $parts = array();
        foreach ( array( 'title', 'detail', 'error', 'message' ) as $key ) {
            if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
                $parts[] = sanitize_text_field( (string) $data[ $key ] );
            }
        }

        foreach ( array( 'validation-errors', 'validation_errors', 'errors' ) as $key ) {
            if ( empty( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
                continue;
            }
            $flat = self::flatten_api_error_values( $data[ $key ] );
            $parts = array_merge( $parts, array_slice( $flat, 0, 8 ) );
        }

        $parts = array_values( array_unique( array_filter( $parts ) ) );
        if ( ! $parts ) {
            return 'Kommo API hata yanıtı.';
        }
        return substr( implode( ' | ', $parts ), 0, 800 );
    }

    private static function flatten_api_error_values( $value ) {
        $out = array();
        $walk = function( $node ) use ( &$walk, &$out ) {
            if ( count( $out ) >= 12 ) {
                return;
            }
            if ( is_scalar( $node ) ) {
                $text = sanitize_text_field( (string) $node );
                if ( '' !== $text ) {
                    $out[] = $text;
                }
                return;
            }
            if ( is_array( $node ) ) {
                foreach ( $node as $child ) {
                    $walk( $child );
                    if ( count( $out ) >= 12 ) {
                        break;
                    }
                }
            }
        };
        $walk( $value );
        return $out;
    }

    public static function configured() { return self::subdomain() && self::token(); }

    public static function subdomain() {
        if ( defined( 'MMC_KOMMO_SUBDOMAIN' ) && MMC_KOMMO_SUBDOMAIN ) {
            return sanitize_title( (string) MMC_KOMMO_SUBDOMAIN );
        }

        $option = sanitize_title( (string) get_option( 'mmc_kommo_subdomain', '' ) );
        if ( $option ) {
            return $option;
        }

        if ( defined( 'MS_KOMMO_BASE_URL' ) && MS_KOMMO_BASE_URL ) {
            $host = wp_parse_url( (string) MS_KOMMO_BASE_URL, PHP_URL_HOST );
            if ( $host && preg_match( '/^([a-z0-9-]+)\.kommo\.com$/i', $host, $m ) ) {
                return sanitize_title( $m[1] );
            }
        }

        return '';
    }

    public static function subdomain_source() {
        if ( defined( 'MMC_KOMMO_SUBDOMAIN' ) && MMC_KOMMO_SUBDOMAIN ) {
            return 'MMC_KOMMO_SUBDOMAIN';
        }
        if ( get_option( 'mmc_kommo_subdomain', '' ) ) {
            return 'WordPress option';
        }
        if ( defined( 'MS_KOMMO_BASE_URL' ) && MS_KOMMO_BASE_URL ) {
            return 'MS_KOMMO_BASE_URL';
        }
        return '';
    }

    private static function token() {
        if ( defined( 'MMC_KOMMO_TOKEN' ) && trim( (string) MMC_KOMMO_TOKEN ) !== '' ) {
            return trim( (string) MMC_KOMMO_TOKEN );
        }
        if ( defined( 'MS_KOMMO_TOKEN' ) && trim( (string) MS_KOMMO_TOKEN ) !== '' ) {
            return trim( (string) MS_KOMMO_TOKEN );
        }
        return '';
    }

    private static function crm_base(){ return 'https://'.self::subdomain().'.kommo.com/api/v4'; }

    private static function profile_error($id,$field,$message){
        global $wpdb; $allowed=array('ai_source_status','crm_status'); if(!in_array($field,$allowed,true))return;
        $wpdb->update($wpdb->prefix.'mmc_kommo_profiles',array($field=>'error','last_error'=>sanitize_textarea_field($message),'updated_at'=>current_time('mysql')),array('id'=>absint($id)));
    }

    public static function templates( $program_id ) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_kommo_templates WHERE program_id=%d ORDER BY id ASC",absint($program_id)));
    }
    public static function update_template( $id, $status, $external_name='', $notes='' ) {
        global $wpdb;
        $allowed=array('expected','verified','inactive','error'); if(!in_array($status,$allowed,true))return new WP_Error('mmc_kommo_template_status','Geçersiz şablon durumu.');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_kommo_templates WHERE id=%d",absint($id))); if(!$row)return new WP_Error('mmc_kommo_template_missing','Şablon kaydı bulunamadı.');
        $wpdb->update($wpdb->prefix.'mmc_kommo_templates',array('status'=>$status,'external_name'=>sanitize_text_field($external_name),'notes'=>sanitize_textarea_field($notes),'last_checked_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')),array('id'=>(int)$row->id));
        MMC_Program_Service::add_log($row->program_id,'kommo_template_updated','kommo_template',$row->id,null,array('status'=>$status),'WhatsApp şablon durumu güncellendi.');
        return true;
    }

    private static function seed_templates( $program_id, $event_id ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_kommo_templates'; $now=current_time('mysql');
        foreach(array('madagaskar_bilet_linki_v2','madagaskar_bilet_takip_v1','madagaskar_etkinlik_hatirlatma_v1','madagaskar_memnuniyet_v3') as $key){
            $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE program_id=%d AND template_key=%s",$program_id,$key)); if($exists)continue;
            $wpdb->insert($table,array('program_id'=>$program_id,'event_id'=>$event_id?:null,'template_key'=>$key,'external_name'=>$key,'status'=>'expected','created_at'=>$now,'updated_at'=>$now));
        }
    }
}
