# Weekly Sprint Plan (Baslangic: 12 Mart 2026)

Bu plan, `POST_COMPLETION_BACKLOG.md` maddelerini uygulamaya donuk sprint takvimine cevirir.

## Sprint 1 (1 hafta) - Admin UX + Mobil hizli kazanclar

### Hedef
Admin paneli daha operasyonel hale getirmek ve mobilde ilk gorunen kalite farkini arttirmak.

### Gorevler
- [ ] Admin arama filtrelerini URL query-state ile kalici yap (`q`, `role`, `page`)
- [ ] Profil kart modalina son aktiflik/son login alani ekle (varsa)
- [ ] `PlayerDetailScreen` icin skeleton loading karti ekle
- [ ] Mobil login/register akisina role secimi adimi ekle

### Cikti (Definition of Done)
- [ ] Admin filtreleri sayfa yenilenince korunuyor
- [ ] Profil kartta en az 2 yeni operasyon metadatasi gorunuyor
- [ ] Mobil oyuncu detay ekraninda loading state profesyonel gorunuyor
- [ ] Role secimi backend register payloadina geciyor

### Dogrulama
- [ ] `docs/WEB_ADMIN_SMOKE_CHECKLIST.md` ilgili maddeleri yesil
- [ ] `scout_mobile/test/login_screen_test.dart` ve `home_screen_test.dart` geciyor

---

## Sprint 2 (1-2 hafta) - Test kapsamini buyutme + API standardizasyonu

### Hedef
Teknik borcu azaltmak ve regresyon riskini dusurmek.

### Gorevler
- [ ] Backend response yapilarini Resource siniflariyla standardize et (kritik endpointlerden basla)
- [ ] Admin panel icin CSV export ekle (kullanici arama sonucu)
- [ ] Mobile integration test tabani olustur (auth -> home -> detail)
- [ ] API hata mesaj formatlarini web/mobilde teklestir

### Cikti (Definition of Done)
- [ ] En az 3 kritik endpoint Resource formatina tasinmis
- [ ] Admin export dosyasi indirilebiliyor
- [ ] En az 1 integration test senaryosu CI'da kosuyor
- [ ] 401/403/422 mesajlari web+mobilde ayni semantik yapiyla gorunuyor

### Dogrulama
- [ ] `tests/Feature/AdminAccessAndPrivacyTest.php` geciyor
- [ ] CI `tests.yml` + `mobile-tests.yml` yeşil

---

## Sprint 3 (1-2 hafta) - Uretim oncesi sertlestirme

### Hedef
Go-live oncesi gozlemlenebilirlik ve guvenlik olgunlugunu tamamlamak.

### Gorevler
- [ ] Canonical admin redirectleri icin regression smoke (legacy admin dosyalari)
- [ ] Sentry/alternatif hata toplama entegrasyonu
- [ ] Rate-limit metriklerini admin dashboarda bagla
- [ ] Security regression job'a artifact raporlama ekle

### Cikti (Definition of Done)
- [ ] Legacy admin URL'ler tek canonical admin'e yonleniyor
- [ ] Uretim hatalari merkezi sistemde toplaniyor
- [ ] Adminde en az 2 adet rate-limit metriği gorunuyor
- [ ] Security workflow artifact uretip sakliyor

### Dogrulama
- [ ] `docs/GO_LIVE_VS_LOCAL.md` go-live bolumu tamamlandi
- [ ] Release candidate gate basarili

---

## Roller ve Sorumluluk
- **Backend odak**: API guvenlik, response standardizasyonu, CI
- **Frontend/Admin odak**: dashboard UX, export, metrik kartlari
- **Mobile odak**: onboarding, loading UX, integration test

## Haftalik ritim
- Pazartesi: Sprint planlama (30 dk)
- Carsamba: Ara durum kontrolu (15 dk)
- Cuma: Demo + checklist kapatma (30 dk)

## Risk ve Onlem
- Risk: Lokal ortam farklari (`localhost` vs `file://`) tekrar issue cikarabilir
  - Onlem: sadece `FRONTEND_RUN_STANDARD.md` akisina gore test
- Risk: Mobil testlerde flaky senaryolar
  - Onlem: deterministic fixture ve mock token manager
