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
var_assign = re.compile(
    r"\$([A-Za-z_][A-Za-z0-9_]*(?:token|secret|api[_-]?key|access[_-]?token)[A-Za-z0-9_]*)\s*=\s*['\"]([A-Za-z0-9._-]{20,})['\"]",
    re.I,
)
const_assign = re.compile(
    r"\b(?:const|define\s*\(\s*['\"])([A-Za-z_][A-Za-z0-9_]*(?:token|secret|api[_-]?key)[A-Za-z0-9_]*)[^=,]*[,=]\s*['\"]([A-Za-z0-9._-]{20,})['\"]",
    re.I,
)

def is_placeholder(value: str) -> bool:
    upper = value.upper()
    return any(word in upper for word in PLACEHOLDER_WORDS)

def looks_like_option_key(name: str) -> bool:
    n = name.lower()
    return any(part in n for part in ("option", "opt_", "_opt", "_key", "key_", "meta_", "_meta", "field_", "_field", "_name"))

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

        # Bounded audit: only current Kommo-related source.
        if "kommo" not in str(path).lower() and "kommo" not in text.lower():
            continue

        for match in bearer.finditer(text):
            value = match.group(1)
            if is_placeholder(value):
                continue
            line = text.count("\n", 0, match.start()) + 1
            findings.append((str(path), line, "bearer_literal"))

        for label, regex in (("secret_variable_assignment", var_assign), ("secret_constant_assignment", const_assign)):
            for match in regex.finditer(text):
                name, value = match.group(1), match.group(2)
                if looks_like_option_key(name) or is_placeholder(value):
                    continue
                line = text.count("\n", 0, match.start()) + 1
                findings.append((str(path), line, label))

if findings:
    for path, line, label in findings:
        print(f"{path}:{line}: {label}")
    raise SystemExit("Potential committed Kommo secret literal(s) detected. Values intentionally not printed.")

print("OK: no fixed Kommo credential literals detected in bounded source roots.")
