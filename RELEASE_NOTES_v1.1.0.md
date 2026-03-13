# NextScout v1.1.0 Release Notes

Date: 9 Mart 2026

## Scope

Bu surum, Week 4-5-6 hedeflerini tek paket halinde tamamlar:

- Week 4: Data quality raporlama ve admin widget gorunurlugu
- Week 5: Team dashboard transfer ozet entegrasyonu
- Week 6: Oyuncu karsilastirma, trend rozetleri ve rapor export

## Backend Highlights (`scout_api_pr_clean`)

- Data quality API genisletildi:
  - `GET /api/data-quality/report`
- Team profile/transfer ozeti genisletildi:
  - `GET /api/teams/{id}/overview`
  - `GET /api/teams/{id}/transfer-summary`
- Player analytics endpointleri eklendi:
  - `POST /api/players/compare`
  - `GET /api/players/{playerId}/trend-summary`
  - `GET /api/market-values/leaderboard`
- Users-role tabanli veri modeline uyum duzeltmeleri yapildi
- Week 4-6 feature testleri eklendi ve gecti

## Frontend Highlights (`untitled`)

### Team Dashboard (`team-dashboard.html`)

- Week 5 transfer ozet kartlari eklendi
- Gelen/giden transfer listeleri eklendi
- Week 6 oyuncu karsilastirma paneli eklendi
- Winner badges:
  - En yuksek piyasa degeri
  - En yuksek gol katkisi
- Trend arrows:
  - Yukselen / Dusen / Sabit
- Export butonlari:
  - CSV indir
  - JSON indir
- Kiyas kartlari leaderboard sirali gosterime gecti

### Admin Dashboard (`admin-dashboard.html`)

- Week 4 data quality widget bolumu eklendi
- KPI metrikleri:
  - Kaynak kapsami
  - Dogrulama orani
  - Cakisma orani
  - Transfer dogrulama orani
- `Yenile` aksiyonu ile canli API verisi cekiliyor
- API erisilemezse kontrollu fallback metrikleri gosteriliyor

## Notes

- PHP startup warning (`oci8_19`) temizleme islemi tamamlandi.
- CLI `php -v` warning'siz dogrulandi.
- Mevcut satir sonu (CRLF/LF) uyarilari davranis bozucu degildir.

## Suggested Next

- v1.1.0 tag olusturma
- Admin panelde data quality detay sayfasi (drill-down)
- Team compare sonucuna mini grafik ekleme
