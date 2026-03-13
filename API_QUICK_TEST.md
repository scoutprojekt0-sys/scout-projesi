# 🚀 API ENTEGRASYONU - HIZLI TEST

## ⚡ Hızlı Başlangıç

### **1. Laravel Server Başlat:**
```bash
cd scout_api
php artisan serve
```

### **2. Tarayıcıda Aç:**
```
http://localhost:8000
```

### **3. Console'u Aç:**
```
F12 veya Ctrl+Shift+I
```

---

## ✅ Ne Göreceksin?

### **Bildirim Badge:**
- Bildirim ikonu üzerinde **kırmızı badge**
- İçinde sayı: **3** veya **5**
- API'den dinamik olarak gelir

### **Canlı Maç Badge:**
- "Canlı Maçlar" butonunda **kırmızı badge**
- İçinde sayı: **12**
- API'den dinamik olarak gelir

---

## 🔍 Console'da Kontrol

```javascript
// Göreceğin loglar:
"Bildirim sayısı: 5"
"Canlı maç sayısı: 12"
```

---

## 🧪 API Test (Manual)

### **Browser'da Direkt Aç:**

**Bildirimler:**
```
http://localhost:8000/api/notifications/count
```

**Canlı Maçlar:**
```
http://localhost:8000/api/live-matches/count
```

**JSON Response Göreceksin:**
```json
{
  "success": true,
  "count": 12,
  "has_live_matches": true
}
```

---

## 🔄 Auto Refresh

- **İlk yükleme:** Sayfa açılır açılmaz
- **Periyodik:** Her 30 saniyede bir otomatik güncellenir
- Console'da göreceksin

---

## 🎯 Sorun Varsa?

### **Badge Görünmüyor:**
1. Console'da hata var mı?
2. Laravel server çalışıyor mu?
3. Network tab'da API çağrıları başarılı mı?

### **API Hata Veriyor:**
```bash
# Cache temizle
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

---

## ✨ Çalışıyor mu?

✅ Bildirim badge'i var mı?
✅ Canlı maç badge'i var mı?
✅ Console'da log var mı?
✅ 30 saniye sonra güncelleniyor mu?

**Hepsi YES ise başarılı! 🎉**
