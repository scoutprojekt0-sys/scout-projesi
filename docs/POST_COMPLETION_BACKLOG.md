# Post-Completion Backlog (12 Mart 2026)

Bu liste zorunlu teslim kapsaminin disindaki, urunu bir ust seviyeye tasiyacak iyilestirmeleri toplar.

## P1 - Kisa vadeli (1-2 sprint)
- [x] Admin dashboard filtreleri icin URL query-state (sayfayi yenileyince filtre kaybolmasin)
- [x] Profil kartta son aktiflik ve son login zamani (varsa)
- [x] Mobile login/register alanlarinda role secimi ve onboarding adimi
- [x] Mobile `PlayerDetailScreen` icin skeleton loading karti

## P2 - Orta vadeli (2-4 sprint)
- [x] Backend API response standardizasyonu genisletildi (admin + discovery/public + featured kritik endpointler)
- [x] Admin panelde export (CSV) eklendi; XLSX + denetlenebilir log kaydi sonraki adim
- [x] Mobile integration test tabani baslatildi (auth -> splash -> login/home akis testleri)
- [x] Uygulama ici coklu dil altyapisi (TR/EN) kuruldu

## P3 - Uretim oncesi sertlestirme
- [x] Gecis testi: canonical admin disi sayfa erisimleri redirect dogrulamasi
- [x] Sentry/benzeri hata toplama temeli (istemci hata telemetry + admin log paneli)
- [x] Operasyon ozeti paneli eklendi (analytics + notifications)
- [x] API rate-limit metriklerinin dashboard'a alinmasi
- [x] Security regression job icin artifact raporlama

## Basari Kriteri
Bu backlog maddeleri tamamlandiginda:
- Admin operasyonu tek ekrandan takip edilir
- Mobile UX tutarli ve testli olur
- Uretim gozlemlenebilirligi artar
