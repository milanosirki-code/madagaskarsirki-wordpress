<?php
$src=file_get_contents(__DIR__.'/../../docs/code-snippets/mdg-corporate-campaigns.php');
function must2($ok,$label){if(!$ok){fwrite(STDERR,"FAIL $label\n");exit(1);}echo "PASS $label\n";}
must2(str_contains($src,'schema_version'),'schema v2 marker exists');
must2(str_contains($src,"'cities'=>array") || str_contains($src,"['cities']"),'cities map canonical');
must2(str_contains($src,'unset($row') && str_contains($src,"['province']") && str_contains($src,"['pricing']"),'legacy top-level province/pricing removed after adapt');
must2(str_contains($src,"'legacy'=>true"),'legacy migration marker');
must2(str_contains($src,'city_add'),'city add operation');
must2(str_contains($src,'city_edit'),'city edit operation');
must2(str_contains($src,'city_toggle'),'city toggle operation');
must2(str_contains($src,'Bir kampanya koduna birden fazla il bağlayabilirsiniz.'),'admin explains multi-province behavior');
must2(str_contains($src,'+ İl Ekle'),'admin add-city control');
must2(str_contains($src,'İl fiyatlarını kaydet'),'per-city price edit');
must2(str_contains($src,'$campaign') && str_contains($src,"['cities']") && str_contains($src,'$city_key'),'catalogue scopes events by configured city');
must2(str_contains($src,'$city') && str_contains($src,"['pricing']"),'pricing is city-scoped');
must2(str_contains($src,'$effective=min($regular,(float)$pricing[$campaign_key]);'),'campaign price capped by live regular price');
must2(str_contains($src,'update_option(self::OPTION,$rows,false);'),'writes remain through campaign option');
echo "All multi-province admin/source contract checks passed.\n";
