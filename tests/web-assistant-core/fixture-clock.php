<?php
namespace Madagaskar\WebAssistant;
// Test-only clock: fixed contract dates remain valid on future CI executions.
function time() { return $GLOBALS['fixture_clock']; }
