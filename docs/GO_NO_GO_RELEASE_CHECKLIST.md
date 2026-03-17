# Go / No-Go Release Checklist (12 Mart 2026)

> Karar kurali (13 Mart 2026): Bu checklist, `docs/RELEASE_DECISION_2026_03_13.md` ile birlikte canliya cikis icin tek karar kaynagidir. Zorunlu maddeler kanitli sekilde yesil olmadan durum **GO** olarak ilan edilmez.

> Mevcut repo-temelli karar: **NO-GO**. Sebep, asagidaki zorunlu maddelerin bugune ait dogrulanmis yesil kaniti bu dosyada islenmis degil.

Bu checklist, canliya cikmadan once son karar adimi icindir.

## 1) Kritik servis sagligi
- [ ] Backend health endpoint calisiyor (`/up`)
- [ ] Admin dashboard aciliyor (`untitled/admin-dashboard.html`)
- [ ] Kritik admin API'ler 200/403 beklenen sekilde donuyor

## 2) Guvenlik
- [ ] `/api/users` admin disinda 403
- [ ] `/api/users/{id}/profile-card` admin disinda 403
- [ ] Sosyal medya endpointi owner/admin disinda 403
- [ ] Telefon ve email gizlilik kurallari role gore dogru
- [ ] 429 ozet metrikleri panelde gorunuyor

## 3) Gozlemlenebilirlik
- [ ] Operasyon ve Guvenlik Ozeti paneli veri cekiyor
- [ ] Istemci Hata Gunlugu paneli listeleme yapiyor
- [ ] Security regression artifact raporlari CI'da olusuyor

## 4) Test kapilari
- [ ] `tests.yml` yesil
- [ ] `security.yml` yesil
- [ ] `mobile-tests.yml` yesil
- [ ] `AdminAccessAndPrivacyTest` yesil
- [ ] `DiscoveryResponseStandardizationTest` yesil

## 5) Mobil kalite
- [ ] Splash -> login/home yonlendirme dogru
- [ ] Login/Register + onboarding role secimi calisiyor
- [ ] Player detail skeleton + retry calisiyor
- [ ] TR/EN locale degisimi ve kaliciligi calisiyor

## 6) Canonical ve redirect
- [ ] Legacy admin dosyalari canonical admin'e yonleniyor
- [ ] Redirect smoke script geciyor (`verify-admin-redirects.php`)

## 7) Son karar
- [ ] Tum zorunlu kutucuklar yesil -> **GO**
- [ ] Herhangi kritik kutucuk kirmizi -> **NO-GO**

---

## Hızlı komutlar (local)

```bat
cd C:\Users\Hp\Desktop\PhpstormProjects\untitled
VERIFY_ADMIN_REDIRECTS.bat
```

```bat
cd C:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean
php artisan test --filter=AdminAccessAndPrivacyTest
```

```bat
cd C:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean
php artisan test --filter=DiscoveryResponseStandardizationTest
```
