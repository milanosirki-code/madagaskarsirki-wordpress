<?php
/** Milano presentation fields v3. Stored ticket data and all original fields stay unchanged. */
if (!defined('ABSPATH')) { exit; }
function milano_pdf_layout_fields_v3($data) {
    if (!is_array($data) || !isset($data['event_name']) || strpos($data['event_name'],'Milano Sirki')!==0) return $data;
    $raw=(string)($data['event_terms']??'');
    $parts=preg_split('/(?=Bilgi\s*&\s*Destek\s*WhatsApp|Koltuk\s+numarası|Lütfen\s+seans)/u',$raw,2);
    $data['milano_layout_address']=trim($parts[0]);
    $notes=isset($parts[1])?$parts[1]:'';
    // Whitespace-only presentation normalization: no wording or phone numbers changed.
    $notes=preg_replace('/:\s*\n\s*(?=\+?[0-9])/u',': ',$notes);
    $data['milano_layout_notes']=preg_replace('/\n[\t ]*\n+/u',"\n",trim($notes));
    return $data;
}
foreach(array('tickera_ticket_designer_ticket_data','tickera_ticket_designer_resolved_ticket_data','tc_ticket_designer_ticket_data','tc_ticket_designer_resolved_ticket_data') as $hook) {
    add_filter($hook,'milano_pdf_layout_fields_v3',99,1);
}
