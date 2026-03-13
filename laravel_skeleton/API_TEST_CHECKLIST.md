# API Test Checklist (MVP)

Bu checklist, `laravel_skeleton/routes/api.php` icin hizli smoke test plani sunar.

## On Hazirlik

1. `composer require laravel/sanctum`
2. `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
3. `php artisan migrate`
4. API base URL: `http://localhost:8000/api`

## Auth

### 1) Register (team)
`POST /auth/register`

Body:
```json
{
  "name": "Team Alpha",
  "email": "team.alpha@example.com",
  "password": "secret123",
  "role": "team",
  "city": "Istanbul",
  "phone": "5551112233"
}
```
Beklenen:
- `201`
- `data.token` doner

### 2) Register (player)
`POST /auth/register`

Body:
```json
{
  "name": "Player One",
  "email": "player.one@example.com",
  "password": "secret123",
  "role": "player",
  "city": "Ankara"
}
```
Beklenen:
- `201`
- `data.token` doner

### 3) Login
`POST /auth/login`

Body:
```json
{
  "email": "player.one@example.com",
  "password": "secret123"
}
```
Beklenen:
- `200`
- `data.token` doner

### 4) Me
`GET /auth/me`
Header: `Authorization: Bearer <TOKEN>`

Beklenen:
- `200`
- user bilgisi

### 5) Update Me
`PUT /auth/me`
Header: `Authorization: Bearer <TOKEN>`

Body:
```json
{
  "city": "Izmir",
  "phone": "5552223344"
}
```
Beklenen:
- `200`
- guncel user

### 6) Logout
`POST /auth/logout`
Header: `Authorization: Bearer <TOKEN>`

Beklenen:
- `200`

## Team

### 7) Team list
`GET /teams`
Header: `Authorization: Bearer <TOKEN>`

### 8) Team update (owner)
`PUT /teams/{teamUserId}`
Header: `Authorization: Bearer <TEAM_TOKEN>`

Body:
```json
{
  "team_name": "Team Alpha U19",
  "league_level": "Regional",
  "team_city": "Istanbul",
  "needs_text": "Stoper ve sol bek"
}
```
Beklenen:
- `200`

## Player

### 9) Player update (owner)
`PUT /players/{playerUserId}`
Header: `Authorization: Bearer <PLAYER_TOKEN>`

Body:
```json
{
  "position": "ST",
  "birth_year": 2006,
  "dominant_foot": "right",
  "height_cm": 182,
  "weight_kg": 74
}
```
Beklenen:
- `200`

### 10) Player filter list
`GET /players?position=ST&city=Izmir&age_min=16&age_max=22`
Header: `Authorization: Bearer <TOKEN>`

Beklenen:
- `200`
- filtrelenmis liste

## Staff

### 11) Staff list by role
`GET /staff?role_type=scout&city=Istanbul`
Header: `Authorization: Bearer <TOKEN>`

### 12) Staff update (owner)
`PUT /staff/{staffUserId}`
Header: `Authorization: Bearer <STAFF_TOKEN>`

Body:
```json
{
  "role_type": "scout",
  "organization": "Scout Lab",
  "experience_years": 6
}
```

## Opportunities

### 13) Create opportunity (team)
`POST /opportunities`
Header: `Authorization: Bearer <TEAM_TOKEN>`

Body:
```json
{
  "title": "U19 Forvet Araniyor",
  "position": "ST",
  "age_min": 16,
  "age_max": 20,
  "city": "Istanbul",
  "details": "Hizli ve bitirici forvet",
  "status": "open"
}
```
Beklenen:
- `201`

### 14) Opportunity list filter
`GET /opportunities?status=open&position=ST&city=Istanbul`
Header: `Authorization: Bearer <TOKEN>`

### 15) Opportunity update/destroy owner check
- `PUT /opportunities/{id}` with owner token -> `200`
- `DELETE /opportunities/{id}` with non-owner token -> `403`

## Applications

### 16) Apply (player)
`POST /opportunities/{opportunityId}/apply`
Header: `Authorization: Bearer <PLAYER_TOKEN>`

Body:
```json
{
  "message": "Videolarimi profilimde paylastim."
}
```
Beklenen:
- `201`

### 17) Apply duplicate
Ayni player ile tekrar apply.
Beklenen:
- `409`

### 18) Incoming applications (team)
`GET /applications/incoming?status=pending`
Header: `Authorization: Bearer <TEAM_TOKEN>`

### 19) Outgoing applications (player)
`GET /applications/outgoing`
Header: `Authorization: Bearer <PLAYER_TOKEN>`

### 20) Change application status (team)
`PATCH /applications/{applicationId}/status`
Header: `Authorization: Bearer <TEAM_TOKEN>`

Body:
```json
{
  "status": "accepted"
}
```
Beklenen:
- `200`

## Contacts

### 21) Send message
`POST /contacts`
Header: `Authorization: Bearer <TOKEN>`

Body:
```json
{
  "to_user_id": 2,
  "subject": "Gorusme talebi",
  "message": "Yarin saat 20:00 uygun musunuz?"
}
```
Beklenen:
- `201`

### 22) Inbox / Sent
- `GET /contacts/inbox`
- `GET /contacts/sent`
Header: `Authorization: Bearer <TOKEN>`

### 23) Change message status (receiver only)
`PATCH /contacts/{contactId}/status`
Header: `Authorization: Bearer <RECEIVER_TOKEN>`

Body:
```json
{
  "status": "read"
}
```
Beklenen:
- receiver: `200`
- non-receiver: `403`

## Hata Senaryolari

- Eksik token: `401`
- Yanlis role ile islem: `403`
- Kayit bulunamadi: `404`
- Validation hatasi: `422`
- Duplicate apply: `409`
