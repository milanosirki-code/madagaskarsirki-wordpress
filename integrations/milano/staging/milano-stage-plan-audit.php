<?php
/**
 * Plugin Name: Milano Yalova Staging Plan Audit
 * Version: 0.6.0
 * Description: Authenticated read-only production-plan audit for MDG24 on the isolated staging hostname only.
 * Author: Dünya Organizasyon
 */
if (!defined('ABSPATH')) {exit;}
add_action('rest_api_init',function(){
    if (parse_url(site_url(),PHP_URL_HOST)!=='staging-f321-milanosirki-lgajf.wpcomstaging.com') {return;}
    register_rest_route('milano-staging/v1','/yalova-plan',array(
        'methods'=>'GET',
        'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
        'callback'=>static function() {
            if (!class_exists('MDG_Events')||!class_exists('MDG_New_Event_Production_Plan')) {
                return new WP_Error('dependencies_missing','MDG planner unavailable.',array('status'=>503));
            }
            $event=MDG_Events::get(24);
            if (!$event || $event->status!=='draft' || (string)$event->title!=='Milano Sirki — Yalova') {
                return new WP_Error('wrong_event','Staging canonical Milano draft unavailable.',array('status'=>409));
            }
            $plan=MDG_New_Event_Production_Plan::build($event);
            $products=array();
            foreach((array)($plan['products']??array()) as $p){
                $products[]=array(
                    'session_id'=>$p['session_id']??null,
                    'date'=>$p['date']??null,
                    'time'=>$p['time']??null,
                    'product_name'=>$p['product_name']??null,
                    'product_slug'=>$p['product_slug']??null,
                    'capacity_total'=>$p['capacity_total']??null,
                    'variations'=>array_map(static function($v){return array(
                        'label'=>$v['label']??'', 'price'=>$v['price']??null,
                        'capacity_units'=>$v['capacity_units']??null,
                    );},(array)($p['variations']??array()))
                );
            }
            return rest_ensure_response(array(
                'ready'=>(bool)($plan['ready']??false),
                'event_id'=>$plan['event_id']??null,
                'brand'=>$plan['brand']??null,
                'session_count'=>$plan['session_count']??null,
                'errors'=>$plan['errors']??array(),
                'warnings'=>$plan['warnings']??array(),
                'template'=>$plan['template']??null,
                'tickera'=>$plan['tickera']??null,
                'products'=>$products,
                'note'=>'Readonly MDG production plan; no Woo, Tickera, QR or PayTR writes.'
            ));
        }
    ));
});


/**
 * Staging-only controlled invocation of the actual MDG draft producer.
 * Never creates payment, orders, live tickets or publish-state products.
 * This endpoint is intentionally outside the public Milano customer site.
 */
add_action('rest_api_init',static function() {
    if (parse_url(site_url(),PHP_URL_HOST)!=='staging-f321-milanosirki-lgajf.wpcomstaging.com') {return;}
    register_rest_route('milano-staging/v1','/yalova-draft-state',array(
        'methods'=>'GET',
        'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
        'callback'=>static function() {
            if (!class_exists('MDG_Events')||!class_exists('MDG_New_Event_Production_Plan')||!class_exists('MDG_New_Event_Draft_Producer')) {
                return new WP_Error('missing_producer','MDG class unavailable',array('status'=>503));
            }
            $event=MDG_Events::get(24);
            if (!$event || (string)$event->title!=='Milano Sirki — Yalova') {
                return new WP_Error('unverified_event','Yalova event not found',array('status'=>409));
            }
            $plan=MDG_New_Event_Production_Plan::build($event);
            return rest_ensure_response(array(
                'event_id'=>24, 'brand'=>$plan['brand']??null, 'ready'=>$plan['ready']??false,
                'state'=>MDG_New_Event_Draft_Producer::state(24,(string)($plan['production_key']??'')),
                'errors'=>$plan['errors']??array()
            ));
        }
    ));
    register_rest_route('milano-staging/v1','/yalova-produce-drafts',array(
        'methods'=>'POST',
        'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
        'callback'=>static function() {
            if (!class_exists('MDG_Events')||!class_exists('MDG_New_Event_Production_Plan')||!class_exists('MDG_New_Event_Draft_Producer')) {
                return new WP_Error('missing_producer','MDG draft producer unavailable',array('status'=>503));
            }
            $event=MDG_Events::get(24);
            if (!$event || (string)$event->status!=='draft' || (string)$event->title!=='Milano Sirki — Yalova'
                || (int)$event->venue_id!==181 || (int)$event->hero_attachment_id!==6048
                || (int)$event->venue_qr_attachment_id!==6047) {
                return new WP_Error('event_guard','Yalova identity/assets no longer match',array('status'=>409));
            }
            $plan=MDG_New_Event_Production_Plan::build($event);
            $brand=$plan['brand']??array();
            $tpl=$plan['template']??array();
            if (empty($plan['ready']) || (int)($brand['program_id']??0)!==14
                || ($brand['key']??'')!=='milano'
                || (int)($tpl['base_template_id']??0)!==28
                || ($tpl['candidate_id']??'')!=='milano_yalova_venue_qr_v1'
                || (int)($plan['session_count']??0)!==2
                || array_map('intval',(array)($plan['session_ids']??array()))!==array(150,151)) {
                return new WP_Error('plan_guard','Staging plan changed; no draft production',array('status'=>409));
            }
            global $wpdb;
            $sha=$wpdb->get_var("SELECT SHA2(template_data,256) FROM {$wpdb->prefix}tickera_ticket_templates WHERE id=28 AND status='active' LIMIT 1");
            if (!hash_equals('8ce4f316026e6ba707561b408e87d16e51ee71c91dd67d31b7695959741525cc',(string)$sha)) {
                return new WP_Error('designer_changed','Designer content hash changed',array('status'=>409));
            }
            $sessions=$wpdb->get_results("SELECT id,start_at,capacity_total,held_units,sold_units,wc_product_id,tickera_event_id FROM {$wpdb->prefix}mdg_sessions WHERE event_id=24 ORDER BY id");
            if (count((array)$sessions)!==2) {return new WP_Error('session_count','Expected exactly two sessions',array('status'=>409));}
            foreach($sessions as $i=>$s) {
                if ((int)$s->id!==array(150,151)[$i] || (int)$s->capacity_total!==500
                    || (int)$s->sold_units!==0 || (int)$s->held_units!==0
                    || (int)$s->wc_product_id!==0 || (int)$s->tickera_event_id!==0) {
                    return new WP_Error('session_changed','Existing capacity or mapping prevents draft create',array('status'=>409));
                }
            }
            $state=MDG_New_Event_Draft_Producer::state(24,(string)$plan['production_key']);
            if (($state['status']??'')!=='clean') {
                return new WP_Error('not_clean','Producer state not clean; no repeat production',array('status'=>409));
            }
            // The owned producer retains its WP capability, nonce, state,
            // create, managed-object rollback, audit, mapping and idempotency guards.
            $nonce=wp_create_nonce('mdg_create_new_event_sales_draft_24');
            $_POST['event_id']=24;
            $_POST['confirm_create']='1';
            $_POST['mdg_nonce']=$nonce;
            $_REQUEST['event_id']=24;
            $_REQUEST['mdg_nonce']=$nonce;
            MDG_New_Event_Draft_Producer::handle_create();
            return new WP_Error('unreachable','Native producer unexpectedly returned without redirect',array('status'=>500));
        }
    ));
});


/** Read-only parser contract for the already-existing session datetime PDF fix. */
add_action('rest_api_init',static function(){
 if(parse_url(site_url(),PHP_URL_HOST)!=='staging-f321-milanosirki-lgajf.wpcomstaging.com'){return;}
 register_rest_route('milano-staging/v1','/yalova-datetime-smoke',array(
  'methods'=>'GET',
  'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
  'callback'=>static function(){
   if(!function_exists('mdg_tdfix_correct_ticket_data')){return new WP_Error('fix_inactive','Existing Tickera datetime fix is not active.',array('status'=>503));}
   $pairs=array(array(6058,'18:00','19:00'),array(6061,'19:30','20:30'));
   $results=array();
   foreach($pairs as $pair){
    $post=get_post($pair[0]);
    if(!$post||$post->post_type!=='product'||$post->post_status!=='draft'
       ||false===stripos((string)$post->post_title,'Milano Sirki')
       ||(int)get_post_meta((int)$post->ID,'_mdg_event_id',true)!==24
       ||(string)get_post_meta((int)$post->ID,'_mdg_production_key',true)!=='MDG-E24-618c401e2640'){
      return new WP_Error('wrong_product','Canonical staging ticket product mismatch.',array('status'=>409));
    }
    $ticket_data=array(
     'event_name'=>'Milano Sirki — Yalova',
     'event_datetime'=>'22 Ekim 2026 18:00 - 20:30',
     'product_name'=>(string)$post->post_title
    );
    $out=mdg_tdfix_correct_ticket_data($ticket_data,0);
    $str=(string)($out['event_datetime']??'');
    $ok=($out['event_start_time']??'')===$pair[1]&&($out['event_end_time']??'')===$pair[2]
     &&false!==strpos($str,'22')&&false!==strpos($str,$pair[1]);
    $results[]=array('product_id'=>$pair[0],'expected_start'=>$pair[1],
     'expected_end'=>$pair[2],'resolved'=>$str,'pass'=>$ok);
   }
   return rest_ensure_response(array(
    'read_only'=>true,
    'type'=>'synthetic parser smoke; NOT a real Tickera PDF or real ticket instance',
    'all_pass'=>count(array_filter($results,static function($r){return $r['pass'];}))===count($results),
    'results'=>$results,
    'hook_attached'=>(bool)has_filter('tickera_ticket_designer_pre_generate','mdg_tdfix_pre_generate')
   ));
  }
 ));
});


/**
 * Safe Yalova asset+family readback. Does not create orders, tickets, holds or PDFs.
 * Assess the previously uploaded venue QR PNG pixels rather than assuming that
 * an attachment whose name contains "QR" holds an actual matrix barcode.
 */
add_action('rest_api_init', static function () {
 if (parse_url(site_url(), PHP_URL_HOST) !== 'staging-f321-milanosirki-lgajf.wpcomstaging.com') { return; }
 register_rest_route('milano-staging/v1', '/yalova-asset-family-audit', array(
  'methods'=>'GET',
  'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
  'callback'=>static function(){
   if (!class_exists('MDG_Events')) {return new WP_Error('mdg_missing','MDG is unavailable.',array('status'=>503));}
   $event=MDG_Events::get(24);
   if (!$event || $event->status!=='draft' || (string)$event->title!=='Milano Sirki — Yalova'
       || (int)$event->venue_id!==181 || (int)$event->venue_qr_attachment_id!==6047) {
    return new WP_Error('wrong_event','Draft venue ownership changed.',array('status'=>409));
   }
   global $wpdb;
   $bridge=$wpdb->get_results($wpdb->prepare(
    "SELECT p.brand FROM {$wpdb->prefix}mmc_mdg_event_bridge b INNER JOIN {$wpdb->prefix}mmc_programs p ON p.id=b.program_id WHERE b.is_active=1 AND b.mdg_event_id=%d LIMIT 2",24
   ));
   if (count((array)$bridge)!==1 || (string)$bridge[0]->brand!=='Milano Sirki'){
    return new WP_Error('wrong_brand','No unique canonical Milano bridge.',array('status'=>409));
   }
   $file=get_attached_file(6047);
   $exists=is_string($file)&&is_file($file)&&is_readable($file);
   $bytes=$exists?filesize($file):0;
   $dimensions=$exists?@getimagesize($file):false;
   $pixels=array('sampled'=>false,'dark_ratio'=>null,'light_ratio'=>null,'transitions_horizontal'=>0,'transitions_vertical'=>0);
   if ($exists && $dimensions && function_exists('imagecreatefrompng') && (int)$dimensions[0]===600 && (int)$dimensions[1]===600) {
     $im=@imagecreatefrompng($file);
     if ($im) {
       $counts=array('dark'=>0,'light'=>0,'other'=>0); $transH=0;$transV=0;
       for ($y=0;$y<600;$y+=6) {
         $prev=null;
         for ($x=0;$x<600;$x+=6) {
           $rgb=imagecolorat($im,$x,$y);
           $c=imagecolorsforindex($im,$rgb);
           $brightness=($c['red']+$c['green']+$c['blue'])/3;
           $cat=($brightness<96)?'dark':(($brightness>205)?'light':'other');
           $counts[$cat]++;
           if ($prev!==null && $prev!==$cat) {$transH++;}
           $prev=$cat;
         }
       }
       for ($x=0;$x<600;$x+=6){
         $prev=null;
         for($y=0;$y<600;$y+=6){
           $c=imagecolorsforindex($im,imagecolorat($im,$x,$y));
           $brightness=($c['red']+$c['green']+$c['blue'])/3;
           $cat=($brightness<96)?'dark':(($brightness>205)?'light':'other');
           if ($prev!==null && $prev!==$cat){$transV++;}
           $prev=$cat;
         }
       }
       imagedestroy($im);
       $total=array_sum($counts);
       $pixels=array('sampled'=>true,'dark_ratio'=>round($counts['dark']/max(1,$total),3),
        'light_ratio'=>round($counts['light']/max(1,$total),3),
        'transitions_horizontal'=>$transH,'transitions_vertical'=>$transV);
     }
   }
   $pixel_matrix_ok=$pixels['sampled'] && $pixels['dark_ratio']>0.1 && $pixels['dark_ratio']<0.75
      && $pixels['light_ratio']>0.18 && $pixels['transitions_horizontal']>70 && $pixels['transitions_vertical']>70;
   $settings=get_option('mdg_family_package_22_v1',array());
   $enabled=!empty($settings['events'][24]) || in_array(24, is_array($settings['events']??null)?$settings['events']:array(),true);
   $mmc_types=$wpdb->get_results("SELECT ticket_code,price,capacity_units,is_active FROM {$wpdb->prefix}mmc_ticket_types WHERE event_id=14 ORDER BY id");
   $family=null;
   foreach((array)$mmc_types as $mt){
    if((string)$mt->ticket_code==='family_2_2'&&(int)$mt->is_active===1){$family=$mt;break;}
   }
   $ses=$wpdb->get_results("SELECT id,capacity_total,sold_units,held_units,wc_product_id,tickera_event_id FROM {$wpdb->prefix}mdg_sessions WHERE event_id=24 ORDER BY id");
   $perSession=array();
   foreach((array)$ses as $s){
      $types=$wpdb->get_results($wpdb->prepare(
       "SELECT id,code,label,price,capacity_units,wc_variation_id FROM {$wpdb->prefix}mdg_ticket_types WHERE session_id=%d AND is_active=1 ORDER BY id",(int)$s->id
      ));
      $child=null;$adult=null;$variation_ok=true;
      foreach((array)$types as $t){
       if(strtoupper((string)$t->code)==='CHILD'){$child=$t;}
       if(strtoupper((string)$t->code)==='ADULT'){$adult=$t;}
       $variation=get_post((int)$t->wc_variation_id);
       if(!$variation || $variation->post_type!=='product_variation'
          || (int)$variation->post_parent!==(int)$s->wc_product_id
          || (int)get_post_meta((int)$t->wc_variation_id,'_mdg_event_id',true)!==24){$variation_ok=false;}
      }
      $parent=get_post((int)$s->wc_product_id);
      $owned=$parent&&$parent->post_type==='product'&&$parent->post_status==='draft'
           &&false!==stripos($parent->post_title,'Milano Sirki')
           &&(string)get_post_meta((int)$parent->ID,'_mdg_production_key',true)==='MDG-E24-618c401e2640';
      $ok=(int)$s->capacity_total===500&&(int)$s->sold_units===0&&(int)$s->held_units===0
         &&count((array)$types)===2 && $child && $adult && (int)$child->capacity_units===1&&(int)$adult->capacity_units===1
         &&(float)$child->price===245.0&&(float)$adult->price===490.0 && $variation_ok && $owned
         &&(int)$s->tickera_event_id===6056;
      $perSession[]=array('session_id'=>(int)$s->id,'pass'=>(bool)$ok,'product_id'=>(int)$s->wc_product_id,
         'child_variation'=>(int)($child->wc_variation_id??0),'adult_variation'=>(int)($adult->wc_variation_id??0),
         'quantity_2plus2'=>4,'normal_total'=>round(2*(float)($child->price??0)+2*(float)($adult->price??0),2));
   }
   $package_price=$family?round((float)$family->price,2):null;
   $family_ok=$enabled && $family && (int)$family->capacity_units===4 && $package_price===1100.0
        && count($perSession)===2 && array_reduce($perSession,static function($acc,$x){return $acc && $x['pass'];},true);
   return rest_ensure_response(array(
    'read_only'=>true,'event_id'=>24,'brand'=>'milano',
    'venue_qr'=>array(
       'attachment_id'=>6047,'mime_type'=>get_post_mime_type(6047),'bytes'=>$bytes,
       'width'=>$dimensions?(int)$dimensions[0]:0,'height'=>$dimensions?(int)$dimensions[1]:0,
       'matrix_looks_like_qr'=>$pixel_matrix_ok,'pixel_metrics'=>$pixels,
       'note'=>'Bitmap matrix heuristic only; QR payload and physical scanner not yet tested'
    ),
    'family_2plus2'=>array('enabled'=>(bool)$enabled,'price'=>$package_price,
       'normal_total'=>1470,'discount'=>($package_price===null?null:1470-$package_price),
       'capacity_units_per_package'=>4,'config_ready'=>(bool)$family_ok,'sessions'=>$perSession,
       'actual_four_distinct_ticket_instances_verified'=>false
    )
   ));
  }
 ));
});


/** Admin-only staging venue QR bytes for offline decoding. No arbitrary path or URL. */
add_action('rest_api_init',static function(){
 if(parse_url(site_url(),PHP_URL_HOST)!=='staging-f321-milanosirki-lgajf.wpcomstaging.com')return;
 register_rest_route('milano-staging/v1','/yalova-venue-qr-file',array(
  'methods'=>'GET',
  'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
  'callback'=>static function(){
   $post=get_post(6047);
   if(!$post||$post->post_type!=='attachment'||get_post_mime_type(6047)!=='image/png'){
    return new WP_Error('asset_changed','QR attachment no longer matches approved image.',array('status'=>409));
   }
   $event=class_exists('MDG_Events')?MDG_Events::get(24):null;
   if(!$event||(int)$event->venue_qr_attachment_id!==6047||(string)$event->title!=='Milano Sirki — Yalova'){
    return new WP_Error('event_changed','QR not bound to Yalova.',array('status'=>409));
   }
   $file=get_attached_file(6047);
   if(!is_string($file)||!is_file($file)||!is_readable($file)||filesize($file)>20000){
    return new WP_Error('file_unavailable','Staging QR not accessible or too large.',array('status'=>409));
   }
   $bytes=file_get_contents($file);
   if(!is_string($bytes)||substr($bytes,0,8)!=="\x89PNG\r\n\x1a\n"){
    return new WP_Error('not_png','QR payload is not PNG.',array('status'=>409));
   }
   $response=rest_ensure_response(array('attachment_id'=>6047,'bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'base64'=>base64_encode($bytes),'readonly'=>true));
   $response->header('Cache-Control','private,no-store,max-age=0');return $response;
  }
 ));
});


/** End-to-end family adaptor *request transformation* without checkout, orders or ticket issuance. */
add_action('rest_api_init',static function(){
 if(parse_url(site_url(),PHP_URL_HOST)!=='staging-f321-milanosirki-lgajf.wpcomstaging.com'){return;}
 register_rest_route('milano-staging/v1','/yalova-family-runtime-smoke',array(
  'methods'=>'GET',
  'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
  'callback'=>static function(){
   if(!class_exists('MDG_Family_Package_22_V1')){
    return new WP_Error('module_missing','Existing family 2+2 module unavailable.',array('status'=>503));
   }
   $e=class_exists('MDG_Events')?MDG_Events::get(24):null;
   if(!$e || (string)$e->status!=='draft'||(string)$e->title!=='Milano Sirki — Yalova'){
    return new WP_Error('event_changed','Yalova staging event changed.',array('status'=>409));
   }
   global $wpdb;
   $original_post=$_POST;
   $rows=array();
   try {
    foreach(array(array(150,6058,342,343,6059,6060),array(151,6061,344,345,6062,6063)) as $pair){
      list($sid,$parent,$childType,$adultType,$childVar,$adultVar)=$pair;
      $post=get_post($parent);
      if(!$post || $post->post_status!=='draft'||$post->post_type!=='product'
         ||(string)get_post_meta($parent,'_mdg_production_key',true)!=='MDG-E24-618c401e2640'){
       return new WP_Error('product_guard','Canonical staged product unavailable.',array('status'=>409));
      }
      $_POST=array('event_id'=>24,'session_id'=>$sid,'lines'=>wp_json_encode(array(array('code'=>'MDG_FAMILY_2_2','qty'=>1))));
      MDG_Family_Package_22_V1::preprocess_family_line();
      $items=json_decode((string)($_POST['lines']??''),true);
      $expected=array('CHILD'=>2,'ADULT'=>2);
      $found=array();
      foreach((array)$items as $item){$found[strtoupper((string)($item['code']??''))]=(int)($item['qty']??0);}
      $child=MDG_Family_Package_22_V1::mark_family_components(array('mdg_ticket_type_id'=>$childType),$parent,$childVar,2);
      $adult=MDG_Family_Package_22_V1::mark_family_components(array('mdg_ticket_type_id'=>$adultType),$parent,$adultVar,2);
      $sameGroup=!empty($child['mdg_family22_group']) && $child['mdg_family22_group']===$adult['mdg_family22_group'];
      $discount=(float)($child['mdg_family22_discount_per']??0);
      $good=($found==$expected) && $sameGroup && $discount===370.0
        && ($child['mdg_family22_component']??'')==='child'
        && ($adult['mdg_family22_component']??'')==='adult'
        && (int)($child['mdg_family22_packages']??0)===1
        && (int)($adult['mdg_family22_packages']??0)===1;
      $rows[]=array('session_id'=>$sid,'pass'=>$good,'rewritten_lines'=>$items,
       'package_count'=>1,'normal_total'=>1470,'discount'=>$discount,
       'final_total'=>1470-$discount,'component_group_matches'=>$sameGroup,
       'parent_draft_id'=>$parent,'child_variation_id'=>$childVar,'adult_variation_id'=>$adultVar);
    }
   } finally {
    $_POST=$original_post;
   }
   return rest_ensure_response(array(
      'read_only'=>true,'real_order_or_ticket_created'=>false,
      'all_pass'=>count($rows)===2 && $rows[0]['pass'] && $rows[1]['pass'],
      'results'=>$rows,
      'scope'=>'Actual existing family module PHP preprocessor+cart-item metadata, not an order, payment, checkout or Tickera QR issuance'
   ));
  }
 ));
});
