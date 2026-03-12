# Week 10 Anomaly Scoring

Date: 9 Mart 2026

## Goal

Moderasyon kuyruğu itemlerine anomaly ve risk puanlaması ekleyip yüksek riskli itemleri otomatik olarak flaglayıp önceliklendirmek.

## Endpoints

### `POST /api/moderation/{id}/score`

Verilen queue item'ı puanlar:
- `anomaly_score`: submitter doğrulama durumu, güven skoru, tarihçe bazlı hesaplama
- `risk_score`: priority, dual-approval gereksinimi, reddedilme tarihçesi bazlı
- `overall_score`: (anomaly + risk) / 2
- `recommendation`: ESCALATE_TO_ADMIN | REQUIRE_SENIOR_REVIEW | FLAG_FOR_ATTENTION | STANDARD_REVIEW

### `GET /api/moderation/high-risk`

Yüksek risk skoru (0.70+) olan pending itemleri listeler:
- overall_score'a göre sıralanmış
- submitter ve priority bilgileri ile
- limit 20

## Scoring Logic

**Anomaly Score** (0-1):
- Unverified submitter: +0.25
- Trust score < 0.50: +0.20
- High volume submitter (>5 in 24h): +0.15
- Has conflicts: +0.30
- Low confidence (<0.60): +0.15

**Risk Score** (0-1):
- Critical priority: +0.40
- High priority: +0.25
- Medium priority: +0.15
- Low priority: +0.05
- Requires dual approval: +0.15
- Rejected 3+ times: +0.20
- Item age > 7 days: +0.10

## Notes

- Scores capped at 1.0
- Recommendation helps moderators prioritize workload
