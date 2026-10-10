#!/usr/bin/env python3
"""Bounded source audit. Never echo matched campaign values."""
from pathlib import Path
import hashlib
import json
import re
import subprocess
import sys
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[2]
DENIED = set(json.loads(Path(__file__).with_name('campaign-code-denyhashes.json').read_text())['sha256'])
TOKEN = re.compile(r'[\w-]+', re.UNICODE)
CODE_LINK = re.compile(r'(?:[?&]|&amp;)(?:kod|mdg_campaign_code)=([^\s\"\'<>`&#]*)', re.I)

def findings(text):
    found = set()
    for match in TOKEN.finditer(text):
        if hashlib.sha256(match.group().casefold().encode()).hexdigest() in DENIED:
            found.add((text.count('\n', 0, match.start()) + 1, 'exposed_campaign_literal'))
    for match in CODE_LINK.finditer(text):
        value = unquote(match.group(1)).casefold()
        if value and not value.startswith(('test-', 'demo-', '$', '{')):
            found.add((text.count('\n', 0, match.start()) + 1, 'non_demo_campaign_link'))
    return sorted(found)

def main():
    # Track the complete current tree, including docs and tests; ignore Git history.
    files = subprocess.check_output(['git', '-C', str(ROOT), 'ls-files', '-z']).decode().split('\0')
    count = 0
    failed = False
    for name in files:
        path = ROOT / name
        if not name or not path.is_file():
            continue
        try:
            text = path.read_text(encoding='utf-8')
        except (UnicodeError, OSError):
            continue
        count += 1
        for line, reason in findings(text):
            print(f'{name}:{line}: {reason} (value redacted)')
            failed = True
    if failed:
        print('FAIL: private campaign material found; values are not printed.')
        return 1
    print(f'OK: {count} current text files scanned; no known exposed code or non-demo code link.')
    return 0

if __name__ == '__main__':
    sys.exit(main())
