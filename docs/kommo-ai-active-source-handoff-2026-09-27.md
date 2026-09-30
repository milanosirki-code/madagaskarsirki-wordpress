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


## 2026-09-30 — Snippet v1.2.0 (dayanıklı senkronizasyon + elle güncelleme akışı)

**Önemli:** Kommo kaynak kodu `madagaskar-management-center` plugin'inde DEĞİL, WordPress **Code Snippets ID 70** olarak çalışır (`docs/code-snippets/mdg-kommo-active-events-unified-source.php`). GitHub'a commit atmak canlı siteyi güncellemez; dosya içeriği (ilk `<?php` satırı hariç) Code Snippets ID 70'e yapıştırılıp kaydedilmelidir. MMC plugin'i (1.3.30) bu işte değişmedi.

### Canlı denetim bulguları (30 Eylül 2026, ekran görüntüleri + canlı sayfalar)
- Canlı snippet = repo `24432d6` + 3 küçük fark (sürüm 1.1.1, iki önizleme kutusu, `source_update_request` içinde 404 nedeniyle erken dönüş).
- Kommo AI kaynaklarında (`#1334640` URL, `#1334998` program, `#1334988` konum) mevcut kaynağı güncelleyen PATCH/PUT adresleri 404 dönüyor. WordPress metni üretiyor ama Kommo'daki metin 27 Eylül'den beri değişmemiş olabilir; Uşak gibi sonradan eklenen etkinliklerin Kommo'da "yayımlanmadı" görünmesinin en güçlü adayı budur (Kommo'daki metin doğrulanmadı).
- Konum kaynağı `#1334988` WordPress'te kayıtlı; "oluşmadı" değil "güncellenemiyor" sorunu.
- 12 aktif etkinliğin 8'inde fiyat "doğrulanamadı" yazıyordu: sayfalar fiyatı farklı cümlelerle yazıyor (Uşak/Aydın/Didim: tek cümle; Kırıkkale: `Biletler: Çocuk 250 TL; Yetişkin 500 TL; Aile Paketi 1.100 TL`; Mamak/Denizli/Sincan/Yenimahalle/Eskişehir/İzmir: aile paketi ayrı cümlede). Eski tek kalıp yalnız birini tanıyordu.
- Kırıkkale adresi JSON-LD'de satır sonu içeriyor; konum satırı ikiye bölünüyordu.
- Kommo AI kaynak API'sinde belgelenmiş güncelleme/silme adresi yok; yalnız oluşturma (POST) çalışıyor.

### v1.2.0 değişiklikleri
- Program kısa kaynağı: her etkinlik tek satır. 1950 sınırı aşılırsa kısa fiyat kodu (Ç250/Y500/A1.100) ve yılsız tarih uygulanır; etkinlik düşürülmez. 12 etkinlik ~1.7K karakter.
- Fiyat okuma: eski kalıp korunur, yoksa Çocuk/Yetişkin/Aile Paketi sıradan bağımsız ayrı ayrı okunur. Bulunamayan kalem için değer uydurulmaz. Fiyat her etkinliğin kendi sayfasından gelir (İzmir 300/600/1.300).
- Konum kaynağı: `Şehir/İlçe | Salon | Açık adres | Google Maps`; Maps yoksa `Maps bağlantısı yok`. Ad, salon, adres alanlarındaki satır sonları tek boşluğa çevrilir.
- Ankara ilçeleri adres/şehir verisinden dinamik etiketlenir.
- **Elle güncelleme akışı:** API güncellemesi olmadığı için mevcut kaynağa yazılamaz; kod yeni/duplicate kaynak açmaz. Yönetim ekranı her metin kaynağı için güncel metni, karakter sayısını, "Metni kopyala" düğmesini ve "Kommo'ya yapıştırdım, güncel işaretle" düğmesini gösterir. Kaynak, yalnız doğrulanmış başarıda veya bu onayda "güncel" sayılır; etkinlik verisi değişirse yeniden "GÜNCEL DEĞİL" olur.
- Etkinlik sayfası geçici okunamazsa (zaman aşımı/5xx/429) bir kez yeniden denenir; hâlâ okunamazsa program/konum kaynakları güncellenmez, eksik liste önbelleğe alınmaz.
- URL kaynağında yenileme doğrulanamazsa HATA değil UYARI gösterilir.
- Güncelleme API'si ileride çalışırsa: `add_filter( 'mdg_kommo_source_update_api_available', '__return_true' );`

### Canlıya alma sırası
1. Code Snippets ID 70 kodunu yedekle.
2. `mdg-kommo-active-events-unified-source.php` içeriğini (ilk `<?php` satırı olmadan) yapıştır, kaydet, etkin bırak.
3. `mdg-kommo-auto-source-refresh.php` ("Madagaskar Kommo AI Source Auto R...") ve `mdg-kommo-force-refresh-trigger.php` snippet'lerini devre dışı bırak (etkinlik/program başına ayrı Kommo kaynağı üretip botu karıştırır).
4. `Madagaskar > Kommo Aktif Kaynak`: senkronizasyon düğmesine bas, sonra Program ve Konum kartlarındaki metni kopyalayıp Kommo'da ilgili kaynağın (`#1334998`, `#1334988`) içine yapıştır, kaydet, "güncel işaretle" düğmesine bas.
5. Kommo'da "Temel bilgiler" kaynağına `docs/kommo-temel-bilgiler-metni.md` metnini yapıştır.
6. Yeni kaynakların yeni konuşmada çalıştığı doğrulandıktan sonra eski `MMC | PRG-...` kaynaklarını sil.
