<?php
$src=file_get_contents(__DIR__.'/../../docs/code-snippets/mdg-corporate-campaigns.php');
function must($ok,$label){if(!$ok){fwrite(STDERR,"FAIL $label\n");exit(1);}echo "PASS $label\n";}
must(str_contains($src,'Programdan otomatik'),'normal price is live-program derived in admin');
must(str_contains($src,'name="adult_campaign"'),'adult campaign price field');
must(str_contains($src,'name="child_campaign"'),'child campaign price field');
must(!str_contains($src,'name="adult_regular"'),'no manual adult regular price field');
must(!str_contains($src,'name="child_regular"'),'no manual child regular price field');
must(str_contains($src,"'adult_campaign'=>self::price_input"),'adult campaign validation');
must(str_contains($src,"'child_campaign'=>self::price_input"),'child campaign validation');
must(str_contains($src,'$effective=min($regular,(float)$pricing[$campaign_key]);'),'campaign cannot exceed live regular price');
must(str_contains($src,'mdg_corporate_campaign_update'),'explicit campaign update action');
must(str_contains($src,'mdg_corporate_campaign_save'),'explicit campaign create action');
must(str_contains($src,'placeholder="Örn. demo-okul-c"'),'demo-okul-c operator workflow visible');
must(str_contains($src,'Ücretsiz çocuk'),'free-child policy remains represented');
echo "All live campaign price-menu contract checks passed.\n";
