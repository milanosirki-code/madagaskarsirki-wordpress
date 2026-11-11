"""Offline regression checks for the exported native survey gates.

These do not emulate Kommo delivery or scheduling. No network or real contacts.
Run with the exported JSON path as the first argument.
"""
import copy
import json
import re
import sys

bot = json.load(open(sys.argv[1], encoding="utf-8"))

def condition(c, values):
    if c.get("type") == "regex":
        pattern = c["condition_value"]
        assert pattern.startswith("/") and pattern.endswith("/")
        return bool(re.search(pattern[1:-1], str(values[c["client_value"]])))
    left = values[c["term1"]]
    right = c["term2"]
    if c["operation"] == "=":
        return str(left) == right
    if c["operation"] == "!=":
        return str(left) != right
    actual = set(left if isinstance(left, list) else [str(left)])
    matched = bool(actual.intersection(right.split(",")))
    return matched if c["operation"] == "in" else not matched

def gate(step, values):
    p = bot[step]["question"][0]["params"]
    result = [condition(c, values) for c in p["conditions"]]
    return all(result) if p["logic"] == "and" else any(result)

def eligible(values):
    return gate("0", values) and gate("10", values) and gate("1", values)

base = {"lead.tags": ["WooCommerce Ödeme Başarılı"],
        "{{lead.name}}": "Order#12345", "{{lead.status_id}}": "110416827",
        "{{lead.cf.2455472}}": "", "{{lead.cf.2460854}}": "2026-10-15 17:30",
        "{{lead.cf.2460856}}": ""}
cases = [("paid processing", {}, True), ("paid won", {"{{lead.status_id}}": "142"}, True),
         ("pending", {"{{lead.status_id}}": "110416823"}, False),
         ("lost", {"{{lead.status_id}}": "143"}, False),
         ("unpaid", {"lead.tags": []}, False),
         ("missing datetime", {"{{lead.cf.2460854}}": ""}, False),
         ("legacy lead", {"{{lead.name}}": "Satış talebi"}, False),
         ("malformed order", {"{{lead.name}}": "Order#123 extra"}, False),
         ("already started", {"lead.tags": ["WooCommerce Ödeme Başarılı", "madagaskar_anket_baslatildi"]}, False),
         ("answered", {"lead.tags": ["WooCommerce Ödeme Başarılı", "anket_yanitlandi"]}, False),
         ("result exists", {"{{lead.cf.2460856}}": "Anket: Memnun"}, False)]
for value in ("1", "true", "Y"):
    cases.append(("sent checkbox " + value, {"{{lead.cf.2455472}}": value}, False))
for name, patch, expected in cases:
    values = copy.deepcopy(base)
    values.update(patch)
    assert eligible(values) is expected, name
assert "{{lead.pipeline_id}}" not in json.dumps(bot)
assert bot["12"]["question"][3]["params"]["template_id"] == 80796
assert bot["12"]["question"][3]["params"]["chat_sources"] == [{"id": 82576}]
assert bot["2"]["question"][1]["params"]["conditions"][1]["event"]["delay"] == 172800
print(str(len(cases)) + " gate regressions passed; template, channel and timeout preserved.")
