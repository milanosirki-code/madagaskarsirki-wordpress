# Protokol / ücretsiz bilet menüsü — canlı doğrulama
Tarih: 6 Ekim 2026

Canlı site: https://madagaskarsirki.com
Yönetim menüsü: Madagaskar → Protokol / Ücretsiz Biletler
Yönetim adresi: https://madagaskarsirki.com/wp-admin/admin.php?page=mdg-protocol-tickets

Code Snippets 127 aktif; code_error null. Kaynak: docs/code-snippets/mdg-protocol-ticket-menu.php.
GitHub incelemesi: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/159

## Kullanım
1. Protokol veya ücretsiz bilet kategorisini ve program/seansı seçin.
2. İsimli: manuel isim listesi veya Excel.xlsx/CSV yükleyin. Excel şablonunu menüden indirin; örnek satırı değiştirin. Kolonlar ad_soyad, bolum, koltuk. İsim zorunlu; koltuk yazılıysa bölüm de zorunlu.
3. Koltuk bazlı: bölüm ve koltuk listesini girin. Örneğin A2–A11 için on ayrı satır.
4. Numarasız: adet girin.
5. Önizlemeyi kontrol edip biletleri üretin. Paket bağlantısını elle paylaşın; her kişinin ayrı yerel QR bileti/PDF bağlantısı bulunur.

Bir pakette en çok 100 kişi ve 4 seans desteklenir. Liste her seçilen seansa uygulanır. Mevcut Denizli davetiyelerine menüden ulaşılır.

## Doğrulama
- PHP sözdizimi ve 23 regresyon kontrolü başarılı.
- Canlı Excel şablonu okuma/yazma turu ve sharedStrings okuması başarılı.
- Canlı yönetim HTML'inde üç modun alan geçişleri, dosya yükleme formu, nonce ve Denizli'nin iki seansı doğrulandı.
- Güncel katalogda 8 etkinlik bulundu; Denizli seansları 99/100.
- Önceki numaralı davetiyelerle koltuk çakışması kontrolü başarılı.
- Mevcut gelecekteki satışa açık yetişkin varyasyonlarının Designer şablonları mevcut.
- Yeni deneme siparişi/bileti oluşturulmadı; gerçek kişi Excel'iyle uçtan uca üretim ve fiziksel QR okutma bu doğrulamaya dahil değil.
- Normal satış ürünleri, fiyatları ve ortak PDF şablonları değiştirilmedi.

## İşletim ve geri alma
Üretim sıfır tutarlı yerel WooCommerce siparişleri ve mevcut MDG kapasite/QR altyapısını kullanır. Tekrar üretme ve aynı seanstaki protokol koltuk çakışmaları engellenir. Salonda ayrılan yerlerin fiziksel olarak korunması ayrıca organize edilmelidir; normal satış numarasızdır.
Geri alma: yalnızca snippet 127 devre dışı bırakılır. Eski davetiye snippet 126 ve normal satış etkilenmez. Yeni menünün paket bağlantıları devre dışı kalır; yerel biletlerin iptali ayrı mevcut süreçle yapılır.
Bu kayıtta gerçek isim, QR içeriği veya özel paket anahtarı yoktur.
