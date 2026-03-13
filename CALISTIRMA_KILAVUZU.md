# 🚀 NextScout Platform - Çalıştırma Kılavuzu

**Tarih:** 4 Mart 2026  
**Versiyon:** Stabilizasyon v1.0

---

## 📦 Gereksinimler

- PHP 8.1+
- Composer
- SQLite (veya MySQL)
- Modern tarayıcı (Chrome, Firefox, Edge)

---

## 🏁 Tek Komutla Başlat

### 1️⃣ API Sunucusunu Başlat

```bash
cd e:\PhpstormProjects\untitled
start-server.bat
```

**Port:** `http://127.0.0.1:8000`

---

### 2️⃣ Frontend'i Aç

Ana dizinde `index.html` dosyasını tarayıcıda aç:

```
file:///E:/PhpstormProjects/untitled/index.html
```

---

## ✅ Smoke Test (Hızlı Doğrulama)

API'nin çalıştığını test etmek için:

```bash
SMOKE_TEST.bat
```

**Test Adımları:**
1. API Health Check
2. Register endpoint (role: player)
3. Login endpoint
4. Users list (pagination + role filter)

---

## 🔐 Kayıt ve Giriş Akışları

### Ana Sayfa (Player Kayıt)
- **Dosya:** `index.html`
- **Rol:** `player`
- **Endpoint:** `/api/auth/register`

### Antrenör Girişi
- **Dosya:** `antranor-giris.html`
- **Rol:** `coach`
- **URL:** `file:///E:/PhpstormProjects/untitled/antranor-giris.html`

### Menejer Girişi
- **Dosya:** `menejer-giris.html`
- **Rol:** `manager`
- **URL:** `file:///E:/PhpstormProjects/untitled/menejer-giris.html`

### Takım Girişi
- **Dosya:** `takim-giris.html`
- **Rol:** `team`
- **URL:** `file:///E:/PhpstormProjects/untitled/takim-giris.html`

---

## 👨‍💼 Admin Paneli

**URL:** `file:///E:/PhpstormProjects/untitled/admin/index.html`

### Özellikler:
- ✅ Tüm üyeleri görüntüleme
- ✅ Role göre filtreleme (player, manager, coach, team, scout)
- ✅ İsim/E-posta arama
- ✅ Sayfalama (20 kayıt/sayfa)
- ✅ Anlık istatistikler

### Kartlar:
1. **Toplam Üye** - Tüm kayıtlar
2. **🏃 Oyuncular** - role: player
3. **💼 Menejerler** - role: manager
4. **🎯 Antrenörler** - role: coach
5. **⚽ Takımlar** - role: team
6. **🔍 Scout'lar** - role: scout

---

## 📡 API Endpoints

### Auth
- `POST /api/auth/register` - Yeni kullanıcı kaydı
- `POST /api/auth/login` - Kullanıcı girişi
- `POST /api/auth/logout` - Çıkış (token gerekli)
- `GET /api/auth/me` - Kullanıcı bilgileri (token gerekli)

### Users
- `GET /api/users` - Kullanıcı listesi
  - **Query Params:**
    - `role` - Rol filtresi (player, manager, coach, team, scout)
    - `search` - İsim/E-posta arama
    - `per_page` - Sayfa başına kayıt (default: 20)
    - `page` - Sayfa numarası

**Örnek:**
```
GET /api/users?role=player&search=Ali&per_page=10&page=1
```

---

## 🧪 Manuel Test Senaryosu

### 1. Kayıt Testi
1. `antranor-giris.html` aç
2. "Yeni Antrenör Hesabı Oluştur" formunu doldur
3. Kayıt butonuna tıkla
4. ✅ Success mesajı görünmeli
5. Otomatik olarak `index.html`'e yönlendirilmeli

### 2. Giriş Testi
1. Aynı sayfada "Antrenör Girişi" formunu doldur
2. Giriş butonuna tıkla
3. ✅ Success mesajı görünmeli
4. Otomatik olarak `index.html`'e yönlendirilmeli

### 3. Admin Paneli Testi
1. `admin/index.html` aç
2. "🎯 Antrenörler" kartına tıkla
3. ✅ Sadece coach rolündeki kullanıcılar listelenmeli
4. Arama kutusuna isim yaz ve "Ara" butonuna tıkla
5. ✅ Filtrelenmiş sonuçlar görünmeli

---

## 🐛 Hata Mesajları

### API Çalışmıyor
```
❌ API çalışmıyor. Sunucuyu başlatın.
```
**Çözüm:** `start-server.bat` çalıştır

### Yetki Yok
```
❌ Yetki yok
```
**Çözüm:** Giriş yap, token al

### Sonuç Bulunamadı
```
Sonuç bulunamadı.
```
**Çözüm:** Farklı filtre/arama dene

---

## 📂 Değişen Dosyalar

### Backend
- `scout_api/app/Http/Controllers/Api/AuthController.php` ✅ (zaten hazır)
- `scout_api/routes/api.php` ✅ (zaten hazır)

### Frontend
- `index.html` - API base URL düzeltildi (8000)
- `antranor-giris.html` - API entegrasyonu eklendi (role: coach)
- `menejer-giris.html` - API entegrasyonu eklendi (role: manager)
- `takim-giris.html` - API entegrasyonu eklendi (role: team)
- `admin/index.html` - ✨ YENİ - Admin üye yönetimi paneli

### Scripts
- `start-server.bat` ✅ (zaten hazır)
- `SMOKE_TEST.bat` - ✨ YENİ - Otomatik test scripti

---

## 🎯 Yarın Sonrası - 3 Önemli Madde

### 1. 🔒 Güvenlik
- Admin paneline authentication ekle
- Role-based authorization (middleware)
- Rate limiting ayarla

### 2. 🛡️ Admin Auth
- Admin login sayfası oluştur
- Admin middleware ekle
- `/api/users` endpoint'ini koru

### 3. 📊 Loglama
- API request/response logları
- Kullanıcı aktivite takibi
- Error tracking sistemi

---

## 📞 Destek

Sorun yaşarsanız:
1. `SMOKE_TEST.bat` çalıştırın
2. Console'daki hata mesajlarını kontrol edin
3. Database'de veriler var mı kontrol edin:
   ```bash
   cd scout_api
   php artisan tinker
   >>> \App\Models\User::count()
   ```

---

**✅ Platform hazır! Yarın tek komutla başlayabilirsin.**
