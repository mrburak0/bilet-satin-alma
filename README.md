# Bilet Satın Alma Platformu — Frontend İskeleti

Bu klasör, görev dokümanındaki sayfa ve yetki mimarisine uygun **statik frontend** iskeletini içerir.
Bootstrap 5 kullanılmıştır. JavaScript tarafında **demo/mock** verilerle sayfa akışları gösterilir. 
Backend entegrasyonu yapılınca formlar gerçek API çağrılarına bağlanmalıdır.

## Sayfalar
- `index.html` — Ana sayfa, arama formu, öne çıkan seferler
- `listings.html` — Sefer listeleme ve filtreleme
- `trip-details.html` — Sefer detayları
- `purchase.html` — Koltuk seçimi + kupon alanı + özet
- `tickets.html` — Hesap & Biletlerim (PDF butonu maket)
- `login.html` / `register.html` — Auth formları (localStorage ile demo)
- `firmapanel.html` — Firma Admin paneli: sefer ve kupon yönetimi (maket)
- `admin.html` — Admin paneli: firma, firma admini ve global kupon yönetimi (maket)
- `404.html` — Basit 404 sayfası

## Çalıştırma
Dosyaları bir statik sunucuda açabilirsiniz. Örn:
- VS Code Live Server
- Python: `python -m http.server` (kökte çalıştırın) ve `http://localhost:8000/index.html`

## Notlar
- **Rol görünümleri** navbar'da `authArea` ile örneklenmiştir. Giriş yaptığınızda "User" rozeti görünür.
- **Koltuk seçimi**: Dolu koltuklar `occupied` sınıfı ile pasifleştirilir.
- **Kupon**: `KUPON10`, `SONBAHAR20` demo kodları mevcuttur.
- Backend sonrası:
  - Seferler, biletler, kullanıcılar ve kuponlar API'dan yüklenecek.
  - PDF indirme butonu gerçek dosyayı indirecek.
  - Firma/Admin panellerindeki CRUD modalları gerçek POST/PUT/DELETE çağrılarına bağlanacak.

---
Görev tesliminde bu frontend’i doğrudan `public/` klasörü olarak kullanabilir ya da `php` şablonlarına taşıyabilirsiniz.
