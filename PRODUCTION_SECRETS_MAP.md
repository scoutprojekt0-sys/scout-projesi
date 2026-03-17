# Production Secrets Map

Bu dosya `.env.production` icindeki placeholder alanlari gercek production degerlerine cevirirken referans olarak kullanilir.

## Railway / Hosting

- `APP_URL`
  - Kaynak: backend hosting domaini
  - Ornek: `https://scout-api-production.railway.app`
- `APP_KEY`
  - Kaynak: Laravel
  - Uretim: `php artisan key:generate --show`
- `APP_ENV`
  - Deger: `production`
- `APP_DEBUG`
  - Deger: `false`

## Frontend

- `FRONTEND_URL`
  - Kaynak: frontend deployment domaini
  - Ornek: `https://scout-app-production.vercel.app`
- `CORS_ALLOWED_ORIGINS`
  - Kaynak: frontend origin listesi
  - Tek domain varsa: `https://scout-app-production.vercel.app`
  - Birden fazla origin varsa virgul ile ayir

## Database

- `DB_CONNECTION`
  - Kaynak: hosting provider database service
  - Beklenen: `mysql` veya `pgsql`
- `DB_HOST`
  - Kaynak: Railway MySQL/Postgres service host
- `DB_PORT`
  - Kaynak: Railway MySQL/Postgres service port
- `DB_DATABASE`
  - Kaynak: olusturulan production database adi
- `DB_USERNAME`
  - Kaynak: database kullanici adi
- `DB_PASSWORD`
  - Kaynak: database sifresi

## Redis / Queue / Cache

- `REDIS_HOST`
  - Kaynak: Redis service host
- `REDIS_PORT`
  - Kaynak: Redis service port
- `REDIS_PASSWORD`
  - Kaynak: Redis sifresi varsa provider paneli
- `QUEUE_CONNECTION`
  - Deger: `redis`
- `CACHE_STORE`
  - Deger: `redis`

## Mail

- `MAIL_MAILER`
  - Beklenen: `smtp`
- `MAIL_HOST`
  - Kaynak: SMTP saglayici
  - Ornek: Brevo, Mailgun SMTP, SendGrid SMTP, Resend SMTP
- `MAIL_PORT`
  - Kaynak: SMTP saglayici portu
  - Tipik: `587`
- `MAIL_USERNAME`
  - Kaynak: SMTP username
- `MAIL_PASSWORD`
  - Kaynak: SMTP password veya API key
- `MAIL_FROM_ADDRESS`
  - Kaynak: gonderici adresi
  - Ornek: `noreply@alanadiniz.com`
- `MAIL_FROM_NAME`
  - Kaynak: marka adi

## Stripe

- `STRIPE_KEY`
  - Kaynak: Stripe Dashboard > Developers > API keys
  - Beklenen: publishable key
- `STRIPE_SECRET`
  - Kaynak: Stripe Dashboard > Developers > API keys
  - Beklenen: secret key
- `STRIPE_WEBHOOK_SECRET`
  - Kaynak: Stripe Dashboard > Developers > Webhooks > endpoint signing secret
- `STRIPE_WEBHOOK_TOLERANCE_SECONDS`
  - Varsayilan: `300`

## Iyzico

- `IYZICO_API_KEY`
  - Kaynak: iyzico merchant paneli
- `IYZICO_SECRET_KEY`
  - Kaynak: iyzico merchant paneli
- `IYZICO_BASE_URL`
  - Production icin: `https://api.iyzipay.com`
- `IYZICO_CALLBACK_URL`
  - Backend callback/webhook URL
  - Ornek: `https://api.example.com/api/webhooks/iyzico`
- `IYZICO_DEFAULT_IDENTITY_NUMBER`
  - Kullanici tarafinda TCKN tutulmuyorsa gecici fallback
  - Production icin gercek akista kullanici verisiyle cozulmesi daha dogru

## PayPal

- `PAYPAL_CLIENT_ID`
  - Kaynak: PayPal Developer Dashboard > App credentials
- `PAYPAL_SECRET`
  - Kaynak: PayPal Developer Dashboard > App credentials
- `PAYPAL_MODE`
  - Production icin: `live`
- `PAYPAL_WEBHOOK_SECRET`
  - Kaynak: PayPal webhook signature verification setup

## Ne Zaman Zorunlu

Kesin zorunlu:

- `APP_KEY`
- `APP_URL`
- `FRONTEND_URL`
- `CORS_ALLOWED_ORIGINS`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `QUEUE_CONNECTION`
- `CACHE_STORE`
- `MAIL_MAILER`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`

Ozellik kullaniliyorsa zorunlu:

- iyzico kullaniyorsan:
  - `IYZICO_API_KEY`
  - `IYZICO_SECRET_KEY`
  - `IYZICO_CALLBACK_URL`
- Stripe kullaniyorsan:
  - `STRIPE_KEY`
  - `STRIPE_SECRET`
  - `STRIPE_WEBHOOK_SECRET`
- PayPal kullaniyorsan:
  - `PAYPAL_CLIENT_ID`
  - `PAYPAL_SECRET`
  - `PAYPAL_WEBHOOK_SECRET`

## Doldurduktan Sonra

Sirayla su komutlari calistir:

```powershell
php .\artisan release:check --env-file=.env.production
php .\artisan test
php .\artisan config:cache
php .\artisan route:cache
php .\artisan view:cache
```

Deploy sonrasi:

```powershell
php .\artisan migrate --force
```
