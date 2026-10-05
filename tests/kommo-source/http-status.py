"""Run the production endpoint callback with WordPress's initial 404 status.

Checks successful source responses and retains the hidden endpoint's token gate.
The PHP child exits through the real serve() function; no function is copied.
"""
import json
import os
from pathlib import Path
import shlex
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'docs/code-snippets/production/snippet-110.php.txt'
PHP = shlex.split(os.environ.get('TEST_PHP_COMMAND', 'php'))
STUBS = r'''<?php
define('ABSPATH', '/fixture/');
$status = 404;
register_shutdown_function(function () { global $status; fwrite(STDERR, json_encode(array('status'=>$status))); });
function add_action(...$args) {}
function get_option($key, $default=null) { return 'mdg_kommo_active_events_token' === $key ? 'fixture-token' : $default; }
function current_user_can($cap) { return false; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return $value; }
function wp_parse_url($url, $part) { return parse_url($url, $part); }
function status_header($code) { global $status; $status=$code; }
function nocache_headers() {}
function wp_date($format, $time=null, $tz=null) { return gmdate($format, $time ?? time()); }
function wp_timezone() { return new DateTimeZone('Europe/Istanbul'); }
function esc_html($value) { return htmlspecialchars($value); }
$_SERVER['REQUEST_URI'] = '/kommo-ai-bilgi-merkezi/';
$_GET = array('token'=>$argv[2]);
if ('other' === $argv[3]) { $_SERVER['REQUEST_URI']='/ordinary-page/'; }
eval(file_get_contents($argv[1]));
mdg_kommo_unified_v2_serve();
'''

with tempfile.TemporaryDirectory() as tmp:
    script = Path(tmp) / 'endpoint.php'
    script.write_text(STUBS)
    for token, path, expected, has_body in [
        ('fixture-token', 'source', 200, True),
        ('wrong-token', 'source', 404, False),
        ('', 'source', 404, False),
        ('fixture-token', 'other', 404, False),
    ]:
        result = subprocess.run(PHP + [str(script), str(SOURCE), token, path], capture_output=True, text=True)
        assert result.returncode == 0, result.stderr
        assert json.loads(result.stderr)['status'] == expected, result.stderr
        assert ('Aktif program bulunamadı.' in result.stdout) == has_body
        assert ('<html' in result.stdout) == has_body
print('PASS: authorized source is HTTP 200; invalid/missing token stays 404; unrelated route is untouched (4 cases).')
