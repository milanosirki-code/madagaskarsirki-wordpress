"""Read-only POST quote smoke tests. Does not create carts, orders or tickets."""
import http.cookiejar
import html
import re
import urllib.parse
import urllib.request

PAGE = 'https://madagaskarsirki.com/kurumsal-davetiye-pilot/'
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(data=None):
    response = opener.open(urllib.request.Request(PAGE + '?age-smoke=20261005', data=urllib.parse.urlencode(data).encode() if data else None), timeout=45)
    return response.status, response.read().decode()

status, body = request()
nonce = re.search(r'name="mdg_campaign_nonce"[^>]*value="([^"]+)"', body).group(1)
cases = [
    ('one_three', 'TEST-DENIZLI', '99', '1', ['5','7','12'], '750,00'),
    ('two_three', 'TEST-DENIZLI', '99', '2', ['5','7','12'], '1.000,00'),
    ('two_five', 'TEST-DENIZLI', '99', '2', ['3','5','7','10','12'], '1.250,00'),
    ('zero_two', 'TEST-DENIZLI', '99', '1', ['0','2','3','12'], '500,00'),
    ('age_thirteen', 'TEST-DENIZLI', '99', '1', ['13','3','5','12'], '1.000,00'),
    ('izmir_extra', 'TEST-IZMIR', '88', '1', ['5','7','12'], '900,00'),
]
for label, code, session, adults, ages, expected in cases:
    pairs = [('mdg_campaign_nonce',nonce), ('mdg_campaign_code',code), ('mdg_campaign_action','quote'), ('mdg_campaign_event','10' if code=='TEST-IZMIR' else '12'), ('mdg_campaign_session',session), ('mdg_campaign_adults',adults), ('mdg_campaign_children',str(len(ages)))] + [('mdg_campaign_ages[]',age) for age in ages]
    status, body = request(pairs)
    result = re.search(r'<div class="mc-result".*?</div>',body,re.S)
    text = html.unescape(re.sub(r'<[^>]+>',' ',result.group(0))) if result else 'MISSING'
    assert status == 200 and expected in text, (label,status,text)
    print('PASS',label,expected,flush=True)
status, body = request([('mdg_campaign_nonce',nonce),('mdg_campaign_code','TEST-DENIZLI'),('mdg_campaign_action','quote'),('mdg_campaign_session','99'),('mdg_campaign_adults','1'),('mdg_campaign_children','3'),('mdg_campaign_ages[]','5')])
assert status == 200 and 'çocuk sayısı kadar yaş bilgisi' in body
print('PASS missing_age_count',flush=True)
