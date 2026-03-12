# Scout API - Kurulum ve Başlatma Rehberi

## 🚀 Hızlı Başlangıç

### **Seçenek 1: Otomatik Kurulum (Önerilen)**

1. **PowerShell veya CMD'yi Yönetici Olarak Aç**

2. **Ana klasöre git:**
   ```bash
   cd c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean
   ```

3. **Master Start Script'i çalıştır:**
   ```bash
   START_ALL.bat
   ```

4. **Menüden seç:**
   - `[1]` = Sadece Backend
   - `[2]` = Sadece Frontend  
   - `[3]` = Sadece Mobile
   - `[4]` = Backend + Frontend
   - `[5]` = Hepsini (Backend + Frontend + Mobile)
   - `[6]` = Veritabanını sıfırla
   - `[7]` = Sorun giderici

---

## 📋 Manuel Kurulum (Eğer otomatik çalışmazsa)

### **Backend (PHP/Laravel)**

1. Backend klasörüne git:
   ```bash
   cd c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean
   ```

2. Composer dependencies kur:
   ```bash
   php composer.phar install
   ```

3. `.env` dosyası oluştur:
   ```bash
   copy .env.example .env
   ```

4. APP_KEY oluştur:
   ```bash
   php artisan key:generate
   ```

5. Database migrate et:
   ```bash
   php artisan migrate --force
   ```

6. Seeders çalıştır (test verileri):
   ```bash
   php artisan db:seed --force
   ```

7. Backend sunucusunu başlat:
   ```bash
   php artisan serve
   ```

**✅ Backend çalışırsa:** `http://localhost:8000` adresinde ping test et:
```bash
curl http://localhost:8000/api/ping
```

---

### **Frontend (Node.js/Vue/React)**

1. Frontend klasörüne git:
   ```bash
   cd c:\Users\Hp\Desktop\PhpstormProjects\untitled
   ```

2. NPM dependencies kur:
   ```bash
   npm install
   ```

3. Dev sunucusunu başlat:
   ```bash
   npm run dev
   ```

**✅ Frontend çalışırsa:** `http://localhost:3000` (veya konsola yazdığı adres)

---

### **Mobile (Flutter)**

1. Mobile klasörüne git:
   ```bash
   cd c:\Users\Hp\Desktop\PhpstormProjects\scout_mobile
   ```

2. Flutter dependencies kur:
   ```bash
   flutter pub get
   ```

3. Android/iOS emülatörünü başlat ve çalıştır:
   ```bash
   flutter run
   ```

---

## 🔍 Test Kullanıcıları

Backend'i ilk kez çalıştırdığında otomatik oluşturulur:

| Email | Şifre | Rol |
|-------|-------|-----|
| `player@test.com` | `Password123!` | Oyuncu |
| `scout@test.com` | `Password123!` | Scout |
| `team@test.com` | `Password123!` | Takım |

---

## 🐛 Sık Sorunlar ve Çözümleri

### **Problem: "Port 8000 zaten kullanılıyor"**
```bash
# Farklı port kullan:
php artisan serve --port=8001
```

### **Problem: "Fatal error in vendor"**
```bash
# Composer'ı yeniden kur:
rm -r vendor
php composer.phar install
```

### **Problem: "npm ERR! gyp ERR!"**
```bash
# NPM cache temizle:
npm cache clean --force
npm install
```

### **Problem: "SQLSTATE[HY000]: General error"**
```bash
# Database'i sıfırla:
php artisan migrate:refresh --seed --force
```

### **Problem: "Cannot find .env"**
```bash
# .env oluştur:
copy .env.example .env
php artisan key:generate
```

---

## 🌐 Tüm Adresler

| Uygulama | Adres | Port |
|----------|-------|------|
| **Backend API** | `http://localhost:8000` | 8000 |
| **Frontend** | `http://localhost:3000` | 3000 |
| **API Docs** | `http://localhost:8000/api/locales` | 8000 |
| **Ping Test** | `http://localhost:8000/api/ping` | 8000 |
| **Mobile** | Cihaz/Emülatöre yüklenir | N/A |

---

## 📖 API Endpoint Örnekleri

```bash
# Dilleri listele
curl http://localhost:8000/api/locales

# Oyuncu listesi
curl http://localhost:8000/api/players

# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"player@test.com","password":"Password123!"}'

# Video klipleri
curl http://localhost:8000/api/videos/trending
```

---

## 💡 İpuçları

- **Backend çalışmıyor ama hata görmüyorsun?** → `storage/logs/laravel.log` kontrol et
- **Frontend port 3000'de açılmıyor?** → Konsolu kontrol et, başka port önerebilir
- **Database hatası alıyorsun?** → `database.sqlite` dosyasını sil ve migration yeniden çalıştır
- **Localhost erişilemiyor?** → Firewall ayarlarını kontrol et

---

## 🆘 Yardım Gerekirse

Hataları şu sırayla kontrol et:
1. `php -v` → PHP yüklü mü?
2. `node -v` → Node.js yüklü mü?
3. `flutter --version` → Flutter yüklü mü?
4. Log dosyalarını kontrol et: `storage/logs/laravel.log`

**Sorun gidericiyi çalıştır:**
```bash
START_ALL.bat
# Seçenek 7'yi seç (Sorun giderici)
```

---

**Happy Coding! 🚀**

