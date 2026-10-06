<?php
$src=file_get_contents(__DIR__.'/../../docs/code-snippets/mdg-corporate-campaigns.php');
function must2($ok,$label){if(!$ok){fwrite(STDERR,"FAIL $label\n");exit(1);}echo "PASS $label\n";}
must2(str_contains($src,"'schema_version']=2"),'schema v2 adaptation');
must2(str_contains($src,'$row[\'cities\']'),'cities map canonical');
must2(str_contains($src,"unset($row['province'],$row['pricing'])"),'legacy top-level province/pricing removed after adapt');
must2(str_contains($src,"'legacy'=>true"),'legacy migration marker');
must2(str_contains($src,"$op==='city_add'"),'city add operation');
must2(str_contains($src,"$op==='city_edit'"),'city edit operation');
must2(str_contains($src,"$op==='city_toggle'"),'city toggle operation');
must2(str_contains($src,'Bir kampanya koduna birden fazla il bağlayabilirsiniz.'),'admin explains multi-province behavior');
must2(str_contains($src,'+ İl Ekle'),'admin add-city control');
must2(str_contains($src,'İl fiyatlarını kaydet'),'per-city price edit');
must2(str_contains($src,'$campaign[\'cities\'][$city_key]'),'catalogue scopes events by configured city');
must2(str_contains($src,'$city[\'pricing\']'),'pricing is city-scoped');
must2(str_contains($src,'$effective=min($regular,(float)$pricing[$campaign_key]);'),'campaign price capped by live regular price');
must2(str_contains($src,'update_option(self::OPTION,$rows,false);'),'writes remain through campaign option');
echo "All multi-province admin/source contract checks passed.\n";
