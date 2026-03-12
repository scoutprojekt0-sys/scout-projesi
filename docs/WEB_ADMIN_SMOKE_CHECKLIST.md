# Web Admin Smoke Checklist

Bu liste `untitled/admin-dashboard.html` icin hizli fonksiyon kontroludur.

## 1) Oturum ve erisim
- [ ] Girisli admin kullanici ile panel aciliyor
- [ ] Admin olmayan kullanici `/api/users` cagrilarinda `403` aliyor
- [ ] Token yokken panelde `adminAuthNotice` gorunuyor

## 2) Ust KPI kartlari
- [ ] Toplam Kullanici degeri API'den geliyor
- [ ] Aktif Kullanici (abonelik) degeri API'den geliyor
- [ ] Toplam Transfer degeri API'den geliyor
- [ ] Aylik Gelir API degeri gorunuyor

## 3) Kayitli kullanici arama
- [ ] `q` ile isim arama calisiyor
- [ ] `q` ile e-posta arama calisiyor
- [ ] Rol filtresi (`player/manager/coach/scout/team`) calisiyor
- [ ] Sonuc metasi (`x-y / total`) dogru doluyor

## 4) Profil karti modal
- [ ] Satirdaki `Profil Karti` butonu modal aciyor
- [ ] Kayit kaynagi (`mobile/web/unknown`) gorunuyor
- [ ] Rol bazli alanlar doluyor
- [ ] Sosyal medya sadece owner/admin durumuna gore geliyor
- [ ] Gizli alanlarda telefon `null`, email maskeli gorunuyor

## 5) Guvenlik kontrolu
- [ ] `/api/users/{id}/profile-card` endpoint'i admin middleware ile korunuyor
- [ ] Social media endpoint'i owner/admin disina `403` donuyor
- [ ] UI'da hassas alanlar yanlis role'de acilmiyor
