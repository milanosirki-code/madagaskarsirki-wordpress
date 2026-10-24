# Milano Sirki — çalışma ve arşiv özeti

Arşiv tarihi: 25.10.2026 (Europe/Istanbul). Kullanıcı Milano çalışmalarını Drive ve GitHub'a kaydetmemizi istedi.

25.10.2026 — Milano Sirki web düzenlemeleri toplu arşiv kaydı
24.10.2026 tarihinde yayımlanan çalışmalar: Milano ana sayfasında ve /bilet-al/ sayfasında geçmiş gösterileri gizleme, tarih ve saat görünürlüğü, belirgin harita/yol tarifi düğmeleri, büyük ve ayrı seans kartları, başlayan seans için turuncu uyarı ve tamamlanan seansı kapatma. Ana sayfada bir saate basıldığında bilet sayfasındaki ilgili seans seçiliyor ve seçili saat gösteriliyor.
Canlı yayın sırasında doğrulanan durum: ana sayfada Karamürsel, Yalova ve Darıca gizlendi; o günkü Derince gösterisi görünüyordu. Ana sayfadan Derince 14:00 bağlantısı /bilet-al/?seans=177#derince adresine giderek doğru formu seçti; 16:00'a geçişte yalnız bir form seçili kaldı. Bu kayıt 24 Ekim doğrulamasını anlatır; 25 Ekim için yeniden canlı kontrol yapıldığı anlamına gelmez.
Teknik düzeltmeler: Türkçe ve ISO tarih desteği, WordPress'in HTML entity dönüşümünden kaynaklanan arşiv betiği sözdizimi hatası, 15 saniyelik saat yenileme ve sekmeye dönüşte güncelleme. Milano'da başlayan seans uyarısı +60 dakika sınırına kadar gösterilir; +60 dakikada bağlantı kapanır. Bu davranış Madagaskar'ın satış başlangıcında kapanan seans kuralından ayrıdır.
Kontroller: test-milano-ticket.cjs ve test-home-session-links.cjs önceki uygulamada geçti; yayımlanmış betik sözdizimi ve seans bağlantıları doğrulandı. Bir yetişkin 490 TL ve iki yetişkin 980 TL görüntülendi, test adedi sıfırlandı. Ödeme veya sipariş oluşturulmadı. Ayrı mobil viewport testi yapılmadı. Fiyatlar, seans ID'leri ve özgün ödeme formları korundu.
GitHub: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/291 . Kaynak, önce/sonra yedekleri, yama dosyaları, testler ve canlı doğrulama belgeleri docs/code-snippets/milano-ticket-20261024/ klasöründe. WordPress sayfaları: ana sayfa #5, bilet sayfası #118. Geri dönüş ilgili yama dosyalarını ters sırada uygulamak veya sayfa revizyonunu geri yüklemek. PR taslak; ana dala birleştirilmedi. Kullanıcının Milano çalışmalarını kaydet talebiyle bu toplu arşiv eklendi.

Drive kaydı: https://docs.google.com/document/d/1Q6-mOKbbkYZgWVcxFYK3yy9zLFjqEZEwKTJ14Z1PiZI

Google Docs eklemesi mevcut revizyonla korundu. Eklenen kapsam paragrafı t.0 sekmesinde 287361–287744 aralığından geri okunarak doğrulandı. Bu işlem arşiv kaydıdır; yeniden canlı site değişikliği yapılmadı.
