# Week 4-6 API Surface

Bu dokuman Week 4, 5 ve 6 kapsaminda eklenen endpointleri ozetler.

## Week 4 - Data Quality Dashboard

### `GET /api/data-quality/dashboard`
Genis kalite metrikleri (oyuncu, transfer, moderasyon, audit).

### `GET /api/data-quality/report`
Widget/summary kullanimina uygun kompakt KPI cevabi.

### `GET /api/data-quality/audit-log`
Filtrelenebilir audit kayitlari.

### `GET /api/data-quality/conflicts`
Cakisma bayragi olan oyuncu verileri.

### `GET /api/data-quality/missing-source`
Kaynak eksigi olan oyuncu verileri.

## Week 5 - Team Profile & Transfer Summary

### `GET /api/teams/{id}/overview`
Public team profile endpoint.

Donen alanlar:
- `team`
- `squad_count`
- `squad`
- `latest_transfers`

### `GET /api/teams/{id}/transfer-summary`
Auth gerektiren transfer ozeti endpointi.

Donen alanlar:
- `incoming_count`
- `outgoing_count`
- `incoming_spend`
- `outgoing_income`
- `net_spend`
- `incoming_spend_by_currency`
- `outgoing_income_by_currency`
- `latest_incoming`
- `latest_outgoing`

## Week 6 - Player Compare & Trends

### `POST /api/players/compare`
Oyunculari market value + form istatistikleri ile karsilastirir.

### `GET /api/players/{playerId}/trend-summary`
Tek oyuncu icin piyasa degeri serisi + kariyer form serisi + ozet.

### `GET /api/market-values/player/{playerId}/trends`
Oyuncu piyasa degeri trendi + kariyer form serisi.

### `GET /api/market-values/leaderboard?limit=20`
Son dogrulanmis piyasa degerlerine gore oyuncu siralamasi.

### `POST /api/market-values/compare`
Coklu oyuncu deger karsilastirmasi.

## Notes

- Veri modeli `users.role` tabanlidir (`player`, `team`), ayri `players/teams` tablolari yoktur.
- Transfer, kariyer ve market-value FK alanlari `users.id` kullanir.
- Kritik mutasyon endpointleri `auth:sanctum` ile korunur.
