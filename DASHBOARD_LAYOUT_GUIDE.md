# 🏠 ANASAYFA (DASHBOARD) - KOMPLE LAYOUT REHBERI

## 📐 ANASAYFA YAPISI

Anasayfa 3 ana bölümden oluşacak:

```
┌─────────────────────────────────────────────────────────────┐
│                      TOP NAVIGATION BAR                     │
│  Logo  |  Search  |  🔔 Notifications(3)  |  👤 Profile   │
└─────────────────────────────────────────────────────────────┘
┌──────────────────────────────────────────────────────────────┐
│ SIDEBAR                 │           MAIN CONTENT              │
│ ───────────────────────┼──────────────────────────────────   │
│ 🏠 Ana Sayfa           │  Welcome Section                    │
│ 🔍 Oyuncu Ara          │  ┌──────────────────────────────┐   │
│ 📱 Mesajlar (5)        │  │ Merhaba, Ahmet Demir!        │   │
│ 🔔 Bildirimler (3)     │  │ Spor: ⚽ Futbol              │   │
│ ⭐ Favori Oyuncular    │  │ Pozisyon: Forvet            │   │
│ 📊 İstatistiklerim     │  │ Rating: ⭐⭐⭐⭐⭐ 4.8/5      │   │
│ 🏆 Başarılarım         │  │ [Profili Düzenle] [Devamını Gör]│
│ 🎖️ Sertifikalar        │  └──────────────────────────────┘   │
│ ⚙️ Ayarlar             │                                     │
│ ❓ Yardım              │  Quick Stats                        │
│ 📤 Çıkış              │  ┌──────────────┬──────────────┐   │
│                       │  │ Görünüm: 4.2K │ Favori: 156   │   │
│                       │  │ Beğeni: 2.4K  │ Yorum: 89     │   │
│                       │  └──────────────┴──────────────┘   │
│                       │                                     │
│                       │  Recent Activity                    │
│                       │  ┌──────────────────────────────┐   │
│                       │  │ 👥 Menajer seni açtı        │   │
│                       │  │ 💬 Yeni mesajın var         │   │
│                       │  │ ⚽ Maç sonuç: 3-1 Kazandık   │   │
│                       │  └──────────────────────────────┘   │
│                       │                                     │
│                       │  Recommended Players                │
│                       │  ┌──────────┬──────────┬──────────┐│
│                       │  │ [Card1]  │ [Card2]  │ [Card3]  ││
│                       │  │ Ali Y.   │ Mehmet K.│ Zeynep Ç.││
│                       │  │ ⭐4.7    │ ⭐4.9    │ ⭐4.6    ││
│                       │  └──────────┴──────────┴──────────┘│
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 ANASAYFA BÖLÜMLERİ

### **1. TOP NAVIGATION BAR**
```
├─ Logo (Scout Platform)
├─ Search Bar (Oyuncu, Takım, Makale)
├─ 🔔 Notifications (Dropdown - 5 bildirim)
├─ 💬 Messages (Dropdown - Unread sayısı)
├─ 👤 Profile (Dropdown Menu)
└─ ⚙️ Ayarlar / Çıkış
```

### **2. SIDEBAR (Sol Menü)**
```
NAVIGATION
├─ 🏠 Ana Sayfa (Active)
├─ 🔍 Oyuncu Ara
├─ 📱 Mesajlar
├─ 🔔 Bildirimler
├─ ⭐ Favorilerim

MY DATA
├─ 📊 İstatistiklerim
├─ 🏆 Başarılarım
├─ 🎖️ Sertifikalar (Antrenör)

TOOLS
├─ 🎬 Video Portföyüm
├─ 📈 Transfer Pazarı
├─ 👥 Takım Yönetimi (Menajer)

SETTINGS
├─ ⚙️ Ayarlar
├─ ❓ Yardım & SSS
└─ 📤 Çıkış
```

### **3. MAIN CONTENT (Ana İçerik)**

#### **A. Welcome Card**
```
┌─────────────────────────────────┐
│ Merhaba, Ahmet Demir!          │
│                                │
│ Spor: ⚽ Futbol                │
│ Pozisyon: Forvet              │
│ Yaş: 24 | Boy: 180cm          │
│ Rating: ⭐⭐⭐⭐⭐ 4.8/5        │
│                                │
│ [Profili Düzenle] [Devamını Gör]│
└─────────────────────────────────┘
```

#### **B. Quick Stats (3 Kart)**
```
┌──────────────┬──────────────┬──────────────┐
│ 👁️ Görünüm   │ 📌 Favori   │ ❤️ Beğeni   │
│ 4,287       │ 156         │ 2,400       │
└──────────────┴──────────────┴──────────────┘
```

#### **C. Recent Activity (Haber Akışı)**
```
┌───────────────────────────────────┐
│ Recent Activity                   │
├───────────────────────────────────┤
│ 👥 Halit Yılmaz seni açtı    │ 2h │
│ 💬 Yeni mesajın var             │ 5h │
│ ⚽ Maç sonuç: 3-1 Kazandık     │ 1d │
│ 🤝 Menajerin seni favori etti  │ 1d │
│ 📰 Yeni lig haberi geldi       │ 2d │
└───────────────────────────────────┘
```

#### **D. Recommended Players (3-6 Kart)**
```
┌──────────────┬──────────────┬──────────────┐
│ [CARD 1]     │ [CARD 2]     │ [CARD 3]     │
│ Ali Yıldız   │ Mehmet Kaya  │ Zeynep Çöl   │
│ Forvet       │ Orta Saha    │ Hitter       │
│ ⭐⭐⭐⭐⭐   │ ⭐⭐⭐⭐⭐   │ ⭐⭐⭐⭐⭐   │
│ 4.7/5        │ 4.9/5        │ 4.6/5        │
│              │              │              │
│ [Mesaj] [+]  │ [Mesaj] [+]  │ [Mesaj] [+]  │
└──────────────┴──────────────┴──────────────┘
```

#### **E. News & Updates**
```
┌──────────────────────────────────┐
│ News & Updates                   │
├──────────────────────────────────┤
│ 📰 Yeni Transfer Haberler       │
│    - 5 oyuncu transferi haberi  │
│ ⚽ Lig Puan Durumu Güncellendi  │
│ 🏆 Turnuva Başladı             │
│ 💬 Mesajlaşma İpuçları         │
└──────────────────────────────────┘
```

---

## 🎬 RESPONSIVE GRID

### **DESKTOP (1920px)**
```
┌─────────┬──────────────────────────────────────┐
│ SIDEBAR │     MAIN CONTENT (3 Column Grid)     │
│ (240px) │  ┌────────────┬────────────┐         │
│         │  │   Card 1   │   Card 2   │         │
│         │  │ (48% width)│ (48% width)│         │
│         │  ├────────────┴────────────┤         │
│         │  │   Card 3 (Full Width)  │         │
│         │  └────────────────────────┘         │
└─────────┴──────────────────────────────────────┘
```

### **TABLET (768px)**
```
┌─────────────────────────────────┐
│ TOP NAV (Collapse to Hamburger) │
├─────────────────────────────────┤
│ SIDEBAR (Collapsed/Toggle)      │
├─────────────────────────────────┤
│   MAIN (2 Column Grid)          │
│  ┌────────────┬────────────┐   │
│  │   Card 1   │   Card 2   │   │
│  ├────────────┴────────────┤   │
│  │      Card 3 (Full)      │   │
│  └────────────────────────┘   │
└─────────────────────────────────┘
```

### **MOBILE (375px)**
```
┌──────────────────┐
│ TOP NAV (Minimal)│
├──────────────────┤
│ MAIN (1 Column)  │
│ ┌──────────────┐ │
│ │   Card 1     │ │
│ │ (Full Width) │ │
│ ├──────────────┤ │
│ │   Card 2     │ │
│ ├──────────────┤ │
│ │   Card 3     │ │
│ └──────────────┘ │
└──────────────────┘
```

---

## 🛠️ ROLE'A GÖRE DASHBOARD

### **FUTBOLCU DASHBOARDİ**
```
Welcome Card (Profil özeti)
├─ Stats (Görünüm, Beğeni, Favori)
├─ Recent Activity (Kim baktı, Mesajlar, vb)
├─ Recommended Clubs (Takım önerileri)
├─ Latest News (Futbol haberleri)
└─ Upcoming Matches (Gelecek maçlar)
```

### **MENAJER DASHBOARDİ**
```
Welcome Card (Profil özeti)
├─ Stats (Yönetilen takım, Oyuncu, vb)
├─ Quick Search (Oyuncu ara - prominent)
├─ Recommended Players (Önerilen oyuncular)
├─ League Standings (Lig tablosu)
├─ Transfer News (Transfer haberleri)
└─ Upcoming Matches (Takım maçları)
```

### **ANTRENÖR DASHBOARDİ**
```
Welcome Card (Profil özeti)
├─ Stats (Eğitilen oyuncu, Başarı oranı)
├─ Recommended Players (Antrenman ihtiyacı)
├─ Training Schedule (Antrenman takvimi)
├─ Certification (Sertifikalar)
├─ News (Antrenörlük haberleri)
└─ Latest Techniques (Son teknikler)
```

---

## 📱 ÖNE ÇIKAN BUTONLAR

### **HEMEN ERIŞIM (Quick Access)**
```
┌────────────┬────────────┬────────────┬────────────┐
│ 📱 Mesaj   │ 🔍 Ara    │ ⭐ Favori  │ 📊 İstat   │
│ Gönder     │ Oyuncu     │ Listesi    │ lerim      │
└────────────┴────────────┴────────────┴────────────┘
```

---

## 🎨 RENK ŞEMASI

```
Primary:    #667eea (Mavi-Mor)
Secondary:  #764ba2 (Mor)
Background: #f9fafb (Açık Gri)
Text:       #1f2937 (Koyu Gri)
Border:     #e5e7eb (Hafif Gri)
Success:    #10b981 (Yeşil)
Warning:    #f59e0b (Turuncu)
```

---

## 📊 DASHBOARDİN KOMPONENTLERİ

| Bileşen | Konum | Genişlik | Yükseklik |
|---------|-------|----------|-----------|
| Welcome Card | Top Left | Full | 120px |
| Stats Cards | Below Welcome | Full (3 Card) | 80px |
| Recent Activity | Left Side | 50% | 250px |
| Quick Actions | Right Side | 50% | 200px |
| Recommended | Full | Full | Variable |
| News | Full | Full | Variable |

---

## 🚀 DASHBOARDİ RENDER ETMEK

Backend'e /api/dashboard endpoint'i ekleyeceğim:

```php
GET /api/dashboard
Response:
{
  "user": { ... },
  "stats": { ... },
  "recent_activity": [ ... ],
  "recommended_players": [ ... ],
  "news": [ ... ],
  "upcoming_matches": [ ... ]
}
```

---

## ✅ ÖZET

**Anasayfa şu şekilde organize olacak:**

1. **TOP BAR** - Arama, Bildirimler, Profil
2. **SIDEBAR** - Navigasyon, Menüler
3. **MAIN CONTENT:**
   - Welcome Card
   - Quick Stats
   - Recent Activity
   - Recommended Players
   - News & Updates
   - Upcoming Events

**Responsive:**
- Desktop: 3 Kolon
- Tablet: 2 Kolon
- Mobile: 1 Kolon

**Role'a göre:**
- Futbolcu: Scout View, Mesajlar, İstatistikler
- Menajer: Oyuncu Arama, Takım Yönetimi
- Antrenör: Eğitim Planı, Sertifikalar
