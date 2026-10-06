<?php
if(!defined('ABSPATH'))exit;
if(!class_exists('MDG_Protocol_PDF',false)){
 final class MDG_Protocol_PDF {
  static function diagnostics(){
   $out=array();foreach(array('TC_Ticket_Designer_Template','TC_Ticket_Designer_PDF_Generator') as $name){$c=MDG_Ticket_Session_Datetime::designer_class($name);$r=new ReflectionClass($c);$file=$r->getFileName();$lines=file($file);$out[$name]=array('methods'=>array_map(function($m){return $m->getName();},$r->getMethods()),'source'=>implode('',array_slice($lines,$r->getStartLine()-1,$r->getEndLine()-$r->getStartLine()+1)));}return $out;
  }
 }
 add_action('rest_api_init',function(){register_rest_route('mdg-protocol-pdf/v1','/diagnostics',array('methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_woocommerce');},'callback'=>array('MDG_Protocol_PDF','diagnostics')));});
}
