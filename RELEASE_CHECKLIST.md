# Release Checklist

## 1. Environment

- [ ] Copy `.env.production.example` to the real production secret store.
- [ ] Set `APP_ENV=production`.
- [ ] Set `APP_DEBUG=false`.
- [ ] Set a real `APP_KEY`.
- [ ] Set public HTTPS values for `APP_URL` and `FRONTEND_URL`.
- [ ] Set `CORS_ALLOWED_ORIGINS` to the exact frontend origin list.
- [ ] Use MySQL or PostgreSQL, not sqlite.
- [ ] Use Redis or database-backed queue, not `sync`.
- [ ] Use Redis or database-backed cache, not `array` or `file`.
- [ ] Use a real SMTP or API mailer, not `log`.
- [ ] Add Stripe and PayPal secrets if those payment flows are enabled.

## 2. Pre-Deploy

- [ ] Run `php artisan test`.
- [ ] Run `php artisan release:check`.
- [ ] Run `php artisan migrate --force` on staging first.
- [ ] Verify `php artisan config:cache`.
- [ ] Verify `php artisan route:cache`.
- [ ] Verify `php artisan view:cache`.
- [ ] Confirm queue worker process definition exists.
- [ ] Confirm scheduler/cron is configured if scheduled tasks are required.

## 3. Manual Smoke Test

- [ ] Login, register, logout, password reset
- [ ] Role-based dashboard access
- [ ] Profile update and media upload
- [ ] Messaging, notifications, favorites, reports
- [ ] Discovery pages: `manager-needs`, `weekly-digest`, `boost-discover`, `look-alike`
- [ ] Live matches, featured, help, trending/news endpoints
- [ ] Admin-only screens and authorization boundaries

## 4. Payments And Webhooks

- [ ] Successful payment
- [ ] Failed payment
- [ ] Duplicate webhook handling
- [ ] Invalid signature rejection
- [ ] Refund or cancellation flow

## 5. Launch Window

- [ ] Create a fresh database backup
- [ ] Deploy application build
- [ ] Run production migrations
- [ ] Warm config, route, and view cache
- [ ] Start queue workers
- [ ] Hit `/api/ping`
- [ ] Review application logs for startup errors

## 6. First 24 Hours

- [ ] Watch failed jobs
- [ ] Watch payment webhook logs
- [ ] Watch auth and rate-limit spikes
- [ ] Verify mail delivery
- [ ] Verify backups completed
