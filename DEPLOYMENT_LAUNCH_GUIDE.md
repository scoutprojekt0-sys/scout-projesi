# 🚀 NEXTSCOUT - DEPLOYMENT & LAUNCH GUIDE

**Tarih:** 2 Mart 2026  
**Status:** Production Ready  
**Version:** 5.2

---

## 📋 PRE-DEPLOYMENT CHECKLIST

### **1. Code & Database**
- [ ] All migrations created
- [ ] Seeds prepared
- [ ] .env file configured
- [ ] Cache cleared
- [ ] Tests passed (100% success)
- [ ] Code reviewed
- [ ] Git committed

### **2. Security**
- [ ] SSL certificate obtained
- [ ] API keys generated
- [ ] Database credentials secure
- [ ] JWT secret configured
- [ ] Rate limiting configured
- [ ] CORS configured
- [ ] Security headers set

### **3. Infrastructure**
- [ ] Server provisioned
- [ ] Docker images built
- [ ] Database server running
- [ ] Redis server running
- [ ] Domain DNS configured
- [ ] CDN setup (optional)
- [ ] Backup system configured

---

## 🐳 DOCKER DEPLOYMENT

### **docker-compose.yml**
```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8000:8000"
    environment:
      - APP_ENV=production
      - APP_KEY=base64:xxxxx
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8
    environment:
      - MYSQL_DATABASE=nextscout
      - MYSQL_PASSWORD=secure_password
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data

volumes:
  mysql_data:
  redis_data:
```

### **Deployment Commands**
```bash
# Build images
docker-compose build

# Start services
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate

# Seed database
docker-compose exec app php artisan db:seed

# Cache config
docker-compose exec app php artisan config:cache

# Optimize
docker-compose exec app php artisan optimize
```

---

## 🌐 VPS DEPLOYMENT (Ubuntu 22.04)

### **1. Server Setup**
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install dependencies
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-redis
sudo apt install -y nginx mysql-server redis-server
sudo apt install -y git composer curl wget

# Create app directory
sudo mkdir -p /var/www/nextscout
sudo chown $USER:$USER /var/www/nextscout
cd /var/www/nextscout
```

### **2. Clone & Setup**
```bash
# Clone repository
git clone https://github.com/nextscout/platform.git .

# Install dependencies
composer install --optimize-autoloader --no-dev

# Copy environment file
cp .env.example .env
# Edit .env with production values

# Generate app key
php artisan key:generate

# Create storage symbolic link
php artisan storage:link
```

### **3. Database Setup**
```bash
# Create database
mysql -u root -p << EOF
CREATE DATABASE nextscout;
CREATE USER 'nextscout'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON nextscout.* TO 'nextscout'@'localhost';
FLUSH PRIVILEGES;
EOF

# Run migrations
php artisan migrate --force

# Seed database (optional)
php artisan db:seed
```

### **4. Nginx Configuration**
```nginx
server {
    listen 80;
    server_name nextscout.pro www.nextscout.pro;

    root /var/www/nextscout/public;
    index index.php index.html;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name nextscout.pro www.nextscout.pro;

    root /var/www/nextscout/public;
    index index.php index.html;

    ssl_certificate /etc/ssl/certs/nextscout.pro.crt;
    ssl_certificate_key /etc/ssl/private/nextscout.pro.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### **5. PHP-FPM & Services**
```bash
# Start services
sudo systemctl start php8.2-fpm
sudo systemctl start nginx
sudo systemctl start mysql
sudo systemctl start redis-server

# Enable on boot
sudo systemctl enable php8.2-fpm nginx mysql redis-server

# Check status
sudo systemctl status nginx
```

### **6. SSL Certificate (Let's Encrypt)**
```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Get certificate
sudo certbot certonly --nginx -d nextscout.pro -d www.nextscout.pro

# Auto-renew
sudo systemctl enable certbot.timer
```

---

## ✅ POST-DEPLOYMENT

### **1. Verify Installation**
```bash
# Check API
curl -X GET http://localhost:8000/api/

# Check status
php artisan status

# View logs
tail -f storage/logs/laravel.log
```

### **2. Cache & Optimization**
```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader
```

### **3. Monitoring Setup**
```bash
# Monitor logs
tail -f storage/logs/laravel.log

# Monitor system
watch 'ps aux | grep -E "php|nginx"'

# Monitor database
mysql -u nextscout -p nextscout -e "SHOW PROCESSLIST;"
```

### **4. Backups**
```bash
# Database backup
mysqldump -u nextscout -p nextscout > backup_$(date +%Y%m%d).sql

# File backup
tar -czf nextscout_backup_$(date +%Y%m%d).tar.gz /var/www/nextscout

# Upload to S3/Cloud
aws s3 cp backup_*.sql s3://nextscout-backups/
aws s3 cp nextscout_backup_*.tar.gz s3://nextscout-backups/
```

---

## 🔐 SECURITY HARDENING

### **1. Firewall**
```bash
# UFW Firewall
sudo ufw enable
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

### **2. SSH Security**
```bash
# Edit SSH config
sudo nano /etc/ssh/sshd_config

# Disable root login
PermitRootLogin no

# Change default port
Port 2222

# Restart SSH
sudo systemctl restart sshd
```

### **3. File Permissions**
```bash
# Set proper permissions
sudo chown -R www-data:www-data /var/www/nextscout
sudo chmod -R 755 /var/www/nextscout
sudo chmod -R 775 /var/www/nextscout/storage
sudo chmod -R 775 /var/www/nextscout/bootstrap/cache
```

---

## 📊 MONITORING & ALERTS

### **1. Application Monitoring**
```bash
# Install New Relic
composer require newrelic/newrelic-php-agent

# Or install DataDog
composer require datadog/php-datadog-trace
```

### **2. Server Monitoring**
```bash
# Install monitoring tools
sudo apt install -y htop iotop nethogs

# Monitor in real-time
htop
```

### **3. Log Aggregation**
- ELK Stack (Elasticsearch, Logstash, Kibana)
- Datadog
- Splunk
- Sentry (Error tracking)

---

## 📈 LOAD TESTING

### **Apache Bench**
```bash
ab -n 10000 -c 100 https://nextscout.pro/api/
```

### **Wrk Load Tester**
```bash
wrk -t12 -c400 -d30s https://nextscout.pro/api/
```

### **JMeter**
- Download Apache JMeter
- Create test plan
- Run performance tests

---

## 🚨 TROUBLESHOOTING

### **Common Issues**

| Problem | Solution |
|---------|----------|
| 500 Error | Check logs: `tail -f storage/logs/laravel.log` |
| Database Error | Verify credentials in .env |
| Permission Error | Fix permissions: `chmod -R 775 storage/` |
| Cache Issues | Clear cache: `php artisan cache:clear` |
| Route Issues | Rebuild routes: `php artisan route:cache` |

---

## 📞 PRODUCTION SUPPORT

### **24/7 Monitoring**
- Error tracking (Sentry)
- Performance monitoring (New Relic)
- Uptime monitoring (UptimeRobot)
- Log aggregation (ELK Stack)

### **Backup Strategy**
- Daily automated backups
- Weekly full backups
- Monthly archive backups
- Backup verification

### **Update Strategy**
- Scheduled maintenance windows
- Zero-downtime deployments
- Database migration testing
- Rollback procedures

---

## ✅ LAUNCH CHECKLIST

- [ ] All systems deployed
- [ ] SSL certificates valid
- [ ] Database migrated
- [ ] Backups working
- [ ] Monitoring active
- [ ] Security hardened
- [ ] Performance tested
- [ ] Team trained
- [ ] Support documentation ready
- [ ] Go-live approved

---

## 🎉 LAUNCH!

```bash
# Final checks
php artisan optimize

# Enable maintenance mode (optional)
php artisan down

# Deploy
git pull origin main

# Run migrations
php artisan migrate --force

# Clear caches
php artisan cache:clear

# Disable maintenance mode
php artisan up

# Check status
curl https://nextscout.pro/api/
```

**✅ PRODUCTION LIVE! 🚀**

---

**Tarih:** 2 Mart 2026  
**Status:** Ready for Launch  
**Next Step:** Deploy to Production

