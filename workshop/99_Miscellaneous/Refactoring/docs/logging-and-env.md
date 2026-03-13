# Logging ve Ortam Dogrulama

## Log formati
`logs/app-YYYY-MM-DD.log` satir formati:

`[ISO8601_DATE] [LEVEL] message {json_context}`

## Hata akisi
- `app_fail()` teknik detayi `app_log('error', ...)` ile dosyaya yazar.
- Kullaniciya request id iceren guvenli hata ekrani doner.

## Prod ayari dogrulamasi
1. `.env` icinde `APP_ENV=prod` ve `APP_DEBUG=0` ayarla.
2. Uygulamayi ac, bilincli bir hata tetikle.
3. Tarayicida PHP warning/stack trace gorunmedigini dogrula.
4. `logs/app-YYYY-MM-DD.log` dosyasina hata satiri dustugunu dogrula.
