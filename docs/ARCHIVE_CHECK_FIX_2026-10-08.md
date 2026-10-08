# Production Source Archive kontrol düzeltmesi — 8 Ekim 2026

Okul 1.8.4 PR #201 başarısız bildiriminin nedeni arşiv kontrolünün güncel bilet dosyasını 4 Ekim parmak iziyle karşılaştırmasıydı. Aynı hata sonraki finans PR #202 kontrolünü de etkiledi. Canlı okul 1.8.4 ve MMC 1.3.48 aktif; okul/öğrenci ekranları önceki incelemede açıldı.

Üretim eklentilerinin tarihsel içerikleri, tüm ilgili manifest parmak izlerinin eşleştiği sabit reconciliation commit `3fbac3f58df2a676c1abad96bae57c48a336e8b7` üzerinden okunur. Ayrı arşiv dosyaları mevcut konumlarından doğrulanmaya devam eder. Manifestteki hiçbir SHA256 yenisiyle değiştirilmez; eksik commit veya hash hatası kontrolü durdurur. GitHub checkout tam geçmiş alır. Mevcut aile/AI/okul sürümleri tarihi minimumdan düşük olmamalıdır; yeni sürümler kabul edilir. Güncel okul ve PHP testleri kendi workflow'larında devam eder.

Yerel doğrulama: 66 dosya SHA256, 58 PHP lint, 43 aktif snippet kontrolü geçti. İki regresyon testi yeni kaynak değişiminin tarihsel arşivi bozmamasını, yanlış hash ve arşiv dosyası değişiminin reddedilmesini, yükseltme kabulünü ve sürüm düşürme reddini doğruladı. Okul 1.8.4 workflow sözleşmesi geçti. Canlı WordPress/finans/okul verisinde değişiklik yok. CI sonucu PR'da takip edilir.
