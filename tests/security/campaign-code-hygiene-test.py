#!/usr/bin/env python3
import importlib.util
from pathlib import Path

path = Path(__file__).with_name('campaign-code-hygiene.py')
spec = importlib.util.spec_from_file_location('campaign_hygiene', path)
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)

# Only synthetic values; production literals are never fixtures.
sample = 'DemoCase'
import hashlib
audit.DENIED = {hashlib.sha256(sample.casefold().encode()).hexdigest()}
prefix = '/kampanya/?kod='
html_prefix = '/kampanya/?x=1&amp;' + 'kod='
cases = [
    ('literal uppercase blocked', 'value="DEMOCASE"', True),
    ('literal casefold blocked', 'value=DemoCase', True),
    ('word boundary respected', 'prefixdemocase_suffix', False),
    ('unknown link blocked', prefix + 'private-example', True),
    ('encoded link blocked', prefix + '%70rivate-example', True),
    ('html query blocked', html_prefix + 'private-example', True),
    ('demo link allowed', '/kampanya/?kod=DEMO-KURUM-A', False),
    ('test link allowed', '/kampanya/?kod=TEST-ANKARA', False),
    ('dynamic PHP link allowed', '/kampanya/?kod=<?php echo $key; ?>', False),
    ('empty link allowed', '/kampanya/?kod=', False),
    ('ordinary code allowed', 'const OPTION="mdg_corporate_campaign_codes_v1";', False),
]
for label, text, expected in cases:
    assert bool(audit.findings(text)) == expected, label
    print('PASS:', label)
print('TOTAL PASS:', len(cases))
