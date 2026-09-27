# Madagaskar Sirki - Kommo AI Aktif Kaynak Devir Notu

Tarih: 27 Eylul 2026

Bu dosya, Madagaskar Sirki web sitesi, Kommo AI bilgi kaynaklari ve bilet PDF/rota calismalari icin teknik devir notudur. Amac, sohbet siniri doldugunda yeni sohbette bastan baslamadan mevcut durumu, calisan yontemi, denenen ama calismayan yollari ve yazilan kodlari hizlica devam ettirebilmektir.

## 1. Son Durum

- WordPress tarafinda calisan ana snippet: `Madagaskar Kommo Active Events Unified Source`
- Code Snippets ID: `70`
- WordPress admin kontrol sayfasi: `wp-admin/admin.php?page=mdg-kommo-active-events-source&refresh=1`
- Gizli kaynak sayfa basligi: `MMC | Kommo AI Bilgi Merkezi`
- Kommo URL kaynagi: `#1334640` - `MMC | Aktif Satistaki Etkinlikler`
- Kommo kisa metin kaynagi: `#1334930` - `MMC | Aktif Programlar | Global Kisa ...`
- Son senkron: `2026-09-27 08:05:54`
- Aktif satis kaynagi 11 etkinlik uretiyor.

Not: Gizli tokenli kaynak URL bu devir notuna bilerek yazilmadi. Token gerekirse WordPress admin kontrol sayfasindan gorulmelidir.

## 2. Calisan Mimari

Kommo icin tek dogru program kaynagi su sekilde kuruldu:

1. WordPress aktif satis etkinliklerini toplar.
2. Tokenli gizli HTML kaynak sayfasi uretir.
3. Kommo'da URL kaynagi olarak `MMC | Aktif Satistaki Etkinlikler` bulunur.
4. Kommo AI'nin daha guvenilir kullanmasi icin ayrica kisa metin kaynagi olusturulur.
5. AI temsilcisi icin kritik cevaplar kisa metin kaynaginin en ustune yazilir.

Kisa metin kaynagindaki oncelik kurallari:

- Mamak sorulursa `10 Ekim 2026` programi cevaplanmali.
- Usak ve `15 Ekim` birlikte sorulursa Usak programi cevaplanmali.
- `2 Ekim` sorulursa Kirikkale programi cevaplanmali.
- Aktif kaynakta etkinlik varken `program yayimlanmadi` denmemeli.
- Musteri once sehir, sonra tarih yazarsa ayni konusma baglami korunmali.

## 3. Neden PDF Kaynagina Donulmedi

PDF kaynak program icin dogru ana kaynak degil. Programlar, seanslar, salonlar, bilet linkleri ve Google Maps linkleri sik degisebiliyor. PDF sabit kaldiginda Kommo eski bilgiyi secip yanlis cevap verebiliyor.

PDF ve `Temel Bilgiler` kaynaklari yalnizca genel kurallar icin kullanilmali:

- Ucretsiz davetiye bilgisi
- Yas kurallari
- Kapida satis bilgisi
- Gosterinin genel suresi
- Genel cevaplama uslubu

Program bilgisi icin ana kaynak WordPress'in urettiigi aktif program kaynagi olmalidir.

## 4. Denenen Ama Sorun Cikaran Yontemler

| Yontem | Sonuc |
| --- | --- |
| Her etkinlik icin ayri Kommo kaynagi | Eski ve yeni kaynaklar birbirine karisti, bot bazen eski kaynagi secip `program yayimlanmadi` dedi. |
| Tek PDF kaynakta program tutmak | Program degistikce PDF guncelleme gerektiriyor, eski bilgi riski yuksek. |
| Sadece URL kaynagi kullanmak | URL sayfasi dogru olsa bile AI temsilcisi bazen bu kaynagi onceliklendirmedi. |
| Kommo URL kaynagini API ile guncellemek | `PATCH/PUT` istekleri 404 dondu; mevcut URL source Kommo API tarafinda guncellenemedi. |
| `available_functions = ['agent', 'suggested_reply']` | Kommo API 400 hata verdi: `This collection should contain exactly 1 element.` |
| Browser icinde editor DOM degerini degistirmek | CodeMirror/editor yapisi sebebiyle textarea/form degerleri dogrudan degismedi. Meta+A ve Meta+V ile kod degisimi yapildi. |

## 5. Kommo Icin Dogru Ayar

Kommo bilgi kaynagi olustururken `available_functions` tek elemanli olmalidir:

```php
function mdg_kommo_active_events_available_functions() {
    return array( 'agent' );
}
```

Iki elemanli fonksiyon listesi Kommo tarafinda reddedilir. AI temsilcisi cevap uretecekse `agent` kullanilmalidir.

## 6. Kommo Kaynak Listesinde Kalmasi Gerekenler

Temiz kaynak listesinde ana program icin sunlar yeterlidir:

- `MMC | Aktif Programlar | Global Kisa ...` - Metin
- `MMC | Aktif Satistaki Etkinlikler` - URL
- `MADAGASKAR SIRKI - RESMI BILET ...` - genel bilet kurallari
- `Temel bilgiler`
- `Urunler ve hizmetler`
- `Isletme ozeti`

Eski manuel program kaynaklari, eski PDF program kaynaklari ve birden fazla `Global Kisa` kopyasi botu karistirabilir.

## 7. Test Senaryolari

Yeni veya temiz bir Kommo/Instagram/WhatsApp konusmasinda test edilmelidir:

| Musteri Mesaji | Beklenen Cevap |
| --- | --- |
| `Mamak ilçesi gösteri için bilgi almak isterim` | Mamak icin 10 Ekim 2026 programi verilmeli. |
| `2 Ekim gösterisi için bilgi verebilir misiniz` | 2 Ekim icin Kirikkale programi verilmeli. |
| `Uşak` sonra `15 Ekim` | Usak 15 Ekim programi verilmeli. |
| `Konum gönderir misiniz` | Ilgili salon, acik adres ve Google Maps linki birlikte verilmeli. |

Eski bir konusmada bot daha once yanlis baglam tutmus olabilir. Bu yuzden kesin test yeni konusmada yapilmalidir.

## 8. Yazilan Kodlar ve Dosyalar

| Dosya | Amac |
| --- | --- |
| `wordpress-snippets/mdg-kommo-active-events-unified-source.php` | Ana calisan kod. WordPress aktif etkinlikleri toplar, gizli kaynak sayfasi uretir, Kommo URL/metin kaynaklarini senkronlar. |
| `wordpress-snippets/mdg-kommo-auto-source-refresh.php` | Onceki otomatik kaynak yenileme denemesi. Dikkatli kullanilmali; ayri kaynaklar uretip Kommo'yu karistirabilir. |
| `wordpress-snippets/mdg-kommo-location-menu-snippet.php` | Kommo konum menusu/konum cevaplari icin onceki snippet. |
| `wordpress-snippets/mdg-kommo-location-menu-simple.php` | Basit konum menusu denemesi. |
| `wordpress-snippets/mdg-kommo-force-refresh-trigger.php` | Kommo kaynak yenilemeyi tetikleme yardimci snippet'i. |
| `route-tools/mdg-route-single-share-snippet.php` | Personel rota paylasimini tek link mantigina yaklastiran onceki calisma. |
| `wordpress-plugins/madagaskar-ticket-session-datetime-fix/madagaskar-ticket-session-datetime-fix.php` | PDF bilette tarih/saat alaninin gercek satin alinan seansi basmasi icin hazirlanan eklenti. |
| `wordpress-plugins/madagaskar-ticket-session-datetime-fix/README.txt` | PDF bilet seans saati duzeltmesi aciklamasi. |

## 9. PDF Bilet Seans Saati Duzeltmesi

PDF bilet sorunu: bilet sablonu secilen seans yerine etkinligin ilk/varsayilan seans saatini basiyordu.

Beklenen mantik:

1. Siparis satirindan/attendee kaydindan gercek satin alinan seans bulunur.
2. Bilet uzerindeki `TARIH & SAAT` alani bu seansin tarih, baslangic ve bitis saatinden uretilir.
3. Yetiskin, cocuk ve aile biletlerinde ortak uygulanir.

Canli testte ornek bir bilette Bolu icin `27 Eylul 2026 14:00 - 15:00` dogru basildi.

## 10. QR / Check-in Notu

Bilet uzerindeki QR kod okutulunca sadece bilet numarasi gorunuyordu. Bu, QR'in sadece metin/bilet numarasi tasidigi anlamina gelir. Gise gorevlisinin gecis kontrolu yapabilmesi icin QR'in Ticketera/Checkinera tarafindan kabul edilen check-in verisine veya check-in URL'sine baglanmasi gerekir.

Ilk karar: Ticketera/Checkinera canli check-in mantigi arastirilacak ve QR icerigi buna gore duzenlenecek. Bu kisim henuz nihai kapatilmadi.

## 11. Rota / Spoke Notu

Personel rota paylasiminda tek tek link yerine ekiplerin secebilecegi tek paylasim linki istendi. Daha once `PRG-2026-ANK-PURSAK-001_Pursaklar_Spoke_Rota_Import.csv` hazirlandi. Ayrica `mdg-route-single-share-snippet.php` dosyasi bu is akisi icin ayrildi.

## 12. Gelecek Sohbette Devam Etme Kurali

Yeni sohbette once su kontrol edilmeli:

1. WordPress Code Snippets ID `70` aktif mi?
2. Admin kontrol sayfasinda aktif etkinlik sayisi dogru mu?
3. Kommo kaynak listesinde kisa metin kaynagi `#1334930` veya daha yeni tek aktif kopya var mi?
4. AI temsilcisi yeni konusmada Mamak/Usak/2 Ekim testlerine dogru cevap veriyor mu?
5. Eski program kaynaklari tekrar eklenmis mi?

GitHub icin durum: Bu calisma klasoru yerel olarak Git repo degil. Bagli GitHub hesabinda ilgili repo bulunamadi. GitHub'a commit atmak icin repo adi veya URL gerekir.



## 2026-09-27 — Kommo kaynak v4 düzenlemesi

Kommo retrieval testlerinde Uşak ve Denizli gibi etkinliklerde kısa kaynağın 1950 karakter sınırında kesildiği, aynı verinin `DOĞRU CEVAP`, şehir-tarih indeksi ve `TÜM AKTİF` bloklarında tekrarlandığı görüldü. Bunun sonucunda AI bazı şehirlerde tarihi bulup salon/konum bilgisini kaçırabiliyordu.

Yapılan değişiklikler:
- `MMC | Aktif Programlar | Global Kısa Cevap` sadeleştirildi; her aktif etkinlik yalnız bir kez `şehir | tarih | salon | seans | fiyat` biçiminde yazılıyor.
- Ayrı `MMC | Aktif Etkinlik Konumları` kısa metin kaynağı eklendi; `şehir | salon | açık adres | Google Maps` biçiminde çalışıyor.
- Program kısa kaynağındaki tekrar eden `ŞEHİR-TARİH İNDEKSİ`, `TÜM AKTİF` ve şehir özel tekrar blokları kaldırıldı.
- Tüm online satış yönlendirmesi yalnız `https://madagaskarsirki.com/bilet-al/` merkezî sayfasına alındı.
- Okuldan ücretsiz çocuk bileti kuralı netleştirildi: toplu girişte geçersiz; çocuk tek başına giremez; bir ücretli yetişkin yanında en fazla iki ücretsiz çocuk bileti kullanılabilir.
- Senkronizasyon artık URL kaynağı + Program kısa kaynağı + Konum kısa kaynağını birlikte oluşturur/günceller.

İlgili commit: `0fc870f200dcde205cdaae626b8e9aecb9fd12b5`.

Canlı WordPress tarafında bu snippet sürümü uygulandıktan sonra Madagaskar > Kommo Aktif Kaynak ekranından yeniden senkronizasyon çalıştırılmalıdır. Sonrasında Kommo kaynak listesinde `MMC | Aktif Etkinlik Konumları` kaynağı görünmelidir.
