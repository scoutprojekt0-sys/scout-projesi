# ⚡ NextScout - HIZLI BAŞLANGIÇ

**Tek dosya, tüm işlemler!**

---

## 🚀 EN HIZLI YOL

### Windows:
```bash
cd e:\PhpstormProjects\untitled
BASLAT.bat
```

**Menüden seç:**
- `[1]` API Sunucusunu Başlat
- `[2]` Smoke Test (Otomatik)
- `[3]` Ana Sayfayı Aç
- `[4]` Admin Panelini Aç
- `[5]` Tüm Sayfaları Aç (5 sekme)
- `[6]` Dokümantasyonu Göster

---

## 📋 MANUEL BAŞLATMA

### 1. API Başlat
```bash
start-server.bat
```

### 2. Test Et
```bash
SMOKE_TEST.bat
```

### 3. Tarayıcıda Aç
```
Ana Sayfa:  file:///E:/PhpstormProjects/untitled/index.html
Admin:      file:///E:/PhpstormProjects/untitled/admin/index.html
```

---

## ✅ KONTROL LİSTESİ

Başlatmadan önce:
- [x] PHP 8.1+ yüklü mü?
- [x] Composer yüklü mü?
- [x] `scout_api` klasöründe `vendor` var mı?
  - Yoksa: `cd scout_api && composer install`

---

## 📚 DOKÜMANTASYON

| Dosya | İçerik |
|-------|--------|
| **BASARILI_TAMAMLANDI.md** | Genel özet, ne yapıldı |
| **CALISTIRMA_KILAVUZU.md** | Nasıl çalıştırılır |
| **TESTLER.md** | Test senaryoları |
| **GUNLUK_RAPOR.md** | Detaylı rapor |

---

## 🎯 İLK TEST

```bash
# 1. API başlat
start-server.bat

# 2. Yeni terminalde test et
curl http://127.0.0.1:8000/api/users?per_page=1

# Beklenen:
# {"ok":true,"data":{"data":[...],"total":X,...}}
```

---

## 🐛 SORUN VARSA

1. `TESTLER.md` dosyasını oku
2. Console'u kontrol et (F12)
3. `SMOKE_TEST.bat` çalıştır

---

**🎉 Başarılar!**
