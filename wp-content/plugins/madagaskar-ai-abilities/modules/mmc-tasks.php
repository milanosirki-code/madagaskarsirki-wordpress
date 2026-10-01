<?php
/**
 * Madagaskar AI — MMC Görev Yönetimi
 *
 * mmc_tasks için güvenli CRUD katmanı.
 * Kalıcı silme yoktur; görev completed/cancelled durumuna alınır.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_tasks_can_run' ) ) {
    function mdg_ai_tasks_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mdg_ai_tasks_forbidden', 'Yönetici yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MMC_Program_Service' ) ) {
            return new WP_Error( 'mdg_ai_tasks_missing', 'MMC program servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_tasks_table' ) ) {
    function mdg_ai_tasks_table() {
        global $wpdb;
        return $wpdb->prefix . 'mmc_tasks';
    }
}

if ( ! function_exists( 'mdg_ai_task_to_array' ) ) {
    function mdg_ai_task_to_array( $row ) {
        if ( ! $row ) { return array(); }
        $meta = null;
        if ( ! empty( $row->metadata ) ) {
            $decoded = json_decode( (string) $row->metadata, true );
            $meta = JSON_ERROR_NONE === json_last_error() ? $decoded : (string) $row->metadata;
        }
        return array(
            'id'               => (int) $row->id,
            'program_id'       => (int) $row->program_id,
            'module'           => (string) $row->module,
            'title'            => (string) $row->title,
            'status'           => (string) $row->status,
            'priority'         => (string) $row->priority,
            'assigned_user_id' => (int) $row->assigned_user_id,
            'due_at'           => (string) ( $row->due_at ?: '' ),
            'completed_at'     => (string) ( $row->completed_at ?: '' ),
            'metadata'         => $meta,
            'created_by'       => (int) $row->created_by,
            'created_at'       => (string) $row->created_at,
            'updated_at'       => (string) $row->updated_at,
        );
    }
}

if ( ! function_exists( 'mdg_ai_tasks_list' ) ) {
    function mdg_ai_tasks_list( $input = array() ) {
        global $wpdb;
        $table = mdg_ai_tasks_table();
        $where = array( '1=1' );
        $params = array();

        $program_id = absint( $input['program_id'] ?? 0 );
        $module     = sanitize_key( $input['module'] ?? '' );
        $status     = sanitize_key( $input['status'] ?? '' );
        $priority   = sanitize_key( $input['priority'] ?? '' );
        $assigned   = absint( $input['assigned_user_id'] ?? 0 );
        $search     = sanitize_text_field( $input['search'] ?? '' );
        $overdue    = ! empty( $input['overdue_only'] );
        $limit      = max( 1, min( 500, absint( $input['limit'] ?? 100 ) ) );

        if ( $program_id ) { $where[]='program_id=%d'; $params[]=$program_id; }
        if ( $module ) { $where[]='module=%s'; $params[]=$module; }
        if ( $status ) { $where[]='status=%s'; $params[]=$status; }
        if ( $priority ) { $where[]='priority=%s'; $params[]=$priority; }
        if ( $assigned ) { $where[]='assigned_user_id=%d'; $params[]=$assigned; }
        if ( $search ) {
            $where[]='title LIKE %s';
            $params[]='%' . $wpdb->esc_like( $search ) . '%';
        }
        if ( $overdue ) {
            $where[]="status='open'";
            $where[]='due_at IS NOT NULL';
            $where[]='due_at < %s';
            $params[]=current_time('mysql');
        }

        $sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) .
               " ORDER BY CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END,
                        due_at IS NULL, due_at ASC, id DESC LIMIT %d";
        $params[]=$limit;
        $rows=$wpdb->get_results($wpdb->prepare($sql,$params));

        $items=array_map('mdg_ai_task_to_array',(array)$rows);
        return array('count'=>count($items),'items'=>$items);
    }
}

if ( ! function_exists( 'mdg_ai_task_get' ) ) {
    function mdg_ai_task_get( $input ) {
        global $wpdb;
        $id=absint($input['task_id']??0);
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.mdg_ai_tasks_table().' WHERE id=%d LIMIT 1',$id));
        if(!$row)return new WP_Error('mdg_ai_task_missing','Görev bulunamadı.');
        return array('task'=>mdg_ai_task_to_array($row));
    }
}

if ( ! function_exists( 'mdg_ai_tasks_summary' ) ) {
    function mdg_ai_tasks_summary( $input = array() ) {
        global $wpdb;
        $table=mdg_ai_tasks_table();
        $program_id=absint($input['program_id']??0);
        $where=$program_id?$wpdb->prepare(' WHERE program_id=%d',$program_id):'';
        $rows=$wpdb->get_results("SELECT status,COUNT(*) total FROM $table $where GROUP BY status",OBJECT_K);
        $open=(int)($rows['open']->total??0);
        $overdue_sql="SELECT COUNT(*) FROM $table WHERE status='open' AND due_at IS NOT NULL AND due_at < %s";
        $args=array(current_time('mysql'));
        if($program_id){$overdue_sql.=' AND program_id=%d';$args[]=$program_id;}
        $overdue=(int)$wpdb->get_var($wpdb->prepare($overdue_sql,$args));
        return array(
            'program_id'=>$program_id,
            'open'=>$open,
            'completed'=>(int)($rows['completed']->total??0),
            'cancelled'=>(int)($rows['cancelled']->total??0),
            'overdue'=>$overdue
        );
    }
}

if ( ! function_exists( 'mdg_ai_task_create' ) ) {
    function mdg_ai_task_create( $input ) {
        global $wpdb;
        $program_id=absint($input['program_id']??0);
        $program=MMC_Program_Service::get_program($program_id);
        if(!$program)return new WP_Error('mdg_ai_task_program_missing','Program bulunamadı.');

        $title=sanitize_text_field($input['title']??'');
        if(!$title)return new WP_Error('mdg_ai_task_title_required','Görev başlığı zorunludur.');

        $priority=sanitize_key($input['priority']??'normal');
        if(!in_array($priority,array('low','normal','high','critical'),true))$priority='normal';

        $assigned=absint($input['assigned_user_id']??0);
        if($assigned && !get_user_by('id',$assigned))return new WP_Error('mdg_ai_task_user_missing','Atanacak kullanıcı bulunamadı.');

        $due=sanitize_text_field($input['due_at']??'');
        if($due){
            $due=str_replace('T',' ',$due);
            $ts=strtotime($due);
            if(!$ts)return new WP_Error('mdg_ai_task_due_invalid','Geçersiz son tarih.');
            $due=wp_date('Y-m-d H:i:s',$ts);
        } else $due=null;

        $metadata=array_key_exists('metadata',$input)?wp_json_encode($input['metadata'],JSON_UNESCAPED_UNICODE):null;
        $now=current_time('mysql');
        $ok=$wpdb->insert(mdg_ai_tasks_table(),array(
            'program_id'=>$program_id,
            'module'=>sanitize_key($input['module']??'system')?:'system',
            'title'=>$title,
            'status'=>'open',
            'priority'=>$priority,
            'assigned_user_id'=>$assigned?:null,
            'due_at'=>$due,
            'completed_at'=>null,
            'metadata'=>$metadata,
            'created_by'=>get_current_user_id(),
            'created_at'=>$now,
            'updated_at'=>$now
        ));
        if(false===$ok||!$wpdb->insert_id)return new WP_Error('mdg_ai_task_create_failed','Görev oluşturulamadı.');
        $id=(int)$wpdb->insert_id;
        MMC_Program_Service::add_log($program_id,'task_created','task',$id,null,array('title'=>$title,'module'=>sanitize_key($input['module']??'system'),'priority'=>$priority),'AI üzerinden görev oluşturuldu.');
        return mdg_ai_task_get(array('task_id'=>$id));
    }
}

if ( ! function_exists( 'mdg_ai_task_update' ) ) {
    function mdg_ai_task_update( $input ) {
        global $wpdb;
        $id=absint($input['task_id']??0);
        $table=mdg_ai_tasks_table();
        $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d LIMIT 1",$id));
        if(!$old)return new WP_Error('mdg_ai_task_missing','Görev bulunamadı.');

        $data=array('updated_at'=>current_time('mysql'));

        if(array_key_exists('title',$input)){
            $title=sanitize_text_field($input['title']);
            if(!$title)return new WP_Error('mdg_ai_task_title_required','Görev başlığı boş olamaz.');
            $data['title']=$title;
        }
        if(array_key_exists('module',$input))$data['module']=sanitize_key($input['module'])?:'system';
        if(array_key_exists('priority',$input)){
            $priority=sanitize_key($input['priority']);
            if(!in_array($priority,array('low','normal','high','critical'),true))return new WP_Error('mdg_ai_task_priority_invalid','Geçersiz öncelik.');
            $data['priority']=$priority;
        }
        if(array_key_exists('assigned_user_id',$input)){
            $uid=absint($input['assigned_user_id']);
            if($uid&&!get_user_by('id',$uid))return new WP_Error('mdg_ai_task_user_missing','Atanacak kullanıcı bulunamadı.');
            $data['assigned_user_id']=$uid?:null;
        }
        if(array_key_exists('due_at',$input)){
            $due=sanitize_text_field($input['due_at']);
            if($due){
                $due=str_replace('T',' ',$due);
                $ts=strtotime($due);
                if(!$ts)return new WP_Error('mdg_ai_task_due_invalid','Geçersiz son tarih.');
                $data['due_at']=wp_date('Y-m-d H:i:s',$ts);
            } else $data['due_at']=null;
        }
        if(array_key_exists('metadata',$input))$data['metadata']=wp_json_encode($input['metadata'],JSON_UNESCAPED_UNICODE);

        $ok=$wpdb->update($table,$data,array('id'=>$id));
        if(false===$ok)return new WP_Error('mdg_ai_task_update_failed','Görev güncellenemedi.');
        MMC_Program_Service::add_log((int)$old->program_id,'task_updated','task',$id,mdg_ai_task_to_array($old),$data,'AI üzerinden görev güncellendi.');
        return mdg_ai_task_get(array('task_id'=>$id));
    }
}

if ( ! function_exists( 'mdg_ai_task_set_status' ) ) {
    function mdg_ai_task_set_status( $input ) {
        global $wpdb;
        $id=absint($input['task_id']??0);
        $status=sanitize_key($input['status']??'');
        if(!in_array($status,array('open','completed','cancelled'),true))return new WP_Error('mdg_ai_task_status_invalid','Geçersiz görev durumu.');

        $table=mdg_ai_tasks_table();
        $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d LIMIT 1",$id));
        if(!$old)return new WP_Error('mdg_ai_task_missing','Görev bulunamadı.');
        if($old->status===$status)return array('unchanged'=>true,'task'=>mdg_ai_task_to_array($old));

        $now=current_time('mysql');
        $data=array(
            'status'=>$status,
            'completed_at'=>'completed'===$status?$now:null,
            'updated_at'=>$now
        );
        $ok=$wpdb->update($table,$data,array('id'=>$id));
        if(false===$ok)return new WP_Error('mdg_ai_task_status_failed','Görev durumu güncellenemedi.');

        MMC_Program_Service::add_log((int)$old->program_id,'task_status_changed','task',$id,$old->status,$status,sanitize_text_field($input['note']??'AI üzerinden görev durumu güncellendi.'));
        return mdg_ai_task_get(array('task_id'=>$id));
    }
}

add_action('wp_abilities_api_categories_init',function(){
    if(function_exists('wp_register_ability_category')){
        wp_register_ability_category('madagaskar-gorevler',array(
            'label'=>'Madagaskar Görevler',
            'description'=>'MMC program görevlerini listeleme, oluşturma, güncelleme, tamamlama ve iptal etme.'
        ));
    }
});

add_action('wp_abilities_api_init',function(){
    if(!function_exists('wp_register_ability'))return;

    $read=array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'public'=>true);
    $create=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false),'public'=>true);
    $write=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false),'public'=>true);
    $status=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>true),'public'=>true);

    wp_register_ability('madagaskar/tasks-list',array(
        'label'=>'Görevleri Listele',
        'description'=>'MMC görevlerini program, modül, durum, öncelik, atanan kullanıcı, gecikme veya arama metnine göre listeler.',
        'category'=>'madagaskar-gorevler',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'program_id'=>array('type'=>'integer','minimum'=>1),
            'module'=>array('type'=>'string'),
            'status'=>array('type'=>'string','enum'=>array('open','completed','cancelled')),
            'priority'=>array('type'=>'string','enum'=>array('low','normal','high','critical')),
            'assigned_user_id'=>array('type'=>'integer','minimum'=>1),
            'search'=>array('type'=>'string'),
            'overdue_only'=>array('type'=>'boolean'),
            'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>500)
        )),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_tasks_list',
        'permission_callback'=>'mdg_ai_tasks_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/task-get',array(
        'label'=>'Görev Detayını Getir',
        'description'=>'Tek MMC görev kaydını getirir.',
        'category'=>'madagaskar-gorevler',
        'input_schema'=>array('type'=>'object','properties'=>array('task_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('task_id')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_task_get',
        'permission_callback'=>'mdg_ai_tasks_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/tasks-summary',array(
        'label'=>'Görev Özetini Getir',
        'description'=>'Tüm sistem veya tek program için açık, tamamlanan, iptal ve geciken görev sayılarını getirir.',
        'category'=>'madagaskar-gorevler',
        'input_schema'=>array('type'=>'object','properties'=>array('program_id'=>array('type'=>'integer','minimum'=>1))),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_tasks_summary',
        'permission_callback'=>'mdg_ai_tasks_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/task-create',array(
        'label'=>'Görev Oluştur',
        'description'=>'Programa yeni MMC görevi ekler ve program işlem günlüğüne kaydeder.',
        'category'=>'madagaskar-gorevler',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'program_id'=>array('type'=>'integer','minimum'=>1),
            'module'=>array('type'=>'string'),
            'title'=>array('type'=>'string'),
            'priority'=>array('type'=>'string','enum'=>array('low','normal','high','critical')),
            'assigned_user_id'=>array('type'=>'integer','minimum'=>1),
            'due_at'=>array('type'=>'string'),
            'metadata'=>array('type'=>array('object','array','string','number','boolean','null'))
        ),'required'=>array('program_id','title')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_task_create',
        'permission_callback'=>'mdg_ai_tasks_can_run',
        'meta'=>$create
    ));

    wp_register_ability('madagaskar/task-update',array(
        'label'=>'Görevi Güncelle',
        'description'=>'Görev başlığı, modülü, önceliği, atanan kullanıcı, son tarih veya metadata alanını kısmi günceller.',
        'category'=>'madagaskar-gorevler',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'task_id'=>array('type'=>'integer','minimum'=>1),
            'module'=>array('type'=>'string'),
            'title'=>array('type'=>'string'),
            'priority'=>array('type'=>'string','enum'=>array('low','normal','high','critical')),
            'assigned_user_id'=>array('type'=>'integer','minimum'=>1),
            'due_at'=>array('type'=>'string'),
            'metadata'=>array('type'=>array('object','array','string','number','boolean','null'))
        ),'required'=>array('task_id')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_task_update',
        'permission_callback'=>'mdg_ai_tasks_can_run',
        'meta'=>$write
    ));

    wp_register_ability('madagaskar/task-set-status',array(
        'label'=>'Görev Durumunu Değiştir',
        'description'=>'Görevi açık, tamamlandı veya iptal durumuna getirir. Görev silinmez; işlem günlüğü korunur.',
        'category'=>'madagaskar-gorevler',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'task_id'=>array('type'=>'integer','minimum'=>1),
            'status'=>array('type'=>'string','enum'=>array('open','completed','cancelled')),
            'note'=>array('type'=>'string')
        ),'required'=>array('task_id','status')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_task_set_status',
        'permission_callback'=>'mdg_ai_tasks_can_run',
        'meta'=>$status
    ));
});
