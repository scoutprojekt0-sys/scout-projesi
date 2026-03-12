# Scout API - Flutter Mobile Integration Guide

## Hızlı Başlangıç

Mevcut Flutter uygulaması `scout_mobile/` klasöründedir.

### API Base URL

```dart
const String API_BASE_URL = 'http://localhost:8000/api';  // Development
// const String API_BASE_URL = 'https://api.nextscout.pro/api';  // Production
```

### Authentication (Sanctum Token)

```dart
// Login
POST /auth/login
Body: {
  "email": "user@example.com",
  "password": "Password123!"
}

Response: {
  "ok": true,
  "data": {
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "expires_at": "2026-03-18T10:30:00Z",
    "user": { ...user_data }
  }
}

// Token'ı header'a ekle
headers['Authorization'] = 'Bearer $token';
```

---

## 📱 Flutter Integration - Örnek Kod

### 1. **HTTP Client Setup**

```dart
// lib/services/api_service.dart
import 'package:http/http.dart' as http;

class ApiService {
  static const String baseUrl = 'http://localhost:8000/api';
  
  static String? token;
  
  static Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/auth/login'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'email': email, 'password': password}),
    );
    
    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      token = data['data']['token'];
      return data;
    }
    throw Exception('Login failed');
  }
  
  static Future<Map<String, dynamic>> getPlayer(int playerId) async {
    final response = await http.get(
      Uri.parse('$baseUrl/players/$playerId'),
      headers: {'Authorization': 'Bearer $token'},
    );
    
    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    }
    throw Exception('Failed to load player');
  }
}
```

### 2. **Oyuncu Arama**

```dart
// lib/screens/player_search_screen.dart
Future<void> searchPlayers(String position, String city, {int? ageMin, int? ageMax}) async {
  try {
    final response = await http.get(
      Uri.parse('$baseUrl/players').replace(queryParameters: {
        'position': position,
        'city': city,
        if (ageMin != null) 'age_min': ageMin.toString(),
        if (ageMax != null) 'age_max': ageMax.toString(),
      }),
      headers: {'Authorization': 'Bearer $token'},
    );
    
    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      setState(() {
        players = data['data'];
      });
    }
  } catch (e) {
    print('Error: $e');
  }
}
```

### 3. **Video Klipleri**

```dart
// lib/screens/video_screen.dart
Future<void> fetchPlayerVideos(int playerId) async {
  try {
    final response = await http.get(
      Uri.parse('$baseUrl/users/$playerId/videos'),
      headers: {'Authorization': 'Bearer $token'},
    );
    
    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      setState(() {
        videos = data['data'];
      });
    }
  } catch (e) {
    print('Error: $e');
  }
}
```

### 4. **İstatistik Görüntüleme**

```dart
// lib/screens/player_stats_screen.dart
Future<void> fetchPlayerStats(int playerId) async {
  try {
    final response = await http.get(
      Uri.parse('$baseUrl/players/$playerId/statistics'),
      headers: {'Authorization': 'Bearer $token'},
    );
    
    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      setState(() {
        stats = data['data'];
      });
    }
  } catch (e) {
    print('Error: $e');
  }
}
```

### 5. **Sosyal Medya Hesapları**

```dart
// lib/screens/social_media_screen.dart
Future<void> fetchSocialMedia(int userId) async {
  try {
    final response = await http.get(
      Uri.parse('$baseUrl/users/$userId/social-media'),
      headers: {'Authorization': 'Bearer $token'},
    );
    
    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      setState(() {
        socialAccounts = data['data'];
      });
    }
  } catch (e) {
    print('Error: $e');
  }
}
```

---

## 🔐 Token Yönetimi

```dart
// lib/services/token_service.dart
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class TokenService {
  static const storage = FlutterSecureStorage();
  
  static Future<void> saveToken(String token) async {
    await storage.write(key: 'auth_token', value: token);
  }
  
  static Future<String?> getToken() async {
    return await storage.read(key: 'auth_token');
  }
  
  static Future<void> deleteToken() async {
    await storage.delete(key: 'auth_token');
  }
  
  static Future<void> refreshToken() async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/refresh'),
        headers: {'Authorization': 'Bearer $currentToken'},
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        await saveToken(data['data']['token']);
      }
    } catch (e) {
      print('Error: $e');
    }
  }
}
```

---

## 📱 pubspec.yaml Gereklilikleri

```yaml
dependencies:
  flutter:
    sdk: flutter
  http: ^1.1.0
  flutter_secure_storage: ^9.0.0
  provider: ^6.0.0
  intl: ^0.18.0  # Locale support
```

---

## 🚀 Deploy ve Production

### Backend Deployment
```bash
# Production'da environment variables ayarla
APP_ENV=production
STRIPE_SECRET=sk_live_...
PAYPAL_CLIENT_ID=...
PAYPAL_SECRET=...
```

### Flutter Build

```bash
# Android
flutter build apk --release

# iOS
flutter build ios --release

# Web
flutter build web --release
```

---

## 🔗 Tüm API Endpoint'leri

```
# Oyuncu
GET    /players                          # Tüm oyuncuları listele
GET    /players/{id}                     # Oyuncu detayı
PUT    /players/{id}                     # Oyuncu güncelle
GET    /players/{id}/statistics          # Oyuncu istatistikleri
GET    /players/{id}/videos              # Oyuncu videoları

# Transfer
GET    /transfers                        # Transfer listesi
GET    /transfers/{id}                   # Transfer detayı
GET    /transfers/player/{playerId}/timeline

# Lig ve Kulüp
GET    /leagues                          # Tüm ligler
GET    /leagues/{league}/standings       # Puan tablosu
GET    /leagues/{league}/top-scorers     # Top skorer
GET    /clubs                            # Tüm kulüpler
GET    /clubs/{id}/squad                 # Kulüp kadrosu

# Video
GET    /users/{userId}/videos            # Oyuncu videoları
GET    /videos/{id}                      # Video detayı
POST   /videos                           # Video yükle
GET    /videos/trending                  # Trending videolar
GET    /videos/tag/{tag}                 # Tag'a göre videolar

# Sosyal Medya
GET    /users/{userId}/social-media      # Sosyal medya hesapları
POST   /social-media                     # Sosyal medya hesabı ekle
PATCH  /social-media/{id}                # Güncelle
DELETE /social-media/{id}                # Sil

# İstatistikler
GET    /players/{playerId}/statistics    # Oyuncu istatistikleri
POST   /player-statistics                # İstatistik kayıt
GET    /seasons/{season}/top-scorers     # Sezon top skorer

# Auth
POST   /auth/login                       # Giriş
POST   /auth/register                    # Kayıt
POST   /auth/logout                      # Çıkış
POST   /auth/refresh                     # Token yenile
GET    /auth/me                          # Profil bilgileri
```

---

## 🧪 Test ile API Testi

```bash
# Flutter debug
flutter run -v

# API test (curl)
curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost:8000/api/players/1

# Postman
Postman > Import > scout_api_pr_clean/postman/Scout_API_E2E.postman_collection.json
```

---

## 📞 Hata Çözümü

| Hata | Çözüm |
|------|-------|
| 401 Unauthorized | Token geçersiz/süresi dolmuş. `/auth/refresh` ile yenile |
| 403 Forbidden | Yetki yok. User role kontrol et |
| 404 Not Found | API route mevcut değil. Dokümantasyonu kontrol et |
| 500 Server Error | Backend hatası. Server logs kontrol et |

---

**Flutter uygulaması ready! 🚀**

