#!/usr/bin/env python3
from pathlib import Path
import re

ROOTS = [
    Path("wp-content/plugins"),
    Path("docs/code-snippets"),
]

TEXT_SUFFIXES = {".php", ".txt", ".md", ".json", ".yml", ".yaml"}

PLACEHOLDER_WORDS = (
    "PLACEHOLDER",
    "REDACTED",
    "REMOVED",
    "YENI",
    "BURAYA",
    "TOKEN_DEGER",
    "EXAMPLE",
    "DUMMY",
)

bearer = re.compile(r"Bearer\s+([A-Za-z0-9._-]{20,})", re.I)
assign = re.compile(
    r"(?:TOKEN|SECRET|API[_-]?KEY|ACCESS[_-]?TOKEN)[^=\n]{0,80}=\s*['\"]([A-Za-z0-9._-]{20,})['\"]",
    re.I,
)
define = re.compile(
    r"define\s*\([^)]*(?:TOKEN|SECRET|API[_-]?KEY)[^)]*['\"]([A-Za-z0-9._-]{20,})['\"]",
    re.I,
)

findings = []

for root in ROOTS:
    if not root.exists():
        continue
    for path in root.rglob("*"):
        if not path.is_file() or path.suffix.lower() not in TEXT_SUFFIXES:
            continue
        try:
            text = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue

        for label, regex in (("bearer_literal", bearer), ("secret_assignment", assign), ("secret_define", define)):
            for match in regex.finditer(text):
                value = match.group(1)
                upper = value.upper()
                if any(word in upper for word in PLACEHOLDER_WORDS):
                    continue
                line = text.count("\n", 0, match.start()) + 1
                findings.append((str(path), line, label))

if findings:
    for path, line, label in findings:
        print(f"{path}:{line}: {label}")
    raise SystemExit("Potential committed secret literal(s) detected. Values intentionally not printed.")

print("OK: no fixed Kommo/API secret literals detected in audited source roots.")
