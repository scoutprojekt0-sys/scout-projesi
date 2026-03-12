# SCOUT API - HANGİ DOSYAYI ÇALIŞTIRMALIYIM?

## DURUMA GÖRE HANGİ SCRIPT

### 1️⃣ İLK KURULUM (Composer YOK)

```
INSTALL_COMPOSER.bat
```
↓ Sonra
```
SETUP_COMPLETE.bat
```

### 2️⃣ COMPOSER VAR AMA BAĞIMLILIKLAR YOK

```
SETUP_COMPLETE.bat
```

### 3️⃣ HER ŞEY KURULU, SADECE SUNUCU BAŞLAT

```
START_NO_COMPOSER.bat
```

## HIZLI TEST

Composer var mı kontrol et:
```cmd
composer --version
```

- **Çıktı gelirse**: `SETUP_COMPLETE.bat` çalıştır
- **Hata verirse**: `INSTALL_COMPOSER.bat` çalıştır

## ADIM ADIM (İLK KURULUM)

1. **INSTALL_COMPOSER.bat** ← ÇİFT TIKLA
2. Kurulum bitince terminali kapat
3. **SETUP_COMPLETE.bat** ← ÇİFT TIKLA
4. Sunucu başlayınca tarayıcıda aç: http://localhost:8000/api/ping

## SORUN GİDERME

### "PHP bulunamadı"
PHP kurulu değil. Kurulum gerekli.

### "composer.phar created"
Normal! Şimdi `SETUP_COMPLETE.bat` çalıştır.

### Port kullanımda
```cmd
php artisan serve --port=8001
```

## TÜM SCRIPTLER

| Dosya | Ne Yapar |
|-------|----------|
| `INSTALL_COMPOSER.bat` | Composer'ı indir ve kur |
| `SETUP_COMPLETE.bat` | Tam kurulum + sunucu başlat |
| `START.bat` | Composer install + sunucu başlat |
| `START_NO_COMPOSER.bat` | Sadece sunucu başlat |
| `TEST_API.bat` | API endpoint'lerini test et |

## ŞİMDİ NE YAPMALIYIM?

**EN KOLAY YOL**:

1. `INSTALL_COMPOSER.bat` dosyasına çift tıkla
2. Bekle
3. `SETUP_COMPLETE.bat` dosyasına çift tıkla
4. Tarayıcıda http://localhost:8000/api/ping aç

**BİTTİ!** 🎉
