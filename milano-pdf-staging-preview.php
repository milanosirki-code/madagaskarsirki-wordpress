<?php
/**
 * Plugin Name: Milano PDF Isolated Staging Preview
 * Description: Administrator-only in-memory PDF layout tests. No production execution.
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) { exit; }
if (strpos(home_url(),'staging-f321-milanosirki-lgajf.wpcomstaging.com')===false) { return; }
require_once __DIR__.'/integrations/milano/pdf-layout-20261018/milano-layout-fields.php';
require_once __DIR__.'/integrations/milano/pdf-layout-20261018/staging-preview.php';
