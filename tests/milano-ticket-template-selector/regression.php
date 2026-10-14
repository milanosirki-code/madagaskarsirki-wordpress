<?php
declare(strict_types=1);
define('ABSPATH', __DIR__);
$meta = [
  6058 => ['tc_designer_template_id'=>28, '_ticket_template'=>'d_28'],
  6062 => ['tc_designer_template_id'=>28, '_ticket_template'=>'d_28'],
];
function absint($v): int {return abs((int)$v);}
function get_post_meta($id,$key,$single=true) {global $meta; return $meta[$id][$key]??'';}
function update_post_meta($id,$key,$value) {global $meta; $meta[$id][$key]=$value;return true;}
function delete_post_meta($id,$key) {global $meta;unset($meta[$id][$key]);return true;}
function tickera_ticket_designer_save_template_value($id,$value,$key) {
    // Models a helper that changes only Designer metadata, as observed on Yalova staging.
    update_post_meta($id,'tc_designer_template_id',(int)substr($value,2));
}
require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-ticket-template-qr-binder.php';
$c = new ReflectionClass('MDG_Ticket_Template_QR_Binder');
$save = $c->getMethod('save_template_selection');
$restore = $c->getMethod('restore_template_selection');
foreach ([6058,6062] as $id) {
    $save->invoke(null,$id,29);
    if (($meta[$id]['tc_designer_template_id']??null)!==29
         || ($meta[$id]['_ticket_template']??null)!=='d_29') {
        fwrite(STDERR,"FAIL: selector mismatch for #$id\n");exit(1);
    }
}
$restore->invoke(null,6062,['designer'=>'28','classic'=>'d_28']);
if ($meta[6062]['tc_designer_template_id']!==28 || $meta[6062]['_ticket_template']!=='d_28') {
   fwrite(STDERR,"FAIL: rollback selection\n");exit(1);
}
$save->invoke(null,6058,0);
if ($meta[6058]['tc_designer_template_id']!==29 || $meta[6058]['_ticket_template']!=='d_29') {
   fwrite(STDERR,"FAIL: empty template must not overwrite selected clone\n");exit(1);
}
if (count($meta)!==2) {fwrite(STDERR,"FAIL: unrelated products touched\n");exit(1);}
echo "PASS: selector sync, helper mismatch repair, rollback, zero-id fail-closed; zero production writes.\n";
