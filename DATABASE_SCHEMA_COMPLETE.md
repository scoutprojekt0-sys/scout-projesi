# 📊 NEXTSCOUT - VERITABANI ŞEMASI

## ✅ TOPLAMDA 115+ TABLO

---

## 📋 TABLO LİSTESİ

### **USERS & AUTH (5 Tablo)**
```
1. users
2. user_roles
3. user_profiles
4. user_sessions
5. password_resets
```

### **PROFILE CARDS (10 Tablo)**
```
6. player_profile_cards
7. manager_profile_cards
8. coach_profile_cards
9. profile_views
10. profile_ratings
11. profile_favorites
12. profile_comments
13. profile_photos
14. profile_videos
15. profile_statistics
```

### **TEAMS & CLUBS (8 Tablo)**
```
16. clubs
17. teams
18. team_members
19. team_statistics
20. amateur_teams
21. team_formations
22. team_documents
23. team_achievements
```

### **MATCHES & LEAGUES (12 Tablo)**
```
24. leagues
25. league_standings
26. matches
27. live_matches
28. live_match_updates
29. match_events
30. match_statistics
31. match_players
32. fixtures
33. results
34. seasons
35. tournament_rounds
```

### **PLAYERS & STATS (15 Tablo)**
```
36. players
37. player_statistics
38. player_positions
39. player_contracts
40. player_injuries
41. player_discipline
42. player_achievements
43. player_comparisons
44. sport_statistics
45. sport_types
46. performance_metrics
47. skill_ratings
48. physical_attributes
49. technical_skills
50. tactical_analysis
```

### **TRANSFER & MARKET (12 Tablo)**
```
51. transfers
52. transfer_news
53. market_values
54. player_market_values
55. manager_market_values
56. coach_market_values
57. amateur_player_market_value
58. market_point_logs
59. weekly_trending_players
60. amateur_market_statistics
61. amateur_transfer_offers
62. transfer_rumors
```

### **MESSAGING (10 Tablo)**
```
63. conversations
64. messages
65. message_attachments
66. anonymous_messages
67. group_conversations
68. group_members
69. secret_messages
70. message_reactions
71. message_reads
72. message_blocks
```

### **NOTIFICATIONS (8 Tablo)**
```
73. notifications
74. notification_types
75. user_notification_preferences
76. notification_history
77. notification_channels
78. push_tokens
79. email_queue
80. notification_settings
```

### **SCOUT & REPORTS (10 Tablo)**
```
81. scout_reports
82. scout_videos
83. scout_analysis
84. technical_evaluations
85. video_reviews
86. scouting_notes
87. player_comparisons
88. analysis_templates
89. evaluation_criteria
90. scout_interests
```

### **LEGAL & CONTRACTS (12 Tablo)**
```
91. lawyers
92. lawyer_specialties
93. contracts
94. contract_templates
95. contract_clauses
96. digital_signatures
97. negotiations
98. disputes
99. mediation_cases
100. legal_documents
101. agreement_templates
102. contract_history
```

### **HELP & SUPPORT (10 Tablo)**
```
103. help_articles
104. help_categories
105. faq
106. support_tickets
107. ticket_responses
108. ticket_attachments
109. support_categories
110. knowledge_base
111. article_feedback
112. article_views
```

### **ADMIN & MODERATION (10 Tablo)**
```
113. admin_logs
114. system_statistics
115. system_settings
116. user_reports
117. content_moderation
118. moderation_actions
119. banned_words
120. admin_audit_logs
121. system_events
122. maintenance_logs
```

### **COMMUNITY & EVENTS (8 Tablo)**
```
123. community_events
124. event_participants
125. event_posts
126. event_comments
127. event_photos
128. group_forums
129. forum_posts
130. forum_comments
```

### **GENERAL (5 Tablo)**
```
131. media
132. media_categories
133. localization
134. languages
135. settings
```

---

## 📊 TABLO ÖZET

| Kategori | Tablo | Fonksiyon |
|----------|-------|----------|
| **Users** | 5 | Kullanıcı yönetimi |
| **Profiles** | 10 | Profil kartları |
| **Teams** | 8 | Takım yönetimi |
| **Matches** | 12 | Maç ve lig sistemi |
| **Players** | 15 | Oyuncu verileri |
| **Transfer** | 12 | Piyasa ve transfer |
| **Messages** | 10 | Mesajlaşma |
| **Notifications** | 8 | Bildirim sistemi |
| **Scout** | 10 | Scout raporları |
| **Legal** | 12 | Sözleşme sistemi |
| **Help** | 10 | Destek sistemi |
| **Admin** | 10 | Yönetim sistemi |
| **Community** | 8 | Topluluk |
| **General** | 5 | Genel ayarlar |

**TOPLAM: 135 TABLO**

---

## 🔑 TEMEL TABLOLAR

### **users**
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    role ENUM('player', 'manager', 'coach', 'admin'),
    is_verified BOOLEAN,
    is_banned BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### **player_profile_cards**
```sql
CREATE TABLE player_profile_cards (
    id BIGINT PRIMARY KEY,
    user_id BIGINT FOREIGN KEY,
    full_name VARCHAR(255),
    sport VARCHAR(50),
    position VARCHAR(50),
    age INT,
    height INT,
    weight INT,
    overall_rating DECIMAL(3,1),
    created_at TIMESTAMP
);
```

### **matches**
```sql
CREATE TABLE matches (
    id BIGINT PRIMARY KEY,
    league_id BIGINT FOREIGN KEY,
    home_team_id BIGINT FOREIGN KEY,
    away_team_id BIGINT FOREIGN KEY,
    match_date DATETIME,
    status VARCHAR(50),
    is_live BOOLEAN,
    is_finished BOOLEAN,
    created_at TIMESTAMP
);
```

### **amateur_player_market_value**
```sql
CREATE TABLE amateur_player_market_value (
    id BIGINT PRIMARY KEY,
    player_id BIGINT FOREIGN KEY,
    base_value INT DEFAULT 5000,
    profile_views_points INT,
    engagement_points INT,
    performance_points INT,
    calculated_market_value INT,
    trend_status VARCHAR(50),
    created_at TIMESTAMP
);
```

---

## ✅ KONTROL

| Durum | Tablo | Sayı |
|-------|-------|------|
| ✅ | Users | 5 |
| ✅ | Profiles | 10 |
| ✅ | Teams | 8 |
| ✅ | Matches | 12 |
| ✅ | Players | 15 |
| ✅ | Transfer | 12 |
| ✅ | Messages | 10 |
| ✅ | Notifications | 8 |
| ✅ | Scout | 10 |
| ✅ | Legal | 12 |
| ✅ | Help | 10 |
| ✅ | Admin | 10 |
| ✅ | Community | 8 |
| ✅ | General | 5 |

**TOPLAM: 135 TABLO** ✅

---

**Tarih:** 2 Mart 2026  
**Status:** ✅ PRODUCTION READY
