<?php
/**
 * Temporary Stage 2 diagnostics. Admin-only, no raw logs, credentials or cart tokens returned.
 * Runtime/source/log modes are read-only. Checkout probe uses a fresh guest cookie jar,
 * adds one validated future ticket, reads checkout draft, then removes only its own cart item.
 * No payment submission, payment_complete, order status write, email or CRM send.
 * Rollback: deactivate this diagnostic snippet; retain any draft order for Woo expiry.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function mdg_stage2_allowed() {
    return current_user_can( 'manage_options' );
}
function mdg_stage2_path( $path ) {
    $root = wp_normalize_path( ABSPATH );
    $path = wp_normalize_path( $path );
    return strpos( $path, $root ) === 0 ? substr( $path, strlen( $root ) ) : basename( $path );
}
function mdg_stage2_callback( $callback ) {
    try {
        if ( is_array( $callback ) ) {
            $class = is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0];
            $name = $class . '::' . $callback[1];
            $ref = new ReflectionMethod( $callback[0], $callback[1] );
        } elseif ( $callback instanceof Closure ) {
            $name = 'Closure';
            $ref = new ReflectionFunction( $callback );
        } elseif ( is_string( $callback ) ) {
            $name = $callback;
            $ref = strpos( $callback, '::' ) !== false ? new ReflectionMethod( $callback ) : new ReflectionFunction( $callback );
        } else {
            return array( 'name'=>'unresolved' );
        }
        return array( 'name'=>$name, 'file'=>mdg_stage2_path( $ref->getFileName() ?: '' ), 'start'=>$ref->getStartLine(), 'end'=>$ref->getEndLine() );
    } catch ( Throwable $e ) { return array( 'name'=>'unresolved', 'exception'=>get_class( $e ) ); }
}
function mdg_stage2_read( $input ) {
    $mode = $input['mode'] ?? 'runtime';
    if ( $mode === 'runtime' ) {
        global $wp_filter;
        $out = array( 'utc'=>gmdate('c'), 'hooks'=>array(), 'routes'=>array() );
        foreach ( array('woocommerce_checkout_create_order','woocommerce_checkout_order_created','woocommerce_checkout_order_processed','woocommerce_store_api_checkout_order_processed','woocommerce_payment_complete','woocommerce_order_status_changed','woocommerce_order_status_processing','woocommerce_order_status_completed','code_snippets/deactivate_snippet','shutdown') as $hook ) {
            $out['hooks'][$hook] = array();
            if ( isset($wp_filter[$hook]) ) {
                foreach ( $wp_filter[$hook]->callbacks as $priority=>$callbacks ) {
                    foreach ( $callbacks as $entry ) {
                        $row=mdg_stage2_callback($entry['function']); $row['priority']=$priority; $row['args']=$entry['accepted_args'];
                        $out['hooks'][$hook][]=$row;
                    }
                }
            }
        }
        foreach ( rest_get_server()->get_routes() as $route=>$handlers ) {
            if ( strpos($route,'code-snippets/v1/snippets/')!==false && strpos($route,'deactivate')!==false ) {
                foreach($handlers as $handler) { if(isset($handler['callback'])) $out['routes'][]=array('route'=>$route,'callback'=>mdg_stage2_callback($handler['callback'])); }
            }
        }
        return $out;
    }
    if ( $mode === 'hashes' ) {
        $out=array();
        foreach(array('madagaskar-management-center','madagaskar-bilet-yonetimi','madagaskar-bilet-yonetimi-v4','madagaskar-ai-abilities','madagaskar-checkout-customizations','madagaskar-kommo-automation','madagaskar-aile-paketi-22') as $slug) {
            $dir=WP_PLUGIN_DIR.'/'.$slug;
            if(!is_dir($dir))continue;
            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));
            foreach($it as $file) {
                if($file->isLink() || !$file->isFile() || !in_array(strtolower($file->getExtension()),array('php','js','css','json','md','txt','csv'),true))continue;
                $out[]=array('path'=>'wp-content/plugins/'.$slug.'/'.substr($file->getPathname(),strlen($dir)+1),'sha256'=>hash_file('sha256',$file->getPathname()),'bytes'=>$file->getSize());
            }
        }
        return array('files'=>$out);
    }
    if ( $mode === 'logs' ) {
        $paths=array(WP_CONTENT_DIR.'/debug.log',WP_CONTENT_DIR.'/php-error.log');
        $configured=ini_get('error_log'); if($configured && is_file($configured)) $paths[]=$configured;
        if(defined('WC_LOG_DIR')) { foreach((array)glob(WC_LOG_DIR.'*fatal*2026-10-03*') as $p) $paths[]=$p; }
        $out=array();
        foreach(array_unique($paths) as $p) {
            $row=array('file'=>mdg_stage2_path($p),'exists'=>is_file($p),'readable'=>is_readable($p),'entries'=>array());
            if(is_file($p)&&is_readable($p)) {
                $fp=fopen($p,'rb'); if($fp) {
                    $size=filesize($p); fseek($fp,max(0,$size-2097152)); $tail=stream_get_contents($fp); fclose($fp);
                    foreach(explode("\n",$tail) as $line) {
                        if(!preg_match('/2026-10-03|03-Oct-2026/i',$line))continue;
                        if(preg_match('/(?:Fatal error|Uncaught)[^\r\n]*?(?:in|at) ([^\s]+\.php)(?::| on line )(\d+)/i',$line,$m)) {
                            $entry=array('file'=>mdg_stage2_path($m[1]),'line'=>(int)$m[2]);
                            if(preg_match('/Uncaught ([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/',$line,$e))$entry['exception']=$e[1];
                            if(preg_match('/^.{0,8}(\d{4}-\d{2}-\d{2}[T ][0-9:.+Z-]+|\d{2}-[A-Za-z]{3}-\d{4} [0-9:]+(?: UTC)?)/',$line,$t))$entry['timestamp']=$t[1];
                            $row['entries'][]=$entry;
                        }
                    }
                }
            }
            $row['entries']=array_slice($row['entries'],-50); $out[]=$row;
        }
        return array('debug_log_enabled'=>defined('WP_DEBUG_LOG')?WP_DEBUG_LOG:null,'logs'=>$out);
    }
    if ( $mode === 'source' ) {
        $map=array(
            'sales'=>WP_PLUGIN_DIR.'/madagaskar-management-center/includes/class-mmc-sales-service.php',
            'region'=>WP_PLUGIN_DIR.'/madagaskar-management-center/includes/class-mmc-region-service.php',
            'bridge'=>WP_PLUGIN_DIR.'/madagaskar-management-center/includes/class-mmc-mdg-bridge-service.php',
            'live-sales'=>WP_PLUGIN_DIR.'/madagaskar-bilet-yonetimi/includes/class-mdg-live-sales.php',
            'snippet-controller'=>WP_PLUGIN_DIR.'/code-snippets/php/rest-api/class-snippets-rest-controller.php'
        );
        $key=$input['target']??''; if(!isset($map[$key])||!is_readable($map[$key])) return new WP_Error('source_missing','Whitelisted source unavailable.');
        $s=file_get_contents($map[$key]);
        return array('target'=>$key,'sha256'=>hash('sha256',$s),'content'=>$s);
    }
    return new WP_Error('invalid_mode','Invalid diagnostic mode.');
}
function mdg_stage2_guest_probe( $input ) {
    $variation_id=absint($input['variation_id']??0);
    $product=function_exists('wc_get_product')?wc_get_product($variation_id):null;
    if(!$product||!$product->is_type('variation')||!$product->is_purchasable()||!$product->is_in_stock())return new WP_Error('invalid_product','A live purchasable ticket variation is required.');
    global $wpdb;
    $future=$wpdb->get_var($wpdb->prepare("SELECT s.id FROM {$wpdb->prefix}mdg_ticket_types t INNER JOIN {$wpdb->prefix}mdg_sessions s ON s.id=t.session_id INNER JOIN {$wpdb->prefix}mdg_events e ON e.id=s.event_id WHERE t.wc_variation_id=%d AND t.is_active=1 AND s.start_at>UTC_TIMESTAMP() AND e.status='onsale' LIMIT 1",$variation_id));
    if(!$future)return new WP_Error('invalid_session','Future onsale session mapping is required.');
    $cookies=array(); $nonce=''; $cart_token=''; $key=''; $steps=array(); $draft_id=0;
    $call=function($path,$method='GET',$body=null)use(&$cookies,&$nonce,&$cart_token,&$steps){
        $headers=array('Content-Type'=>'application/json','Cache-Control'=>'no-cache');
        if($nonce)$headers['Nonce']=$nonce;
        if($cart_token)$headers['Cart-Token']=$cart_token;
        $args=array('method'=>$method,'timeout'=>25,'redirection'=>0,'cookies'=>array_values($cookies),'headers'=>$headers);
        if($body!==null)$args['body']=wp_json_encode($body);
        $r=wp_remote_request(rest_url('wc/store/v1/'.$path),$args);
        if(is_wp_error($r)) { $steps[]=array('path'=>$path,'transport_error'=>$r->get_error_code()); return null; }
        foreach(wp_remote_retrieve_cookies($r) as $c)$cookies[$c->name]=$c;
        $n=wp_remote_retrieve_header($r,'nonce'); if($n)$nonce=$n;
        $t=wp_remote_retrieve_header($r,'cart-token'); if($t)$cart_token=$t;
        $status=wp_remote_retrieve_response_code($r); $data=json_decode(wp_remote_retrieve_body($r),true);
        $step=array('path'=>$path,'method'=>$method,'status'=>$status);
        if(is_array($data)&&isset($data['code']))$step['error_code']=$data['code'];
        $steps[]=$step;
        return array('status'=>$status,'data'=>$data);
    };
    try {
        $initial=$call('cart');
        if(!$initial||$initial['status']!==200||!empty($initial['data']['items']))return array('steps'=>$steps,'stopped'=>'Fresh empty guest cart not established.');
        if(!$nonce&&!$cart_token)return array('steps'=>$steps,'stopped'=>'No guest Store API authorization header.');
        $added=$call('cart/add-item','POST',array('id'=>$variation_id,'quantity'=>1));
        if(!$added||$added['status']!==200)return array('steps'=>$steps,'stopped'=>'Add-item did not succeed.');
        foreach($added['data']['items']??array() as $item) {
            if((int)$item['id']===$variation_id)$key=$item['key'];
        }
        if(!$key)return array('steps'=>$steps,'stopped'=>'Expected cart item missing.');
        $cart=$call('cart');
        $checkout=$call('checkout');
        if($checkout&&$checkout['status']===200) $draft_id=(int)($checkout['data']['order_id']??0);
        $ticket_count=null;
        if($draft_id) {
            $ticket_count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_type='tc_tickets_instances' AND m.meta_key IN ('tc_order_id','order_id','_order_id') AND m.meta_value=%s",(string)$draft_id));
        }
        $cleanup=$call('cart/remove-item','POST',array('key'=>$key));
        $key='';
        $cleared=$call('cart');
        return array('cart_cleanup_status'=>$cleanup['status']??null,'cart_empty_after_cleanup'=>empty($cleared['data']['items'])&&($cleared['status']??0)===200,'utc'=>gmdate('c'),'variation_id'=>$variation_id,'session_id'=>(int)$future,'steps'=>$steps,'cart_item_confirmed'=>(bool)$key,'cart_error_count'=>count($cart['data']['errors']??array()),'order_id'=>$draft_id,'order_status'=>$checkout['data']['status']??null,'payment_methods'=>$cart['data']['payment_methods']??array(),'ticket_count_by_known_order_keys'=>$ticket_count,'payment_submitted'=>false,'order_submission_posted'=>false,'limit'=>'Checkout GET draft initialization only. No checkout POST, PayTR token request, payment callback or ticket creation test.');
    } finally {
        if($key) $call('cart/remove-item','POST',array('key'=>$key));
    }
}
add_action('wp_abilities_api_init',function(){
    if(!function_exists('wp_register_ability'))return;
    wp_register_ability('madagaskar/stage2-runtime-audit',array(
        'label'=>'Temporary Stage2 read-only diagnostics','description'=>'Admin-only hook, source hash and sanitized fatal metadata diagnostics.',
        'category'=>'madagaskar-saglik',
        'input_schema'=>array('type'=>'object','properties'=>array('mode'=>array('type'=>'string','enum'=>array('runtime','hashes','logs','source')),'target'=>array('type'=>'string'))),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_stage2_read','permission_callback'=>'mdg_stage2_allowed',
        'meta'=>array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'show_in_rest'=>true)
    ));
    wp_register_ability('madagaskar/stage2-guest-checkout-probe',array(
        'label'=>'Temporary isolated guest checkout probe','description'=>'Creates an isolated guest cart and unpaid checkout draft only; clears its own cart; never submits payment or checkout POST.',
        'category'=>'madagaskar-saglik',
        'input_schema'=>array('type'=>'object','properties'=>array('variation_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('variation_id')),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_stage2_guest_probe','permission_callback'=>'mdg_stage2_allowed',
        'meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>false,'idempotent'=>false),'show_in_rest'=>true)
    ));
});
