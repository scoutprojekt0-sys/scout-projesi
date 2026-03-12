# Frontend Run Standard (Local)

Bu proje icin local frontend calistirma standardi tekleştirildi:

## Canonical web root
- `untitled/index.html`

## Canonical admin page
- `untitled/admin-dashboard.html`

## Notlar
- `admin-dashboard-improved.html`, `admin-dashboard-modern.html`, `admin-dashboard-new.html`
  dosyalari canonical admin sayfasina yonlendirilir.
- Local testlerde tarayicida `localhost` origin kullanilmali; `file://` acilisi token/localStorage davranisini degistirir.

## Beklenen davranis
- Giriş sonrası `nextscout_token` localStorage’da bulunur.
- Admin panel API istekleri `Authorization: Bearer <token>` ile gider.
