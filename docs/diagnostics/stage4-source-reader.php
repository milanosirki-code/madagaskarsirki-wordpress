<?php
/**
 * Temporary admin-only Stage 4 source reader. Not a production module.
 * No checkout, payment, order, ticket, CRM or data-write operations.
 */
if (!defined('ABSPATH')) { exit; }
function mdg_stage4_source_read($input) {
    $slugs = array('madagaskar-management-center','madagaskar-bilet-yonetimi','madagaskar-bilet-yonetimi-v4','madagaskar-ai-abilities','madagaskar-checkout-customizations','madagaskar-kommo-automation','madagaskar-aile-paketi-22','madagaskar-legacy-redirects','madagaskar-okul-tanitim','madagaskar-population-data','madagaskar-fatura-takip','madagaskar-v5-finans-guncellemesi','madagaskar-v5-sabit-gider-turleri','madagaskar-menu-duzenleyici','madagaskar-yonetim-merkezi-v2-v3','madagaskar-yonetim-merkezi-v5','madagaskar-cekilis','dunya-fatura-takip');
    $slug = (string)($input['slug'] ?? '');
    if (!in_array($slug,$slugs,true)) { return new WP_Error('invalid_slug','Plugin not whitelisted.'); }
    $base = realpath(WP_PLUGIN_DIR.'/'.$slug);
    if (!$base || !is_dir($base)) { return new WP_Error('missing_plugin','Plugin unavailable.'); }
    if (($input['mode'] ?? '') === 'hashes') {
        $rows=array();
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
        foreach($it as $f) {
            if($f->isLink() || !$f->isFile() || !in_array(strtolower($f->getExtension()),array('php','js','css','md','txt','csv','json'),true)) { continue; }
            $raw=file_get_contents($f->getPathname());
            $rows[]=array('path'=>'wp-content/plugins/'.$slug.'/'.substr($f->getPathname(),strlen($base)+1),'sha256'=>hash('sha256',$raw),'git_blob_sha'=>sha1('blob '.strlen($raw).chr(0).$raw),'bytes'=>strlen($raw));
        }
        return array('slug'=>$slug,'files'=>$rows);
    }
    $path=(string)($input['path'] ?? '');
    if(strpos($path,'..')!==false || !preg_match('/^[a-zA-Z0-9_\/. -]+\.(php|js|css|md|txt)$/D',$path)) { return new WP_Error('invalid_path','Code or readme path required.'); }
    $full=realpath($base.'/'.$path);
    if(!$full || strpos($full,$base.DIRECTORY_SEPARATOR)!==0 || !is_file($full) || !is_readable($full)) { return new WP_Error('missing_source','Whitelisted source unavailable.'); }
    $raw=file_get_contents($full);
    $offset=max(0,(int)($input['offset'] ?? 0));
    $length=min(20000,max(1,(int)($input['length'] ?? 20000)));
    return array('path'=>'wp-content/plugins/'.$slug.'/'.$path,'sha256'=>hash('sha256',$raw),'bytes'=>strlen($raw),'offset'=>$offset,'content'=>substr($raw,$offset,$length));
}
add_action('wp_abilities_api_init',function(){
    if(!function_exists('wp_register_ability')) { return; }
    wp_register_ability('madagaskar/stage4-source-reader',array(
        'label'=>'Temporary Stage 4 source reader','description'=>'Admin-only code hashes/source pages. No data writes.',
        'category'=>'madagaskar-saglik',
        'input_schema'=>array('type'=>'object','properties'=>array('mode'=>array('type'=>'string','enum'=>array('hashes','source')),'slug'=>array('type'=>'string'),'path'=>array('type'=>'string'),'offset'=>array('type'=>'integer'),'length'=>array('type'=>'integer')),'required'=>array('mode','slug')),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_stage4_source_read',
        'permission_callback'=>function(){return current_user_can('manage_options');},
        'meta'=>array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'show_in_rest'=>true)
    ));
});
