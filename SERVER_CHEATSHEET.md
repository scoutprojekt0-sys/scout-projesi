# Server Cheatsheet

Sunucu:

- Host: `178.104.162.196`
- SSH user: `deploy`
- App path: `~/apps/scout_api`
- Caddy path: `~/caddy`

## SSH

```bash
ssh deploy@178.104.162.196
```

## App Status

```bash
cd ~/apps/scout_api
docker compose --env-file .env.production -f compose.prod.yml ps
docker compose --env-file .env.production -f compose.prod.yml logs --tail=100 app
docker compose --env-file .env.production -f compose.prod.yml logs --tail=100 nginx
docker compose --env-file .env.production -f compose.prod.yml logs --tail=100 queue
docker compose --env-file .env.production -f compose.prod.yml logs --tail=100 scheduler
```

## Restart App

```bash
cd ~/apps/scout_api
docker compose --env-file .env.production -f compose.prod.yml up -d --force-recreate app queue scheduler nginx
```

## Full Restart

```bash
cd ~/apps/scout_api
docker compose --env-file .env.production -f compose.prod.yml down
docker compose --env-file .env.production -f compose.prod.yml up -d
```

## Deploy New Code

```bash
cd ~/apps/scout_api
git fetch origin
git reset --hard origin/main
docker compose --env-file .env.production -f compose.prod.yml build app --no-cache
docker compose --env-file .env.production -f compose.prod.yml up -d
```

## Caddy Status

```bash
cd ~/caddy
docker compose ps
docker compose logs --tail=100
```

## Monitoring

```bash
cd ~/apps/scout_api
bash scripts/monitor_prod.sh
tail -n 50 ~/monitoring/scout_api/monitor.log
cat ~/monitoring/scout_api/last_status.txt
```

## Restart Caddy

```bash
cd ~/caddy
docker compose down
docker compose up -d
```

## Quick Tests

```bash
curl -I https://nextscout.pro
curl -I https://api.nextscout.pro/up
curl -I http://127.0.0.1:8081/up
```

## Firewall

```bash
sudo ufw status
```

## Docker Cleanup

```bash
docker ps
docker image ls
docker system df
```

## Important Files

- `~/apps/scout_api/.env.production`
- `~/apps/scout_api/compose.prod.yml`
- `~/caddy/Caddyfile`
- `~/caddy/compose.yml`

## Notes

- `nextscout.pro` and `api.nextscout.pro` are behind Caddy.
- App traffic is proxied to Docker nginx on `127.0.0.1:8081`.
- If DNS acts stale, test with time and recheck.

## Model Comparison

```bash
python scripts/compare_run_metrics.py runs/basketball/player_ball_detector13/validation_summary.json
python scripts/compare_run_metrics.py runs/volleyball/player_ball_detector2/validation_summary.json
```
