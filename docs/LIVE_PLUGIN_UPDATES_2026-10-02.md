# Canlı Eklenti Güncellemeleri ve Sürüm Farkı — 2 Ekim 2026

**Kapsam:** `madagaskarsirki.com` (WordPress.com Atomic, blog ID `255726534`).
**Yapan:** Claude, WordPress.com MCP bağlantısıyla, işletme sahibinin onayıyla ("hepsini yap").
**Yöntem:** Her eklenti tek tek güncellendi; toplu güncelleme kullanılmadı. Repo kodunda değişiklik yoktur.

## 1. Başlangıç durumu (2 Ekim, güncellemeden önce)

- Jetpack Monitor: site **up** (son durum değişikliği 29.09.2026 17:39).
- Jetpack Backup: aktif; son başarılı yedek **01.10.2026 22:55** (63 yedek). Geri alma noktası budur.
- Eklentiler: 123 kurulu, 57 aktif, 66 pasif, 11 güncelleme bekliyor.
- Jetpack Scan: 1 açık tehdit — `website-chat-button-kommo-integration` 1.3.2, "Missing Authorization" (ilk tespit 09.08.2026).

## 2. Yapılan güncellemeler

| Eklenti | Önceki | Yeni | Durum | Not |
|---|---|---|---|---|
| Website Chat Button: Kommo integration | 1.3.2 | 1.3.3 | aktif | Scan tehdidinin düzeltmesi |
| Site Kit by Google | 1.187.0 | 1.188.0 | aktif | |
| AI Provider for Google | 1.1.0 | 1.2.0 | aktif | |
| AI Provider for OpenAI | 1.0.3 | 1.2.0 | aktif | |
| FluentCRM | 3.1.10 | 3.2.5 | aktif | |
| WPVibe | 1.19.0 | 1.20.0 | aktif | |
| Reddit for WooCommerce | 1.0.4 | 1.0.6 | pasif | |
| Snapchat for WooCommerce | 1.0.3 | 1.0.6 | pasif | |

Güncellemelerden sonra Jetpack Monitor siteyi yine **up** gösterdi. Yeni Jetpack Scan 02.10.2026 12:57 UTC'de kuyruğa alındı.

**Kommo tehdidi kapanmadı.** Scan, 1.3.2 kaydını "fixed" olarak işaretledi (12:51 UTC), ancak aynı açığı 1.3.3 için yeniden açtı (`fixable: false`). Açıklama hâlâ "<= 1.3.1" diyor; 1.3.2 ve 1.3.3 bu aralıkta değil. Bu, güvenlik açığı veritabanındaki sürüm aralığının hatalı olduğunu düşündürüyor, ama doğrulanmadı. Daha yeni bir sürüm çıkana kadar yapılabilecek bir güncelleme yok. Kesin çözüm, eklentiyi pasifleştirip chat butonunu kaldırmak olur; bu karar işletme sahibine aittir.

## 3. Bilerek yapılmayanlar

| Eklenti | Bekleyen | Neden |
|---|---|---|
| Tickera Bridge for WooCommerce | 1.7.7 → 1.7.8 | Bilet siparişi / checkout zincirinde. Bu ortamdan siteye HTTP erişimi yok; sepet → checkout → ödeme smoke testi yapılamadı (AGENTS.md öncelik 1). |
| Phone Number Validation | 1.10.0 → 1.10.1 | Checkout telefon alanını doğrular; hata checkout'u kilitleyebilir. Aynı neden. |
| WooCommerce PayPal Payments | 4.1.2 → 4.1.3 | Pasif. İşletme sahibi güncelleme isteğini reddetti. |

Bu iki checkout eklentisi, smoke test yapılabilecek bir anda güncellenmeli ve ardından sepet → checkout → PayTR ödeme sayfası açılışı denenmelidir.

## 4. Canlı ↔ repo sürüm farkı

Canlıdaki eklenti sürümleri ile bu depodaki `Version:` başlıkları:

| Eklenti | Canlı | Repo | Yorum |
|---|---|---|---|
| Madagaskar Management Center | **1.3.47** | 1.3.30 | Canlı daha yeni; 1.3.31–1.3.47 arası kod depoda yok. |
| Madagaskar Okul Tanıtım Yönetimi | **1.7.9** | 1.7.4 | Canlı daha yeni; 1.7.5–1.7.9 depoda yok. |
| Madagaskar AI Abilities | 0.6.1 | **0.7.0** | Repo daha yeni; canlıya alınmamış. |
| Madagaskar Aile Paketi 2+2 | 1.1.2 | **1.1.3** | Repo daha yeni; canlıya alınmamış. |
| Checkout Customizations, Kommo Automation, Legacy Redirects, Veri Ambarı, Ticket Session Datetime Fix | aynı | aynı | Fark yok. |

WordPress.com MCP eklenti dosyalarını okuyamadığı için canlı kod buradan alınamadı. MMC ve Okul Tanıtım'ın canlı kaynağı WPVibe veya SFTP ile çekilip depoya işlenmelidir.

## 5. Diğer gözlemler (değişiklik yapılmadı)

- İki bilet yönetimi eklentisi birlikte aktif: "Madagaskar Bilet Yönetimi" `3.6.3-ticket-invalidation-dry-run` ve "V4.0" `4.0.14-transition`.
- İki SKU hotfix'i birlikte aktif: "İlçe Bazlı SKU Hotfix" 1.0.0 ve "V2" 2.0.0.
- 66 pasif eklentinin büyük kısmı eski dry-run / denetim sürümleridir (V3.6.4–V3.9.0.4).

## 6. Geri alma

- Tek eklenti: WordPress.com eklenti ekranından önceki sürüme dönmek ya da önceki sürüm zip'ini yüklemek.
- Tüm site: Jetpack Backup ile **01.10.2026 22:55** yedeğine dönmek. Bu, o andan sonraki sipariş verisini de geri alır; yalnızca son çare olarak kullanılmalı.

## 7. İşletme sahibi kararları (2 Ekim)

- Tickera Bridge ve Phone Number Validation güncellemeleri **yapılmayacak**.
- Kommo chat butonu, Scan uyarısına rağmen **açık kalacak**.
- MMC ve Okul Tanıtım canlı kodunun repoya alınması **sonraya** bırakıldı.
- Not: Activity log'da "Invum POS Entegratör" (pasif) 1.0.9 → 1.0.13 güncellemesi "Server" aktörüyle görünüyor; Claude yapmadı.
