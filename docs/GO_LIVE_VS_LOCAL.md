# Go-Live vs Local Checklist Ayrimi

Bu dokuman local gelistirme kontrolleri ile go-live kontrollerini ayirir.

## Local (Gelistirme)
- API ve web UI lokal ortamda acilir
- Test kullanici token akisi dogrulanir
- Admin panelden kullanici arama ve profil karti dogrulanir
- Gizlilik kurallari (email/phone/social) role gore kontrol edilir
- Smoke test listesi: `docs/WEB_ADMIN_SMOKE_CHECKLIST.md`
- Mobile kalite listesi: `scout_mobile/QUALITY_GATE_CHECKLIST.md`

## Go-Live (Uretim)
- Production environment degiskenleri tam
- Stripe/PayPal live anahtarlari dogru
- Queue worker calisiyor
- Error logging ve alerting aktif
- CVE/security taramasi temiz
- Release candidate gate basarili

## Oncelik
1. Local checklist tamamen yesil olmadan go-live adimina gecilmez.
2. Go-live adimi sadece release gate gecisi sonrasi acilir.
