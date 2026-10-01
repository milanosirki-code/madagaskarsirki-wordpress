<?php

if ( ! function_exists( 'mdg_ai_school_can_run' ) ) {
    function mdg_ai_school_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mdg_ai_school_forbidden', 'Bu işlem için yönetici yetkisi gerekir.' );
        }
        if ( ! function_exists( 'mad_okul_table' ) || ! class_exists( 'Mad_Okul_Operations' ) ) {
            return new WP_Error( 'mdg_ai_school_missing', 'Okul Tanıtım servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_school_to_array' ) ) {
    function mdg_ai_school_to_array( $row ) {
        if ( ! $row ) return array();
        $q = trim( (string) $row->kurum_adi . ', ' . (string) $row->adres . ', ' . (string) $row->ilce . ', ' . (string) $row->il );
        return array(
            'id'                => (int) $row->id,
            'province'          => (string) $row->il,
            'district'          => (string) $row->ilce,
            'school_name'       => (string) $row->kurum_adi,
            'address'           => (string) $row->adres,
            'status'            => (string) $row->durum,
            'personnel'         => isset( $row->personel ) ? (string) $row->personel : '',
            'event'             => isset( $row->etkinlik ) ? (string) $row->etkinlik : '',
            'last_visit'        => isset( $row->son_ziyaret ) ? (string) ( $row->son_ziyaret ?: '' ) : '',
            'notes'             => isset( $row->notlar ) ? (string) ( $row->notlar ?: '' ) : '',
            'latitude'          => isset( $row->latitude ) && null !== $row->latitude ? (float) $row->latitude : null,
            'longitude'         => isset( $row->longitude ) && null !== $row->longitude ? (float) $row->longitude : null,
            'program_id'        => isset( $row->program_id ) ? (int) $row->program_id : 0,
            'mmc_program_id'    => isset( $row->mmc_program_id ) ? (int) $row->mmc_program_id : 0,
            'assigned_user_id'  => isset( $row->assigned_user_id ) ? (int) $row->assigned_user_id : 0,
            'route_group'       => isset( $row->route_group ) ? (string) $row->route_group : '',
            'route_order'       => isset( $row->route_order ) ? (int) $row->route_order : 0,
            'route_distance_m'  => isset( $row->route_distance_m ) ? (int) $row->route_distance_m : 0,
            'route_duration_s'  => isset( $row->route_duration_s ) ? (int) $row->route_duration_s : 0,
            'maps_url'          => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $q ),
            'created_at'        => isset( $row->created_at ) ? (string) $row->created_at : '',
            'updated_at'        => isset( $row->updated_at ) ? (string) $row->updated_at : '',
        );
    }
}

if ( ! function_exists( 'mdg_ai_school_program_to_array' ) ) {
    function mdg_ai_school_program_to_array( $row ) {
        if ( ! $row ) return array();
        return array(
            'id'             => (int) $row->id,
            'mmc_program_id' => isset( $row->mmc_program_id ) ? (int) $row->mmc_program_id : 0,
            'program_name'   => (string) $row->program_adi,
            'province'       => (string) $row->il,
            'district'       => (string) $row->ilce,
            'venue_name'     => (string) $row->salon_adi,
            'venue_address'  => (string) $row->salon_adresi,
            'event_date'     => isset( $row->etkinlik_tarihi ) ? (string) ( $row->etkinlik_tarihi ?: '' ) : '',
            'latitude'       => isset( $row->latitude ) && null !== $row->latitude ? (float) $row->latitude : null,
            'longitude'      => isset( $row->longitude ) && null !== $row->longitude ? (float) $row->longitude : null,
            'status'         => (string) $row->durum,
            'created_at'     => isset( $row->created_at ) ? (string) $row->created_at : '',
            'updated_at'     => isset( $row->updated_at ) ? (string) $row->updated_at : '',
        );
    }
}

if ( ! function_exists( 'mdg_ai_schools_list' ) ) {
    function mdg_ai_schools_list( $input = array() ) {
        global $wpdb;
        $table = mad_okul_table();
        $where = array( '1=1' );
        $params = array();

        $province = sanitize_text_field( $input['province'] ?? '' );
        $district = sanitize_text_field( $input['district'] ?? '' );
        $status   = sanitize_text_field( $input['status'] ?? '' );
        $search   = sanitize_text_field( $input['search'] ?? '' );
        $program_id = absint( $input['program_id'] ?? 0 );
        $mmc_program_id = absint( $input['mmc_program_id'] ?? 0 );
        $assigned_user_id = absint( $input['assigned_user_id'] ?? 0 );
        $limit = isset( $input['limit'] ) ? max( 1, min( 500, absint( $input['limit'] ) ) ) : 100;

        if ( $province ) { $where[] = 'il=%s'; $params[] = mad_okul_place_title( $province ); }
        if ( $district ) { $where[] = 'ilce=%s'; $params[] = mad_okul_place_title( $district ); }
        if ( $status ) { $where[] = 'durum=%s'; $params[] = $status; }
        if ( $program_id ) { $where[] = 'program_id=%d'; $params[] = $program_id; }
        if ( $mmc_program_id ) { $where[] = 'mmc_program_id=%d'; $params[] = $mmc_program_id; }
        if ( $assigned_user_id ) { $where[] = 'assigned_user_id=%d'; $params[] = $assigned_user_id; }
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = '(kurum_adi LIKE %s OR adres LIKE %s)';
            $params[] = $like; $params[] = $like;
        }

        $sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) .
               " ORDER BY CASE WHEN route_order>0 THEN 0 ELSE 1 END, route_order, il, ilce, kurum_adi LIMIT %d";
        $params[] = $limit;
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        $items = array_map( 'mdg_ai_school_to_array', (array) $rows );
        return array( 'count'=>count($items), 'items'=>$items );
    }
}

if ( ! function_exists( 'mdg_ai_school_get' ) ) {
    function mdg_ai_school_get( $input ) {
        global $wpdb;
        $id = absint( $input['school_id'] ?? 0 );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . mad_okul_table() . ' WHERE id=%d LIMIT 1', $id ) );
        if ( ! $row ) return new WP_Error( 'mdg_ai_school_not_found', 'Okul kaydı bulunamadı.' );
        return array( 'school'=>mdg_ai_school_to_array($row) );
    }
}

if ( ! function_exists( 'mdg_ai_school_summary' ) ) {
    function mdg_ai_school_summary( $input = array() ) {
        global $wpdb;
        $table = mad_okul_table();
        $where = array( '1=1' );
        $params = array();
        $province = sanitize_text_field( $input['province'] ?? '' );
        $district = sanitize_text_field( $input['district'] ?? '' );
        if ( $province ) { $where[]='il=%s'; $params[]=mad_okul_place_title($province); }
        if ( $district ) { $where[]='ilce=%s'; $params[]=mad_okul_place_title($district); }
        $where_sql = implode(' AND ',$where);

        $sql = "SELECT durum,COUNT(*) adet FROM $table WHERE $where_sql GROUP BY durum ORDER BY adet DESC";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql,$params)) : $wpdb->get_results($sql);
        $statuses=array(); $total=0;
        foreach((array)$rows as $row){ $statuses[(string)$row->durum]=(int)$row->adet; $total+=(int)$row->adet; }

        $district_sql="SELECT COUNT(DISTINCT CONCAT(il,'|',ilce)) FROM $table WHERE $where_sql";
        $districts=$params ? (int)$wpdb->get_var($wpdb->prepare($district_sql,$params)) : (int)$wpdb->get_var($district_sql);

        return array('total'=>$total,'district_count'=>$districts,'statuses'=>$statuses);
    }
}

if ( ! function_exists( 'mdg_ai_school_programs_list' ) ) {
    function mdg_ai_school_programs_list( $input = array() ) {
        global $wpdb;
        $table = Mad_Okul_Operations::programs_table();
        $province = sanitize_text_field( $input['province'] ?? '' );
        $district = sanitize_text_field( $input['district'] ?? '' );
        $status = sanitize_text_field( $input['status'] ?? '' );
        $where=array('1=1'); $params=array();
        if($province){$where[]='il=%s';$params[]=mad_okul_place_title($province);}
        if($district){$where[]='ilce=%s';$params[]=mad_okul_place_title($district);}
        if($status){$where[]='durum=%s';$params[]=$status;}
        $sql="SELECT * FROM $table WHERE ".implode(' AND ',$where)." ORDER BY etkinlik_tarihi DESC,id DESC";
        $rows=$params?$wpdb->get_results($wpdb->prepare($sql,$params)):$wpdb->get_results($sql);
        return array('count'=>count($rows),'items'=>array_map('mdg_ai_school_program_to_array',(array)$rows));
    }
}

if ( ! function_exists( 'mdg_ai_school_bridge_status' ) ) {
    function mdg_ai_school_bridge_status( $input ) {
        $mmc_program_id = absint( $input['mmc_program_id'] ?? 0 );
        $result = Mad_Okul_Operations::bridge_status( $mmc_program_id );
        if ( is_wp_error( $result ) ) return $result;
        return array( 'mmc_program_id'=>$mmc_program_id, 'bridge'=>$result );
    }
}

if ( ! function_exists( 'mdg_ai_school_ensure_bridge' ) ) {
    function mdg_ai_school_ensure_bridge( $input ) {
        $mmc_program_id = absint( $input['mmc_program_id'] ?? 0 );
        $row = Mad_Okul_Operations::ensure_mmc_bridge( $mmc_program_id );
        if ( is_wp_error( $row ) ) return $row;
        return array( 'ensured'=>true, 'program'=>mdg_ai_school_program_to_array($row) );
    }
}

if ( ! function_exists( 'mdg_ai_school_update' ) ) {
    function mdg_ai_school_update( $input ) {
        global $wpdb;
        $id=absint($input['school_id']??0);
        $table=mad_okul_table();
        $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d LIMIT 1",$id));
        if(!$old) return new WP_Error('mdg_ai_school_not_found','Okul kaydı bulunamadı.');

        $allowed_statuses=array('Bekliyor','Adres Eksik','Atandı','Ziyaret Edildi','Afiş Bırakıldı','Görüşüldü','Tekrar Gidilecek','Olumsuz');
        $data=array('updated_at'=>current_time('mysql'));

        if(array_key_exists('status',$input)){
            $status=sanitize_text_field($input['status']);
            if(!in_array($status,$allowed_statuses,true)) return new WP_Error('mdg_ai_school_status_invalid','Geçersiz okul durumu.');
            $data['durum']=$status;
        }
        if(array_key_exists('personnel',$input)) $data['personel']=sanitize_text_field($input['personnel']);
        if(array_key_exists('event',$input)) $data['etkinlik']=sanitize_text_field($input['event']);
        if(array_key_exists('last_visit',$input)){
            $date=sanitize_text_field($input['last_visit']);
            if($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) return new WP_Error('mdg_ai_school_date_invalid','last_visit YYYY-AA-GG olmalıdır.');
            $data['son_ziyaret']=$date?:null;
        }
        if(array_key_exists('notes',$input)) $data['notlar']=sanitize_textarea_field($input['notes']);

        $ok=$wpdb->update($table,$data,array('id'=>$id));
        if(false===$ok) return new WP_Error('mdg_ai_school_update_failed','Okul kaydı güncellenemedi.');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d LIMIT 1",$id));
        return array('updated'=>true,'school'=>mdg_ai_school_to_array($row));
    }
}

if ( ! function_exists( 'mdg_ai_school_assign' ) ) {
    function mdg_ai_school_assign( $input ) {
        global $wpdb;
        $ids=array_values(array_unique(array_filter(array_map('absint',(array)($input['school_ids']??array())))));
        $uid=absint($input['assigned_user_id']??0);
        $mmc_program_id=absint($input['mmc_program_id']??0);
        $program_id=absint($input['program_id']??0);
        $group=sanitize_text_field($input['route_group']??'A');

        if(empty($ids)) return new WP_Error('mdg_ai_school_ids_required','En az bir okul seçilmelidir.');
        if(count($ids)>500) return new WP_Error('mdg_ai_school_assign_limit','Tek işlemde en fazla 500 okul atanabilir.');
        if(!$uid || !get_user_by('id',$uid)) return new WP_Error('mdg_ai_school_user_invalid','Tanıtım personeli bulunamadı.');

        if($mmc_program_id && !$program_id){
            $bridge=Mad_Okul_Operations::ensure_mmc_bridge($mmc_program_id);
            if(is_wp_error($bridge)) return $bridge;
            $program_id=(int)$bridge->id;
        }
        if(!$program_id) return new WP_Error('mdg_ai_school_program_required','program_id veya mmc_program_id zorunludur.');

        $table=mad_okul_table();
        $updated=array(); $failed=array();
        foreach($ids as $index=>$id){
            $exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id=%d",$id));
            if(!$exists){$failed[]=$id;continue;}
            $ok=$wpdb->update($table,array(
                'program_id'=>$program_id,
                'mmc_program_id'=>$mmc_program_id?:null,
                'assigned_user_id'=>$uid,
                'route_group'=>$group,
                'route_order'=>$index+1,
                'durum'=>'Atandı',
                'updated_at'=>current_time('mysql'),
            ),array('id'=>$id));
            if(false===$ok)$failed[]=$id;else$updated[]=$id;
        }
        return array(
            'program_id'=>$program_id,
            'mmc_program_id'=>$mmc_program_id,
            'assigned_user_id'=>$uid,
            'route_group'=>$group,
            'updated_count'=>count($updated),
            'updated_school_ids'=>$updated,
            'failed_school_ids'=>$failed,
        );
    }
}

if ( ! function_exists( 'mdg_ai_school_task_result' ) ) {
    function mdg_ai_school_task_result( $input ) {
        global $wpdb;
        $id=absint($input['school_id']??0);
        $status=sanitize_text_field($input['status']??'Ziyaret Edildi');
        $notes=sanitize_textarea_field($input['notes']??'');
        $allowed=array('Ziyaret Edildi','Afiş Bırakıldı','Görüşüldü','Tekrar Gidilecek','Olumsuz');
        if(!in_array($status,$allowed,true)) return new WP_Error('mdg_ai_school_task_status_invalid','Geçersiz ziyaret sonucu.');

        $table=mad_okul_table();
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d LIMIT 1",$id));
        if(!$row) return new WP_Error('mdg_ai_school_not_found','Okul kaydı bulunamadı.');

        $ok=$wpdb->update($table,array(
            'durum'=>$status,
            'notlar'=>$notes,
            'son_ziyaret'=>current_time('Y-m-d'),
            'updated_at'=>current_time('mysql'),
        ),array('id'=>$id));
        if(false===$ok) return new WP_Error('mdg_ai_school_task_update_failed','Ziyaret sonucu kaydedilemedi.');

        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d LIMIT 1",$id));
        return array('updated'=>true,'school'=>mdg_ai_school_to_array($row));
    }
}

if ( ! function_exists( 'mdg_ai_school_route_plan' ) ) {
    function mdg_ai_school_route_plan( $input ) {
        global $wpdb;
        $program_id=absint($input['program_id']??0);
        $mmc_program_id=absint($input['mmc_program_id']??0);
        $programs=Mad_Okul_Operations::programs_table();

        if(!$program_id && $mmc_program_id){
            $program_id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $programs WHERE mmc_program_id=%d LIMIT 1",$mmc_program_id));
            if(!$program_id) return new WP_Error('mdg_ai_school_bridge_missing','Bu MMC programı Okul Tanıtım programına henüz bağlı değil.');
        }
        if(!$program_id) return new WP_Error('mdg_ai_school_program_required','program_id veya mmc_program_id zorunludur.');

        $program=$wpdb->get_row($wpdb->prepare("SELECT * FROM $programs WHERE id=%d LIMIT 1",$program_id));
        if(!$program) return new WP_Error('mdg_ai_school_program_missing','Okul Tanıtım programı bulunamadı.');

        $rows=$wpdb->get_results($wpdb->prepare(
            "SELECT s.*,u.display_name FROM ".mad_okul_table()." s LEFT JOIN {$wpdb->users} u ON u.ID=s.assigned_user_id WHERE s.program_id=%d ORDER BY s.assigned_user_id,s.route_group,s.route_order,s.kurum_adi",
            $program_id
        ));

        $groups=array();
        foreach((array)$rows as $row){
            $key=(int)$row->assigned_user_id.'|'.($row->route_group?:'A');
            if(!isset($groups[$key]))$groups[$key]=array();
            $groups[$key][]=$row;
        }

        $routes=array();
        foreach($groups as $key=>$items){
            list($uid,$group)=explode('|',$key,2);
            $origin=trim($program->salon_adi.', '.$program->salon_adresi,', ');
            foreach(array_chunk($items,8) as $part_index=>$chunk){
                $points=array();
                foreach($chunk as $school){
                    $points[]=trim($school->kurum_adi.', '.$school->adres.', '.$school->ilce.', '.$school->il,', ');
                }
                $destination=array_pop($points);
                $url='https://www.google.com/maps/dir/?api=1&origin='.rawurlencode($origin).'&destination='.rawurlencode($destination).'&travelmode=driving';
                if($points)$url.='&waypoints='.implode('%7C',array_map('rawurlencode',$points));
                $routes[]=array(
                    'assigned_user_id'=>(int)$uid,
                    'personnel_name'=>(string)($chunk[0]->display_name??''),
                    'route_group'=>$group,
                    'part'=>$part_index+1,
                    'school_count'=>count($chunk),
                    'maps_url'=>$url,
                    'school_ids'=>array_map(function($s){return (int)$s->id;},$chunk),
                );
                $last=end($chunk);
                $origin=trim($last->kurum_adi.', '.$last->adres.', '.$last->ilce.', '.$last->il,', ');
            }
        }

        $assigned_count = 0;
        foreach ( (array) $rows as $r ) {
            if ( ! empty( $r->assigned_user_id ) ) $assigned_count++;
        }
        return array(
            'program'=>mdg_ai_school_program_to_array($program),
            'program_school_count'=>count($rows),
            'assigned_school_count'=>$assigned_count,
            'unassigned_school_count'=>count($rows)-$assigned_count,
            'route_count'=>count($routes),
            'routes'=>$routes,
        );
    }
}

add_action( 'wp_abilities_api_categories_init', function() {
    if ( function_exists( 'wp_register_ability_category' ) ) {
        wp_register_ability_category(
            'madagaskar-okul-tanitim',
            array(
                'label'=>'Madagaskar Okul Tanıtım',
                'description'=>'Okul listeleri, MMC program köprüsü, görev dağıtımı, ziyaret sonuçları ve rota planları.',
            )
        );
    }
} );

add_action( 'wp_abilities_api_init', function() {
    if ( ! function_exists( 'wp_register_ability' ) ) return;

    $read=array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'public'=>true);
    $critical=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false),'public'=>true);
    $critical_idempotent=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>true),'public'=>true);

    wp_register_ability('madagaskar/schools-list',array(
        'label'=>'Okulları Listele',
        'description'=>'Okul Tanıtım ana listesini il, ilçe, durum, program, personel veya arama metnine göre listeler.',
        'category'=>'madagaskar-okul-tanitim',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'province'=>array('type'=>'string'),'district'=>array('type'=>'string'),'status'=>array('type'=>'string'),
            'search'=>array('type'=>'string'),'program_id'=>array('type'=>'integer','minimum'=>1),
            'mmc_program_id'=>array('type'=>'integer','minimum'=>1),'assigned_user_id'=>array('type'=>'integer','minimum'=>1),
            'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>500)
        )),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_schools_list','permission_callback'=>'mdg_ai_school_can_run','meta'=>$read
    ));

    wp_register_ability('madagaskar/school-get',array(
        'label'=>'Okul Detayını Getir','description'=>'Tek okulun adres, durum, görev, rota ve ziyaret bilgilerini getirir.',
        'category'=>'madagaskar-okul-tanitim',
        'input_schema'=>array('type'=>'object','properties'=>array('school_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('school_id')),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_school_get','permission_callback'=>'mdg_ai_school_can_run','meta'=>$read
    ));

    wp_register_ability('madagaskar/schools-summary',array(
        'label'=>'Okul Tanıtım Özetini Getir','description'=>'İl/ilçe bazında toplam okul ve durum dağılımını getirir.',
        'category'=>'madagaskar-okul-tanitim',
        'input_schema'=>array('type'=>'object','properties'=>array('province'=>array('type'=>'string'),'district'=>array('type'=>'string'))),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_school_summary','permission_callback'=>'mdg_ai_school_can_run','meta'=>$read
    ));

    wp_register_ability('madagaskar/school-programs-list',array(
        'label'=>'Okul Tanıtım Programlarını Listele','description'=>'Okul Tanıtım program/salon kayıtlarını ve MMC bağlantılarını listeler.',
        'category'=>'madagaskar-okul-tanitim',
        'input_schema'=>array('type'=>'object','properties'=>array('province'=>array('type'=>'string'),'district'=>array('type'=>'string'),'status'=>array('type'=>'string'))),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_school_programs_list','permission_callback'=>'mdg_ai_school_can_run','meta'=>$read
    ));

    wp_register_ability('madagaskar/school-bridge-status',array(
        'label'=>'MMC–Okul Tanıtım Köprüsünü Kontrol Et','description'=>'MMC programının Okul Tanıtım programına bağlı olup olmadığını ve belirsizlik durumunu kontrol eder.',
        'category'=>'madagaskar-okul-tanitim',
        'input_schema'=>array('type'=>'object','properties'=>array('mmc_program_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('mmc_program_id')),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_school_bridge_status','permission_callback'=>'mdg_ai_school_can_run','meta'=>$read
    ));

    wp_register_ability('madagaskar/school-ensure-bridge',array(
        'label'=>'MMC–Okul Tanıtım Köprüsünü Oluştur/Güncelle','description'=>'Mevcut eklenti servisiyle MMC programını Okul Tanıtım programına güvenli biçimde bağlar veya uyumluluk kaydını oluşturur. Kritik yazma işlemidir.',
        'category'=>'madagaskar-okul-tanitim',
        'input_schema'=>array('type'=>'object','properties'=>array('mmc_program_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('mmc_program_id')),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_school_ensure_bridge','permission_callback'=>'mdg_ai_school_can_run','meta'=>$critical_idempotent
    ));

    wp_register_ability('madagaskar/school-update',array(
        'label'=>'Okul Kaydını Güncelle','description'=>'Okulun operasyon durumu, personel metni, etkinlik, son ziyaret ve not alanlarını günceller. Kritik yazma işlemidir.',
        'category'=>'madagaskar-okul-tanitim',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'school_id'=>array('type'=>'integer','minimum'=>1),
            'status'=>array('type'=>'string','enum'=>array('Bekliyor','Adres Eksik','Atandı','Ziyaret Edildi','Afiş Bırakıldı','Görüşüldü','Tekrar Gidilecek','Olumsuz')),
            'personnel'=>array('type'=>'string'),'event'=>array('type'=>'string'),
            'last_visit'=>array('type'=>'string','pattern'=>'^\d{4}-\d{2}-\d{2}$'),'notes'=>array('type'=>'string')
        ),'required'=>array('school_id')),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_school_update','permission_callback'=>'mdg_ai_school_can_run','meta'=>$critical
    ));

    wp_register_ability('madagaskar/school-assign',array(
        'label'=>'Okulları Personele Ata','description'=>'Seçilen okulları MMC/Okul Tanıtım programına, tanıtım personeline ve rota grubuna atar; sıra numaralarını oluşturur. Kritik yazma işlemidir.',
        'category'=>'madagaskar-okul-tanitim',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'school_ids'=>array('type'=>'array','items'=>array('type'=>'integer','minimum'=>1),'minItems'=>1,'maxItems'=>500),
            'assigned_user_id'=>array('type'=>'integer','minimum'=>1),
            'program_id'=>array('type'=>'integer','minimum'=>1),
            'mmc_program_id'=>array('type'=>'integer','minimum'=>1),
            'route_group'=>array('type'=>'string')
        ),'required'=>array('school_ids','assigned_user_id')),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_school_assign','permission_callback'=>'mdg_ai_school_can_run','meta'=>$critical
    ));

    wp_register_ability('madagaskar/school-task-result',array(
        'label'=>'Okul Ziyaret Sonucunu Kaydet','description'=>'Atanmış okul için ziyaret sonucunu, notu ve son ziyaret tarihini kaydeder. Kritik yazma işlemidir.',
        'category'=>'madagaskar-okul-tanitim',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'school_id'=>array('type'=>'integer','minimum'=>1),
            'status'=>array('type'=>'string','enum'=>array('Ziyaret Edildi','Afiş Bırakıldı','Görüşüldü','Tekrar Gidilecek','Olumsuz')),
            'notes'=>array('type'=>'string')
        ),'required'=>array('school_id','status')),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_school_task_result','permission_callback'=>'mdg_ai_school_can_run','meta'=>$critical
    ));

    wp_register_ability('madagaskar/school-route-plan',array(
        'label'=>'Okul Rota Planını Getir','description'=>'Programdaki okulları personel ve rota grubuna göre 8 duraklık Google Maps bağlantılarına böler; atanmış ve atanmamış okul sayılarını ayrıca gösterir.',
        'category'=>'madagaskar-okul-tanitim',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'program_id'=>array('type'=>'integer','minimum'=>1),'mmc_program_id'=>array('type'=>'integer','minimum'=>1)
        )),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_school_route_plan','permission_callback'=>'mdg_ai_school_can_run','meta'=>$read
    ));
} );
