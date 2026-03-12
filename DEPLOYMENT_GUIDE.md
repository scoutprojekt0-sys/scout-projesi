# Deployment Guide - Scout API & Frontend

## 🚀 Backend Deployment (Railway.app)

### Adım 1: Railway.app Hesabı Oluştur
- https://railway.app adresine git
- GitHub ile giriş yap
- New Project → GitHub Repo

### Adım 2: Environment Variables Ayarla
Railway Dashboard'da şu değişkenleri ekle:

```
DB_HOST=mysql-production-host
DB_PORT=3306
DB_NAME=scout_db
DB_USER=scout_user
DB_PASSWORD=your-secure-password

REDIS_HOST=redis-production-host
REDIS_PASSWORD=redis-password
REDIS_PORT=6379

STRIPE_PUBLIC_KEY=pk_live_xxxx
STRIPE_SECRET_KEY=sk_live_xxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxx

PAYPAL_CLIENT_ID=xxxx
PAYPAL_SECRET=xxxx

MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=xxxx
MAIL_PASSWORD=xxxx

APP_KEY=base64:LgS30IDYbT73YPEgAYmOXC1JBUOTslrFpxKJwI4TQvQ=
```

### Adım 3: Database Bağlantısı
Railway üzerinde MySQL/PostgreSQL ekle:
- Add Service → MySQL/Postgres
- Otomatik bağlanacak

### Adım 4: Deploy
```bash
git push origin main
# Railway otomatik olarak deploy edecek
```

---

## 🌐 Frontend Deployment (Vercel)

### Adım 1: Vercel Hesabı
- https://vercel.com adresine git
- GitHub ile giriş yap

### Adım 2: Proje Ekle
```bash
vercel --prod
```

VEYA Vercel Dashboard'dan:
- New Project → Import Git Repository
- untitled repo seç

### Adım 3: Environment Variables
Vercel Settings → Environment Variables:

```
VITE_API_BASE_URL=https://scout-api-production.railway.app/api
```

### Adım 4: Build & Deploy
```bash
npm run build
vercel --prod
```

---

## 📱 Mobile Deployment (Flutter)

### Android APK (Google Play Store)
```bash
cd scout_mobile
flutter build apk --release --split-per-abi
flutter build appbundle --release
```

APK yollama:
1. Google Play Console hesabı oluştur
2. scout_mobile/build/app/outputs/bundle/release/app-release.aab yükle

### iOS (Apple App Store)
```bash
flutter build ios --release
# Xcode'da build et ve App Store'a yükle
```

---

## 📊 API Endpoints (Production)

```
Backend:  https://scout-api-production.railway.app/api
Frontend: https://scout-app-production.vercel.app
```

Test et:
```bash
curl https://scout-api-production.railway.app/api/ping
```

---

## ✅ Deployment Checklist

- [ ] Database yapılandırıldı
- [ ] Environment variables ayarlandı
- [ ] SSL sertifikası aktif
- [ ] Stripe webhook yapılandırıldı
- [ ] PayPal webhook yapılandırıldı
- [ ] Email servisi test edildi
- [ ] Rate limiting ayarlandı
- [ ] Logging etkin
- [ ] Backup planı var
- [ ] CDN yapılandırıldı (optional)

