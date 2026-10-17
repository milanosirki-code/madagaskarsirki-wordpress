import json
from pathlib import Path

old = json.loads(Path('kommo-survey-bot-backup-20261017.json').read_text())
new = json.loads(Path('kommo-survey-bot-runtime-20261017.json').read_text())

def walk(value):
    if isinstance(value, dict):
        yield value
        for child in value.values():
            yield from walk(child)
    elif isinstance(value, list):
        for child in value:
            yield from walk(child)

def bad_condition_shapes(bot):
    return [c for x in walk(bot) if x.get('handler') == 'conditions'
            for c in x['params']['conditions'] if not {'term1','term2','operation'} <= c.keys()]

assert len(bad_condition_shapes(old)) == 1, 'Must reproduce the mixed regex schema defect'
assert not bad_condition_shapes(new)
assert new['17']['question'][0]['handler'] == 'validations'
assert new['17']['question'][0]['params']['conditions'] == [old['0']['question'][0]['params']['conditions'][3]]
for x in walk(new):
    if x.get('handler') == 'goto':
        assert str(x['params']['step']) in new
for k in ('3','4','5','6','7','8'):
    assert old[k] == new[k], 'Reply/error routes must remain identical'
send = lambda b: next(x for x in b['12']['question'] if x.get('handler') == 'send_message')
assert send(old) == send(new)
assert new['12']['question'][0] == old['12']['question'][0], 'Reserve before sending'
assert not any(x.get('params',{}).get('name') == 'set_custom_fields' for x in new['12']['question'])
assert new['2']['question'][0] == old['2']['question'][0], 'Mark sent after send step only'
assert new['2']['question'][-1] == old['2']['question'][-1], 'Preserve 48h wait'
assert new['10']['question'][0] == old['10']['question'][0]
assert new['1']['question'][0] == old['1']['question'][0]
for k in ('0','14','15','16','17','10','1'):
    assert new[k]['question'][-1]['params']['name'] == 'add_note'
assert new['conversation'] is False
print('PASS: old schema defect reproduced; new schema, branch targets, eligibility guards, duplicate lock, approved send payload, response/error routes and 48h wait verified. This is not a Kommo runtime/delivery test.')
