<?php
require __DIR__ . '/../../wp-content/plugins/madagaskar-web-assistant-core/includes/class-preview.php';
echo '<!doctype html><html lang="tr"><meta charset="utf-8"><body>';
echo \Madagaskar\WebAssistant\Preview::markup();
echo '<script id="mdg-wa-config" type="application/json">{"api":"https://staging-f321-milanosirki-lgajf.wpcomstaging.com/wp-json/mdg-web-assistant/v1/","origin":"https://staging-f321-milanosirki-lgajf.wpcomstaging.com","nonce":"fixture-nonce"}</script></body></html>';
