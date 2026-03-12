# Live Backend Deploy (Railway)

Bu repo Docker ile deploy edilecek sekilde hazirdir.

## 1) Railway proje olustur
- railway.app -> New Project -> Deploy from GitHub Repo
- Repo: `scout_api`
- Branch: `feat/scout-api-hardening` (veya merge sonrasi `main`)

## 2) PostgreSQL ekle
- Project icinde `New` -> `Database` -> `PostgreSQL`
- Olusan DB servisinin Variables kismini ac

## 3) Web servis environment variables
Asagidaki degiskenleri Web servisine ekle:

- `APP_NAME=NextScout`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=<web service url>`
- `APP_KEY=<php artisan key:generate --show cikisi>`
- `DB_CONNECTION=pgsql`
- `DB_HOST=<railway postgres host>`
- `DB_PORT=<railway postgres port>`
- `DB_DATABASE=<railway postgres database>`
- `DB_USERNAME=<railway postgres user>`
- `DB_PASSWORD=<railway postgres password>`
- `SESSION_DRIVER=file`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=sync`
- `LOG_CHANNEL=ops`
- `LOG_OPS_STACK=daily,alerts`
- `ALERT_LOG_LEVEL=error`
- `ALERT_SLACK_WEBHOOK_URL=<slack-incoming-webhook>`
- `CORS_ALLOWED_ORIGINS=<landing-url>,http://localhost:3000,http://127.0.0.1:3000`
- `SANCTUM_STATEFUL_DOMAINS=<landing-domain>,localhost,127.0.0.1`

Not: `APP_KEY` icin lokalde su komutu calistir:

```bash
php artisan key:generate --show
```

## 4) Deploy sonrasi hizli kontrol
- `GET /up`
- `GET /api/news/live`
- `POST /api/auth/register`

## 5) Landing baglantisi
Landing tarafinda `api_base` olarak bu backend URL'ini kullan:

```text
https://<backend-domain>/api
```

## 6) Staging CI/CD + Rollback

GitHub Actions ile staging deploy ve rollback icin:

- `.github/workflows/deploy-staging.yml`
- `.github/workflows/rollback-staging.yml`
- `docs/runbooks/STAGING_DEPLOY_ROLLBACK.md`

Not: Workflow'lar `staging` environment secret'larini kullanir.

## 7) Monitoring + Alerting

Health endpointleri:
- `/health/live`
- `/health/ready`
- `/up`

Detayli operasyon adimlari:
- `docs/runbooks/MONITORING_ALERTING.md`
