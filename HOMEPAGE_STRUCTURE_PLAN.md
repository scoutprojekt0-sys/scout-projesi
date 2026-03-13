# 🏠 ANASAYFA YAPISI PLANI

## 📐 3 ANA BÖLÜM

---

## 1️⃣ **⚽ SCOUT PLATFORM** (Üst Bölüm)
> Oyuncu Keşfetme & Profil Yönetimi

```
SCOUT PLATFORM
├─ 🎯 OYUNCU KEŞFİ
│  ├─ En İyi Oyuncular (Rating'e göre)
│  ├─ Yeni Oyuncular
│  ├─ Profil Kartları (Futbol/Basketbol/Voleybol)
│  └─ Anonim Mesajlaşma (Menajer Feature)
│
├─ 📱 AMATÖR FUTBOL
│  ├─ Amatör Oyuncu Profilleri
│  ├─ Deneme Maçı Talebi
│  ├─ Topluluk Etkinlikleri
│  └─ Serbest Oyuncu İlanları
│
├─ 📊 İSTATİSTİKLER
│  ├─ Oyuncu İstatistikleri
│  ├─ Kişisel İstatistikler
│  └─ Takım İstatistikleri
│
├─ 👥 MENAJER & ANTRENÖR
│  ├─ Menajer Profilleri
│  ├─ Antrenör Profilleri
│  └─ Scouting Raporları
│
└─ 🎓 HUKUK SİSTEMİ
   ├─ Avukat Profilleri
   ├─ Sözleşme Yönetimi
   └─ Müzakere Sistemi
```

---

## 2️⃣ **🎯 RADAR** (Orta Bölüm)
> Trendler, Canlı İçerik & Haberler

```
RADAR
├─ 🔥 TRENDLER
│  ├─ Haftalık En Popüler Oyuncular
│  ├─ Trending Up (Yükselen Yıldızlar)
│  ├─ Trending Down (Düşen Oyuncular)
│  └─ Scout Tarafından Seçilmiş
│
├─ ⚽ CANLI MAÇLAR & SONUÇLAR
│  ├─ Canlı Maç Spikeri
│  ├─ Canlı Skor
│  ├─ Maç İstatistikleri
│  ├─ Gol/Asist/Kartlar
│  └─ Tamamlanan Maç Sonuçları
│
├─ 🏆 LİG TABLOSU
│  ├─ Puan Durumu
│  ├─ Gol Krallığı
│  ├─ Asist Krallığı
│  └─ Fixture Takvimi
│
├─ 📰 HABERLER
│  ├─ Transfer Haberleri
│  ├─ Oyuncu Haberleri
│  ├─ Takım Haberleri
│  └─ Lig Haberleri
│
└─ 👁️ SCOUT AKTIVITELERI
   ├─ Yeni Scout Raporları
   ├─ Scout İncelemeler
   └─ Video Analizler
```

---

## 3️⃣ **💰 TRANSFERMARKET** (Alt Bölüm)
> Piyasa, Değerler & Transfer

```
TRANSFERMARKET
├─ 💎 PROFESYONEL PİYASA
│  ├─ Piyasa Değeri (Futbolcu)
│  ├─ Piyasa Değeri (Menajer)
│  ├─ Piyasa Değeri (Antrenör)
│  ├─ Transfer Haberleri (Resmi)
│  ├─ Transfer Takvimi
│  └─ Transfer Pazarı Analizi
│
├─ 🏪 AMATÖR PİYASA (YENI)
│  ├─ Amatör Oyuncu Piyasa Değeri
│  ├─ Tıklanan Puan Sistemi
│  ├─ Performans Puanları
│  ├─ Trend Analizi (Haftalık)
│  ├─ Leaderboard (Top 50)
│  └─ Transfer Teklifi Sistemi
│
├─ 📈 PAZAR ANALİZİ
│  ├─ Yükselen Değerler
│  ├─ Düşen Değerler
│  ├─ Sabit Değerler
│  ├─ Pazar İstatistikleri
│  └─ Trend Grafikleri
│
└─ 🎪 TRANSFER YAZARI
   ├─ Transfer Spekülasyonları
   ├─ Potansiyel Transferler
   ├─ Yıldız Oyuncular
   └─ Gizli Talepler
```

---

## 📊 SAYFANIN AKIŞI

```
┌─────────────────────────────────────┐
│          SCOUT PLATFORM             │
│  (Oyuncu Keşfi, Profil, Hukuk)     │
├─────────────────────────────────────┤
│                                     │
│  Top Oyuncular  |  En İyi Menajer  │
│  Profil Kartlar | Antrenör Kartlar │
│  Deneme Maçı    | Avukat Profili   │
│                                     │
├─────────────────────────────────────┤
│           RADAR                     │
│  (Trendler, Canlı, Haberler)      │
├─────────────────────────────────────┤
│                                     │
│  Trending Up    |  Canlı Maçlar    │
│  Haftalık Top   |  Maç Sonuçları   │
│  Scout Seçtiler |  Lig Tablosu     │
│  Haberler       |  Fixture         │
│                                     │
├─────────────────────────────────────┤
│        TRANSFERMARKET               │
│  (Piyasa Değeri, Transfer)         │
├─────────────────────────────────────┤
│                                     │
│  Profesyonel Piyasa | Amatör Market│
│  Piyasa Değeri      | Tıkla Puan   │
│  Transfer Haberleri | Leaderboard  │
│  Pazar Analizi      | Trengler     │
│  Transfer Yazarı    | Transfer Teklif
│                                     │
└─────────────────────────────────────┘
```

---

## 🔌 BACKEND YAPISI

```
HomeController
├─ getScoutPlatformSection()
│  ├─ getTopPlayers()
│  ├─ getManagerProfiles()
│  ├─ getCoachProfiles()
│  ├─ getLawyerProfiles()
│  └─ getAmateurFootball()
│
├─ getRadarSection()
│  ├─ getTrendingPlayers()
│  ├─ getLiveMatches()
│  ├─ getLeagueStandings()
│  ├─ getNews()
│  └─ getScoutActivities()
│
└─ getTransferMarketSection()
   ├─ getProfessionalMarket()
   ├─ getAmateurMarket()
   ├─ getMarketAnalysis()
   └─ getTransferNews()
```

---

## 💾 ÖZET

| Bölüm | Başlık | İçerik | Endpoint |
|-------|--------|--------|----------|
| 1 | ⚽ SCOUT PLATFORM | Oyuncu Keşfi, Profil, Hukuk | /api/scout/* |
| 2 | 🎯 RADAR | Trendler, Canlı, Haberler | /api/radar/* |
| 3 | 💰 TRANSFERMARKET | Piyasa, Transfer | /api/market/* |

---

**Bu yapı, TransferMarket'e benzer, ama 3 ana bölümde organize!**
