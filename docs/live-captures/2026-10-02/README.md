# Canlı snippet kopyaları — 2 Ekim 2026

Bu klasördeki dosyalar, 2 Ekim 2026'da işletme sahibinin Code Snippets'e yapıştırdığı kodların kopyasıdır. Code Snippets biçimindedir: başlarında `<?php` etiketi yoktur.

Ayrıntılı kayıt: `docs/CLAUDE_SITE_CHANGES_2026-10-02.md`

| Dosya | İçerik |
|---|---|
| `ms-global-alt-bilgi-v3.3.php.txt` | Alt bilgi ve çerez bildirimi (canlıdaki sürüm) |
| `snippet-35-ms-anasayfa-v3.2.php.txt` | Ana sayfa (`[ms_anasayfa_v2]`) |
| `ms-bilet-sorgulama-v1.php.txt` | Biletlerim sayfasındaki bilet sorgulama formu |
| `ms-yarim-kalan-odeme-kaydi-v1-deneme.php.txt` | Yarım kalan ödeme kaydı, deneme modu (mesaj göndermez) |
| `onceki/` | Alt bilgi ve ana sayfa snippet'lerinin değişiklikten önceki hali ve alt bilginin ara sürümü V3.2; geri alma için |
| `mantik-testleri/` | Sahte WordPress/WooCommerce fonksiyonlarıyla çalışan mantık testleri |

## Mantık testlerini çalıştırma

Testler gerçek WordPress gerektirmez; yalnızca karar mantığını ve HTML çıktısını dener. Geçici bir klasörde:

```bash
mkdir t && cd t
for f in ms-bilet-sorgulama-v1 ms-yarim-kalan-odeme-kaydi-v1-deneme; do
  (echo "<?php"; cat ../$f.php.txt) > $f.php
done
(echo "<?php"; cat ../snippet-35-ms-anasayfa-v3.2.php.txt) > home.php
cp ../mantik-testleri/stubs.php.txt stubs.php
cp ../mantik-testleri/k1-bilet-sorgulama-test.php.txt k1run.php
cp ../mantik-testleri/k2-odeme-kaydi-test.php.txt k2run.php
cp ../mantik-testleri/anasayfa-test.php.txt hometest.php

php k2run.php
php hometest.php
T_FILE=$(mktemp) php k1run.php '{"post":{"ms_bilet_sorgu":"1","ms_bs_siparis":"3735","ms_bs_telefon":"0506 034 38 74"}}'
```

`k1run.php` her çağrıda tek bir form gönderimini dener; `T_FILE` deneme sayaçlarının tutulduğu dosyadır. Testlerdeki sipariş numaraları ve telefonlar uydurmadır.
