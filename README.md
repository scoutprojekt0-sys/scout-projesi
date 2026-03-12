<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Scout API Quick Start

Tek komut ile ilk kurulum:

```bash
composer run setup
```

Gelistirme sunucusu:

```bash
php artisan serve
```

E2E akis icin Postman collection:

- postman/Scout_API_E2E.postman_collection.json
- postman/Scout_API_E2E_Communication_Media.postman_collection.json

Not: Media - Upload istegi icin media_file_path degiskenine lokal bir dosya yolu verin.

Newman ile collection calistirma:

```bash
npm run test:api
```

Communication + media E2E:

```bash
npm run test:api:extended
```

Local override ile calistirma:

```bash
npm run test:api:local
```

Windows'ta php.ini kullanarak test kosma (mbstring dahil):

```powershell
$env:PHPRC='C:\Users\Hp\PhpstormProjects\untitled'; php artisan test
```

Security komutlari:

```bash
# Legacy wildcard tokenlari listeler (silmez)
php artisan security:revoke-legacy-tokens --dry-run

# Legacy wildcard tokenlari siler
php artisan security:revoke-legacy-tokens
```

Not: Bu komut her gun 03:00'te scheduler ile otomatik calisir.

Auth session endpointleri:

- `GET /api/auth/sessions` aktif oturumlar
- `POST /api/auth/refresh` mevcut tokeni yeniler (token rotation)
- `DELETE /api/auth/sessions/{tokenId}` secili cihazi kapatir
- `DELETE /api/auth/sessions` mevcut cihaz haric tum oturumlari kapatir
- `POST /api/auth/password/forgot` reset link talebi
- `POST /api/auth/password/reset` token ile sifre yenileme

`GET /api/auth/sessions` response alanlari:

- `device_label`
- `ip_address`
- `user_agent`
- `last_used_at`

Session revoke olaylari `audit_events` tablosuna yazilir.

Core UX query parametreleri:

- `GET /api/opportunities`: `status`, `position`, `city`, `page`, `per_page`, `sort_by`, `sort_dir`
- `GET /api/applications/incoming`: `status`, `page`, `per_page`, `sort_by`, `sort_dir`
- `GET /api/applications/outgoing`: `status`, `page`, `per_page`, `sort_by`, `sort_dir`
- `GET /api/contacts/inbox|sent`: `status`, `page`, `per_page`, `sort_by`, `sort_dir`
- `GET /api/users/{id}/media`: `type`, `page`, `per_page`, `sort_by`, `sort_dir`

CI guvenlik kontrolleri:

- `.github/workflows/security.yml`
- Composer dependency audit (`composer audit`)
- Secret scan (`gitleaks`)
- `.github/workflows/codeql.yml` (CodeQL PHP analizi)
- `.github/workflows/tests.yml` icinde Pint style check
- `.github/workflows/api-smoke.yml` icinde Newman Postman E2E

## Project Docs Structure

- `docs/adr`: architecture decision records
- `docs/api`: API contract and error mapping notes
- `docs/runbooks`: deploy/rollback/incident runbooks
- `docs/runbooks/branch-protection.md`: main branch required check policy
- `docs/runbooks/monitoring-alerts.md`: ops log, alert and first-response guide
- `docs/runbooks/backup-restore.md`: backup and restore procedure
- `docs/runbooks/rollback-procedure.md`: rollback steps and verification
- `docs/security`: security strategy and hardening notes
- `docs/security/SECURITY_CHECKLIST_FINAL.md`: final security completion checklist
- `docs/security/PENTEST_SCENARIOS.md`: pentest-style scenario set
- `docs/uat/UAT_WEEK7_SIGNOFF.md`: week 7 role-based UAT sign-off
- `docs/accessibility/WEEK7_ACCESSIBILITY_CHECKLIST.md`: week 7 accessibility pass report
- `docs/launch/PREPROD_DRY_RUN_2026-02-25.md`: pre-production dry-run evidence
- `docs/launch/RELEASE_CANDIDATE_CHECKLIST.md`: release candidate go/no-go checklist
- `docs/launch/WEEK1_MONITORING_PLAN.md`: first-week launch monitoring cadence
- `docs/launch/GO_LIVE_CUTOVER_CHECKLIST.md`: launch window cutover steps
- `docs/DESIGN_SYSTEM_V1.md`: design tokens and auth component rules
- `docs/ROADMAP_WEEK1_WEEK2.md`: 2 haftalik delivery plani
- `docs/ROADMAP_WEEK3_WEEK4.md`: core UX + messaging/media delivery plani
- `docs/ROADMAP_WEEK5_WEEK8.md`: performance, security final, UAT ve launch plani

## Environment Matrix

| Variable | Local | CI | Production | Notes |
| --- | --- | --- | --- | --- |
| `APP_ENV` | Required | Required | Required | `local` / `testing` / `production` |
| `APP_KEY` | Required | Required | Required | `php artisan key:generate` |
| `APP_URL` | Required | Required | Required | API base URL |
| `DB_CONNECTION` | Required | Required | Required | default `sqlite` |
| `FRONTEND_URL` | Optional | Optional | Required | frontend origin |
| `CORS_ALLOWED_ORIGINS` | Optional | Optional | Required | comma-separated allowed origins |
| `SANCTUM_TOKEN_EXPIRATION` | Optional | Optional | Optional | minutes, default `10080` |
| `LOG_SECURITY_LEVEL` | Optional | Optional | Optional | default `info` |
| `LOG_SECURITY_DAYS` | Optional | Optional | Optional | default `30` |
| `LOG_OPS_LEVEL` | Optional | Optional | Optional | default `info` |
| `LOG_OPS_DAYS` | Optional | Optional | Optional | default `14` |
| `MONITOR_SLOW_REQUEST_MS` | Optional | Optional | Optional | default `800` |
| `OPPORTUNITIES_CACHE_ENABLED` | Optional | Optional | Optional | default `true` |
| `OPPORTUNITIES_CACHE_TTL_SECONDS` | Optional | Optional | Optional | default `60` |
| `RATE_LIMIT_AUTH_PER_MINUTE` | Optional | Optional | Optional | default `5` |
| `RATE_LIMIT_API_READ_PER_MINUTE` | Optional | Optional | Optional | default `120` |
| `RATE_LIMIT_API_WRITE_PER_MINUTE` | Optional | Optional | Optional | default `40` |
| `AUTH_FAILED_ATTEMPTS_BEFORE_LOCK` | Optional | Optional | Optional | default `5` |
| `AUTH_LOCK_SECONDS` | Optional | Optional | Optional | default `900` |

Production boot-time validation now fails fast if these are missing:

- `APP_KEY`
- `APP_URL`
- `FRONTEND_URL`
- `CORS_ALLOWED_ORIGINS`

Auth design demo route:

- `GET /auth/design-demo`
- `GET /auth/sessions`
- `GET /app/core` (opportunities + applications + profile core flow UI)
- `GET /app/communication` (messaging + media UX/API parity UI)

## Quality Gates

Local:

- `composer lint`
- `composer test`
- `composer quality`

CI:

- `.github/workflows/tests.yml`
- `.github/workflows/security.yml`
- `.github/workflows/api-smoke.yml`
- `.github/workflows/codeql.yml`

## Week 5 Reliability Notes

- Run migration for performance indexes:
  - `php artisan migrate`
- Opportunity list endpoint now uses short-TTL cache with versioned invalidation on create/update/delete.
- Request metrics middleware adds `X-Request-Id` and writes request summary/alerts to `ops` log channel.

## Week 7 UAT + Content Polish Notes

- Added Quick Start onboarding blocks to:
  - `/auth/sessions`
  - `/app/core`
  - `/app/communication`
- Added skip links and improved keyboard focus-visible styling.
- Added live status regions (`role="status"`, `aria-live="polite"`) and async `aria-busy` toggles.
- Added UAT and accessibility evidence docs under `docs/uat` and `docs/accessibility`.

## Week 8 Launch Notes

- Added release documentation package under `docs/launch`:
  - pre-prod dry-run report
  - release candidate checklist
  - first-week monitoring plan
- Added manual RC workflow:
  - `.github/workflows/release-candidate.yml`
  - runs quality gates plus both Newman E2E collections
