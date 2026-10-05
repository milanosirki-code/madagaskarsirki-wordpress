# Codex için talimat listesi — 5 Ekim 2026

**Kimden:** İşletme sahibi adına Claude. **Kime:** Codex. **Yazıldığı an:** 5 Ekim 2026, 15:10 TSİ.
**Takip:** Issue #141. Her görev bittiğinde sonucu oraya yaz ve kutusunu işaretle.
**Yerine geçtiği liste:** `docs/TODO_2026-10-04.md` bölüm A. O dosyadaki B ve C bölümleri geçerliliğini korur.
**Drive karşılığı:** `Madagaskar + USKD Codex Çalışma ve Yedek Rehberi`, "Codex için talimat listesi — 5 Ekim" bölümü.

Bu liste, işletme sahibinin 4 Ekim'de verdiği iki karara ve 5 Ekim 14:20–14:30 canlı kontrolüne dayanır. Görevleri yazıldığı sırayla yap. Bir görevin "Dur" koşulu oluşursa o görevi bırak, nedenini #141'e yaz, sıradakine geç.

## Bağlayıcı kararlar (işletme sahibi)

1. **Satış seans başladığında kapanır** (4 Ekim, 18:46). Listelerde bir seans yalnızca başlamadıysa görünür.
2. **Seans başladıktan sonra satılmış, kullanılmamış biletler başka seansa aktarılır; iade edilmez** (4 Ekim, 19:49).

## Her görev için geçerli kurallar

- `AGENTS.md` öncelik sırası geçerlidir: önce canlı satış ve ödeme akışı korunur.
- İşletme sahibinin açık talebi olmadan: iade yapma, sipariş silme veya değiştirme, müşteriye mesaj gönderme, gerçek ödeme oluşturma.
- Müşteri adı, telefonu, e-postası, bilet kodu ve token değerlerini GitHub'a veya Drive'a yazma. Sipariş numarası ve sayılar yazılabilir.
- Canlıda her değişiklikten önce yedek al, sonra geri oku ve doğrula. WordPress dosya düzenleyicisini kullanma.
- Ödeme akışına dokunan her adımdan sonra: gelecek bir etkinlikte bilet seç → sepet → ödeme sayfası → PayTR formu açılıyor mu, bak. Ödemeyi tamamlama.
- "Yaptım" deme; kanıtı yaz: ne zaman, hangi okuma, hangi sonuç.
- Geçici tanı snippet'lerini iş bitince pasife al ve pasif olduğunu geri okuyarak doğrula.

---

## T1 — Önbelleği temizle ve doğrula (hemen)

**Neden:** 5 Ekim 14:25'te `/sehirler/` yaklaşık 1 Ekim'den kalma kopyadan geliyordu: Pursaklar 1 Ekim, iptal edilen Kırıkkale 2 Ekim, Sincan 3 Ekim ve Yenimahalle 4 Ekim "SATIŞTA". Ana sayfa `?utm_…` veya `?fbclid=…` ekiyle açıldığında dünkü "Yenimahalle 4 Ekim — BUGÜN SATIŞTA" kopyası geliyordu. Ayrıntı: Issue #140.

**Yap:**

1. Temizlemeden önce şu altı adresin yanıt başlıklarını ve ilk gösteri kartını kaydet: `/`, `/?utm_source=instagram`, `/sehirler/`, `/sehirler/?x=1`, `/bilet-al/`, `/bilet-al/?x=1`. Başlıklardan önbellek katmanını ve kopyanın yaşını belirle.
2. Site önbelleğini temizle: platform kenar önbelleği ve nesne önbelleği (3 Ekim'de kullandığın yöntem).
3. Aynı altı adresi yeniden oku.

**Bitti sayılır:** Altı adresin hiçbirinde 1–4 Ekim gösterisi yok; hepsi Denizli 8 Ekim ile başlıyor; "Sakonu" yazımı yok. Katman, süre ve önce/sonra durumu #140'a yazıldı.

**Dur:** Temizlik sonrası herhangi bir adres hâlâ eski kopyayı veriyorsa T2'ye geçmeden nedenini bul.

## T2 — Liste sayfalarının eski kopyadan sunulmasını kalıcı olarak önle

**Neden:** T1 bugünü düzeltir; aynı sorun her gösteri gününden sonra tekrarlar.

**Yap:**

1. T1'deki başlık bulgusuna göre karar ver: üç liste sayfası (`/`, `/sehirler/`, `/bilet-al/`) ya hiç önbelleğe alınmamalı ya da en çok 5 dakika tutulmalı. Sorgu eki olan adresler de aynı kurala uymalı.
2. Aday snippet'i incele: `docs/code-snippets/staged-2026-10-05/ms-liste-onbellek-v1.php.txt`. Bu üç sayfada `DONOTCACHEPAGE` tanımlar, `nocache_headers()` gönderir ve `<head>` içine `<meta name="ms-sayfa-uretim" content="…">` ile üretim anını yazar. Ödeme akışına dokunmaz.
3. Uygunsa etkinleştir. Kenar önbelleği bu başlıklara uymuyorsa platform ayarıyla ya da uygun başlıkla çöz; meta etiketini yine de bırak.
4. Doğrula: her adresi 2 dakika arayla iki kez oku; `ms-sayfa-uretim` değeri ilerlemeli. Sorgu ekli adreslerde de ilerlemeli.
5. Ana sayfanın yanıt süresini önce ve sonra ölç; belirgin yavaşlama varsa 5 dakikalık süreye geç ve nedenini yaz.

**Bitti sayılır:** Altı adresin hepsinde üretim anı güncel; bir sonraki gösteri gününde (Denizli 8 Ekim) son seans başladıktan en geç 5 dakika sonra kart listelerden kalkıyor. Snippet canlıya alındıysa `docs/code-snippets/production/` ve envanter güncellendi. #140 kapatıldı.

**Dur:** Etkinleştirme sonrası herhangi bir sayfa hata verirse snippet'i pasife al.

## T3 — Seans denetimini çalıştır

**Neden:** Hangi bitmiş gösterinin hâlâ satın alınabildiği ve seans başladıktan sonra ödenmiş sipariş olup olmadığı bilinmiyor.

**Yap:**

1. `docs/code-snippets/staged-2026-10-04/ms-seans-denetimi-v3.php.txt` gövdesini geçici bir snippet olarak ekle ve etkinleştir. Salt okunurdur: üç `SELECT`, sipariş başına bir bilet listesi okuması.
2. Araçlar > Seans Denetimi ekranını `?gun=14` ile aç.
3. İlk kontrol: girişte okutulduğunu bildiğin bir siparişte "Okutulan" sütunu doğru mu? Değilse `tc_checkins` biçimini canlıda oku, farkı #141'e yaz ve bölüm 3'ün okutulma sütunlarına güvenme.
4. Şunları #116'ya yaz: bölüm 1 sayısı ve etkinlik numaraları; bölüm 2'de "EVET" görünen ürün numaraları; bölüm 3'te ödenmiş geç sipariş sayısı, "AKTARIM ADAYI" sipariş numaraları ve bilet sayıları, "elle incele" sipariş numaraları.
5. Snippet'i pasife al.

**Bitti sayılır:** Sayılar ve sipariş numaraları #116'da; snippet pasif.

## T4 — Biten gösterilerin satışını kapat

**Neden:** 5 Ekim 14:20'de Sincan 3 Ekim ve Yenimahalle 4 Ekim etkinlik sayfaları "Satışta" yazıyor ve bilet seçtiriyordu.

**Yap:**

1. T3 bölüm 2'de "EVET" görünen her ürünü mevcut V4 satış kapatma ekranıyla kapat. En az: Sincan 3 Ekim ve Yenimahalle 4 Ekim seans ürünleri.
2. Gelecekteki hiçbir gösterinin ürününe dokunma.
3. T3 ekranını yeniden aç: o ürünler "hayır" görünmeli.

**Bitti sayılır:** Kapatılan ürün numaraları #116'da; etkinlik sayfalarında bilet seçilip sepete eklenmek istendiğinde reddediliyor.

**Not:** T5 canlıya alınana kadar her gösteri gününün sonunda bu görev tekrarlanır.

## T5 — Kalıcı düzeltmeyi canlıya al (PR #131)

**Neden:** Karar 1'in uygulaması. PR #131, PR #117'deki değişikliği güncel main üzerine taşıyor ve WooCommerce'in kendi sepet yolları için koruma ekliyor; CI başarılı, canlıda değil.

**Yap:**

1. Canlıdaki beş eklenti dosyasının (`class-mdg-sessions.php`, `class-mdg-public-event.php`, `class-mdg-live-sales.php`, `class-mdg-public-cities.php`, `class-mdg-public-tickets.php`) hash'ini main ile karşılaştır. Eşleşmiyorsa dur.
2. Sırayla yükle: **önce `class-mdg-sessions.php`**, sonra diğer dördü. Ters sırada etkinlik sayfası hata verir.
3. Snippet #30'u güncelle: iki koşul `end_at >= %s` → `start_at > %s`. Önce canlı gövdenin `docs/code-snippets/production/snippet-030.php.txt` ile aynı olduğunu doğrula.
4. Smoke test:
   - Gelecek etkinlik (Denizli 8 Ekim): bütün seanslar görünüyor; bilet seç → sepet → ödeme → PayTR formu açılıyor.
   - Bitmiş etkinlik (Sincan 3 Ekim): "Bu gösterinin bilet satışı sona erdi" bildirimi var; seçici ve sepet düğmesi yok.
   - Başlamış seansın `/urun/…` sayfasından sepete ekleme reddediliyor.
   - Yönetici taslak önizlemesi açılıyor.
   - Sistem sağlığı 16/16; PHP hata günlüğünde yeni satır yok.
5. PR #131'i birleştir. PR #117'yi "PR #131 ile karşılandı" notuyla kapat. `docs/code-snippets/production/` ve envanteri güncelle.

**Bitti sayılır:** Smoke test sonuçları #116'da; #116 kapatıldı; #117 kapalı.

**Dur:** Gelecek etkinlikte PayTR formu açılmıyorsa beş dosyayı hemen önceki haline döndür.

**T5 bugün yapılamıyorsa:** `docs/code-snippets/staged-2026-10-04/ms-seans-satis-kilidi-v2.php.txt` geçici köprüsünü etkinleştir (aynı klasördeki README'de 7 adımlık smoke test var). T5 tamamlanınca köprüyü pasife al.

## T6 — Aktarım adaylarını bildir; aktarım yapma

**Neden:** Karar 2. Kaç aday olduğu T3'ten çıkacak.

**Yap:**

1. T3'teki "AKTARIM ADAYI" sipariş numaralarını ve bilet sayılarını #116'da ayrı bir başlık altında listele. Aday yoksa "aday yok" yaz; görev biter.
2. Aday varsa: işletme sahibinin onayını ve müşterinin seçtiği hedef seansı bekle. Bunlar gelmeden hiçbir siparişe, bilete veya kapasiteye dokunma.
3. Müşteriye mesaj gönderme. Mesajı ekip gönderir; taslak `docs/TODO_2026-10-04.md` madde A8'dedir.
4. Onay geldiğinde tek sipariş aktarımını A8'deki koşullarla yap: önce dry-run, V4 test biletinde deneme, snapshot ve geri alma. V4 "Erteleme / Aktarım" ekranını bu iş için kullanma; o ekran etkinliğin bütün biletlerini taşır.

**Bitti sayılır:** Aday listesi (ya da "aday yok") #116'da.

## T7 — Kurumsal kampanyayı güvenceye al

**Neden:** `/kampanya/` yayında; snippet #124 ve #125 aktif; ücretsiz çocuk bileti satırları canlı ödeme akışına giriyor. Kod sekiz taslak PR'da (#132–#139) ve main'de yok. PR #136'ya göre gerçek banka ödemesi ve kapıda QR okutma test edilmedi.

**Yap:**

1. İşletme sahibi açıkça onaylayana kadar gerçek kurum kodu tanımlama. Şu an tanımlı gerçek kod var mı, #141'e yaz.
2. Sekiz PR'ı tek, incelenebilir bir PR'da topla ya da sırayla birleştir; canlıdaki #124 ve #125 gövdelerinin main ile birebir aynı olduğunu hash'le doğrula. `docs/code-snippets/production/` ve envanteri güncelle.
3. Kampanya sepetinde Karar 1'i doğrula: başlamış seans kampanya sayfasında listelenmiyor ve sepete eklenemiyor. T5 canlıya alındıktan sonra yeniden dene.
4. Şunları test planı olarak yaz ve işletme sahibinin onayına sun; onaysız yapma: bir gerçek kampanya siparişinin ödenmesi, biletlerin üretilmesi, ücretsiz çocuk biletinin kapıda okutulması, kısmi ve tam iade.
5. Kötüye kullanım kontrollerini gözden geçir ve sonucu yaz: yetişkin satırı sepetten çıkarılınca ücretsiz satırlar düşüyor mu; adetler elle değiştirilince fiyat geri geliyor mu; kupon ücretsiz satırla birleşiyor mu; aynı kod sınırsız kullanılabiliyor mu ve bu isteniyor mu.
6. `/kampanya/` sayfasının önbelleğe alınmadığını T1'deki yöntemle doğrula (formda nonce var; eski kopya formu bozar).

**Bitti sayılır:** Kod main'de; canlı gövdeler main ile eşleşiyor; test planı ve kötüye kullanım kontrolü sonuçları #141'de; gerçek kod tanımlanmadı ya da tanımlandıysa işletme sahibinin onayı kayıtlı.

## T8 — Kayıtları güncelle

**Yap:**

1. Drive rehberine 4–5 Ekim çalışmanı özetle: brüt gelir düzeltmesi, salt okunur düzeltmeleri, Kommo kaynağı, çekiliş yedek çekimi, kurumsal kampanya, PR #131. Rehberdeki son Codex kaydı 3 Ekim 15:54.
2. PR'ları toparla: #111 birleştir; #106 kapat (Kırıkkale geçti, kök neden #108 ile çözüldü); #107 arşiv notuyla kapat; #125 sonuçlandır; #45, #43, #38, #1 için güncel main ile karşılaştırıp kapat ya da yenile.
3. `docs/TODO_2026-10-04.md` içinde tamamlanan B maddelerini işaretle.

**Bitti sayılır:** Açık PR sayısı ve her birinin neden açık olduğu #141'de.

## T9 — İşletme sahibinin onayını bekleyen işleri hazırla; uygulama

Aşağıdakiler için yalnızca hazırlık yap ve onay iste:

- **Issue #124:** `wp_mad_okul_programlar` satır 7 için tek alanlık değişiklik (`mmc_program_id` NULL → 9). Önerdiğin farkı ve geri alma adımını yaz.
- **Issue #82:** Kommo tarafındaki doğrulama yetkili tarayıcı gerektiriyor; işletme sahibinden ne istediğini adım adım yaz.
- **Canlıya alınmayı bekleyen sürümler:** Aile Paketi 1.1.3 ve AI Abilities 0.7.0 için değişiklik özeti ve smoke test planı.
- **Tickera Bridge 1.7.8 ve Phone Number Validation 1.10.1:** ödeme akışındadır; güncelleme planı ve geri alma adımı.

**Bitti sayılır:** Dört başlık için onay isteği #141'de; hiçbir canlı değişiklik yapılmadı.

## T10 — Düşük öncelik

- 26 Eylül ürün sayfası (`/urun/madagaskar-sirki-ankara-26-eylul-2026-1200-bileti/`) doğrudan adresle açılıyor ve fiyat gösteriyor. Satışı kapalı ürün sayfalarını `/bilet-al/` adresine yönlendir.
- Issue #105: ödeme hatırlatması deneme kaydına V4 ile kapatılmış ürün ve başlamış seans kontrolünü ekle (T5'ten sonra `MDG_Sessions::sales_closed_by_time()` kullanılabilir). Gönderim kapalı kalacak.
- Issue #87: kalan `end_at` analizi; bitince kapat.
- Kommo sohbet butonu: 1.3.3'ten yeni sürüm çıkınca güncelle.

---

## İşletme sahibinden beklenenler (Codex yapmaz)

- T6: aday listesi çıkarsa onay; müşterilerin seçtiği seanslar; fiyat farkı kararı.
- T7: gerçek kurum kodu tanımlama onayı; gerçek ödeme ve kapı testi onayı.
- T9: dört onay.
- Ödeme hatırlatması: Kommo otomasyon ekranı ve karttaki telefon bölümünün görüntüsü, "Ödeme Linki" alanı, mesaj metni onayı.
- `docs/TODO_2026-10-04.md` bölüm C'deki tasarım kararları.

## Hazır kaynaklar

| Kaynak | Ne için |
|---|---|
| PR #131 | T5 |
| `docs/code-snippets/staged-2026-10-05/ms-liste-onbellek-v1.php.txt` | T2 |
| `docs/code-snippets/staged-2026-10-04/ms-seans-denetimi-v3.php.txt` | T3 |
| `docs/code-snippets/staged-2026-10-04/ms-seans-satis-kilidi-v2.php.txt` | T5 yapılamazsa |
| `docs/code-snippets/staged-2026-10-04/snippet-030-seans-baslayinca-v1.php.txt` | T5 adım 3 için karşılaştırma |
| `docs/TODO_2026-10-04.md` madde A8 | T6 koşulları ve mesaj taslağı |

Staged snippet'lerin hiçbiri canlıda değil ve gerçek WordPress üzerinde çalıştırılmadı; hepsi sözdizimi denetiminden ve izole mantık testinden geçti.
