# Vecna ERP - Deployment Guide

## Prerequisites

- **Operating System**: Ubuntu 22.04 LTS (recommended)
- **PHP**: 8.2 or higher
- **PostgreSQL**: 15 or higher
- **Redis**: 7.0 or higher
- **Nginx**: Latest stable
- **Node.js**: 18+ (for frontend assets)
- **Composer**: 2.x
- **Supervisor**: For queue workers

## Server Setup

### 1. Update System

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install PHP 8.2 and Extensions

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-common \
    php8.2-pgsql php8.2-redis php8.2-mbstring php8.2-xml \
    php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath \
    php8.2-intl php8.2-soap php8.2-imagick
```

### 3. Install PostgreSQL

```bash
sudo apt install -y postgresql postgresql-contrib

# Start PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Create database user
sudo -u postgres psql

postgres=# CREATE USER vecna_user WITH PASSWORD 'your_secure_password';
postgres=# CREATE DATABASE vecna_central OWNER vecna_user;
postgres=# GRANT ALL PRIVILEGES ON DATABASE vecna_central TO vecna_user;
postgres=# \q
```

### 4. Install Redis

```bash
sudo apt install -y redis-server

# Configure Redis
sudo nano /etc/redis/redis.conf
# Set: supervised systemd

sudo systemctl restart redis
sudo systemctl enable redis
```

### 5. Install Nginx

```bash
sudo apt install -y nginx

sudo systemctl start nginx
sudo systemctl enable nginx
```

### 6. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### 7. Install Node.js & npm

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### 8. Install Supervisor

```bash
sudo apt install -y supervisor

sudo systemctl start supervisor
sudo systemctl enable supervisor
```

## Application Deployment

### 1. Clone Repository

```bash
cd /var/www
sudo git clone https://github.com/Julius-3367/vecna.git
cd vecna

# Set ownership
sudo chown -R www-data:www-data /var/www/vecna
sudo chmod -R 755 /var/www/vecna/storage
sudo chmod -R 755 /var/www/vecna/bootstrap/cache
```

### 2. Install Dependencies

```bash
# PHP dependencies
composer install --optimize-autoloader --no-dev

# JavaScript dependencies
npm install
npm run build
```

### 3. Environment Configuration

```bash
cp .env.example .env
nano .env
```

**Configure .env:**

```env
APP_NAME="Vecna ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vecna.co.ke
APP_TIMEZONE=Africa/Nairobi

CENTRAL_DOMAIN=vecna.co.ke
TENANT_DOMAIN_SUFFIX=.vecna.co.ke

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=vecna_central
DB_USERNAME=vecna_user
DB_PASSWORD=your_secure_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# AWS S3 for file storage
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=af-south-1
AWS_BUCKET=vecna-production

# M-Pesa Credentials
MPESA_CONSUMER_KEY=your_mpesa_consumer_key
MPESA_CONSUMER_SECRET=your_mpesa_consumer_secret
MPESA_PASSKEY=your_passkey
MPESA_SHORTCODE=your_shortcode
MPESA_ENVIRONMENT=production

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your_mail_username
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@vecna.co.ke

# Monitoring
SENTRY_LARAVEL_DSN=your_sentry_dsn
```

### 4. Generate Application Key

```bash
php artisan key:generate
```

### 5. Run Migrations

```bash
# Central database migrations
php artisan migrate --force

# Seed subscription plans
php artisan db:seed --class=SubscriptionPlansSeeder

# Create admin user
php artisan make:filament-user
```

### 6. Storage Setup

```bash
php artisan storage:link

# Set proper permissions
sudo chown -R www-data:www-data /var/www/vecna/storage
sudo chmod -R 775 /var/www/vecna/storage
```

### 7. Optimize for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## Nginx Configuration

Create Nginx configuration:

```bash
sudo nano /etc/nginx/sites-available/vecna
```

**Add configuration:**

```nginx
# Main domain (central)
server {
    listen 80;
    listen [::]:80;
    server_name vecna.co.ke;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name vecna.co.ke;
    root /var/www/vecna/public;

    index index.php index.html index.htm;

    # SSL Configuration (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/vecna.co.ke/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/vecna.co.ke/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Increase upload size
    client_max_body_size 100M;
}

# Tenant subdomains (wildcard)
server {
    listen 80;
    listen [::]:80;
    server_name *.vecna.co.ke;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name *.vecna.co.ke;
    root /var/www/vecna/public;

    index index.php index.html index.htm;

    # Wildcard SSL
    ssl_certificate /etc/letsencrypt/live/vecna.co.ke/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/vecna.co.ke/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 100M;
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/vecna /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## SSL Certificate (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx

# Get wildcard certificate
sudo certbot certonly --manual --preferred-challenges dns \
    -d vecna.co.ke -d *.vecna.co.ke

# Auto-renewal
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
```

## Queue Worker Configuration

Create Supervisor configuration:

```bash
sudo nano /etc/supervisor/conf.d/vecna-worker.conf
```

**Add:**

```ini
[program:vecna-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vecna/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/vecna/storage/logs/worker.log
stopwaitsecs=3600
```

Start workers:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vecna-worker:*
```

## Laravel Horizon (Alternative to basic workers)

```bash
sudo nano /etc/supervisor/conf.d/vecna-horizon.conf
```

```ini
[program:vecna-horizon]
process_name=%(program_name)s
command=php /var/www/vecna/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/vecna/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vecna-horizon
```

## Scheduled Tasks (Cron)

```bash
sudo crontab -e -u www-data
```

**Add:**

```cron
* * * * * cd /var/www/vecna && php artisan schedule:run >> /dev/null 2>&1
```

## Firewall Configuration

```bash
sudo ufw allow 'Nginx Full'
sudo ufw allow OpenSSH
sudo ufw enable
```

## Performance Optimization

### PHP-FPM Configuration

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

**Optimize:**

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

```bash
sudo systemctl restart php8.2-fpm
```

### OPcache Configuration

```bash
sudo nano /etc/php/8.2/fpm/conf.d/10-opcache.ini
```

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
```

## Monitoring & Maintenance

### Log Rotation

```bash
sudo nano /etc/logrotate.d/vecna
```

```
/var/www/vecna/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### Backup Script

```bash
sudo nano /usr/local/bin/vecna-backup.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/vecna"
DATE=$(date +%Y%m%d_%H%M%S)
APP_DIR="/var/www/vecna"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
sudo -u postgres pg_dump vecna_central > $BACKUP_DIR/db_$DATE.sql

# Backup application files
tar -czf $BACKUP_DIR/app_$DATE.tar.gz -C /var/www vecna

# Upload to S3
aws s3 cp $BACKUP_DIR/db_$DATE.sql s3://vecna-backups/database/
aws s3 cp $BACKUP_DIR/app_$DATE.tar.gz s3://vecna-backups/application/

# Keep only last 7 days locally
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup completed: $DATE"
```

Make executable and schedule:

```bash
sudo chmod +x /usr/local/bin/vecna-backup.sh
sudo crontab -e
```

Add daily backup at 2 AM:

```cron
0 2 * * * /usr/local/bin/vecna-backup.sh >> /var/log/vecna-backup.log 2>&1
```

## Creating First Tenant

### Via Artisan Command

```bash
php artisan tenants:create shop1 \
    --domain=shop1.vecna.co.ke \
    --business_name="My First Shop" \
    --email=owner@shop1.com \
    --phone=+254712345678
```

### Via API

```bash
curl -X POST https://vecna.co.ke/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "My Shop",
    "email": "owner@myshop.com",
    "phone": "+254700000000",
    "industry": "retail",
    "password": "SecurePassword123!"
  }'
```

## Troubleshooting

### Check Application Logs

```bash
tail -f /var/www/vecna/storage/logs/laravel.log
```

### Check Queue Workers

```bash
sudo supervisorctl status
```

### Restart Services

```bash
# Restart all services
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl restart redis
sudo supervisorctl restart all
```

### Clear Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Database Issues

```bash
# Check PostgreSQL status
sudo systemctl status postgresql

# Check connections
sudo -u postgres psql -c "SELECT * FROM pg_stat_activity;"

# Restart PostgreSQL
sudo systemctl restart postgresql
```

## Security Best Practices

1. **Change default passwords** for database, redis
2. **Enable firewall** (ufw)
3. **Regular updates**: `sudo apt update && sudo apt upgrade`
4. **Use strong APP_KEY**: Never share .env file
5. **Enable 2FA** for admin users
6. **Regular backups**: Test restore procedures
7. **Monitor logs**: Set up alerts for errors
8. **SSL only**: Force HTTPS everywhere
9. **Rate limiting**: Configure API throttling
10. **Security headers**: Configured in Nginx

## Scaling Considerations

### Horizontal Scaling

- **Load Balancer**: Nginx/HAProxy for multiple app servers
- **Database**: PostgreSQL replication (master-slave)
- **Redis**: Redis Cluster for queue/cache
- **File Storage**: S3 instead of local filesystem

### Vertical Scaling

- Increase PHP-FPM workers
- Allocate more RAM to PostgreSQL
- Increase Redis memory limit
- Use faster SSD storage

## Support & Monitoring

- **Application**: Sentry for error tracking
- **Uptime**: UptimeRobot or Pingdom
- **Performance**: New Relic or DataDog
- **Logs**: Papertrail or Loggly

---

**Deployment Checklist:**

- [ ] Server provisioned (Ubuntu 22.04)
- [ ] PHP 8.2+ installed with extensions
- [ ] PostgreSQL 15+ configured
- [ ] Redis installed and running
- [ ] Nginx configured with SSL
- [ ] Application cloned and dependencies installed
- [ ] Environment configured (.env)
- [ ] Migrations run
- [ ] Queue workers running (Supervisor)
- [ ] Cron jobs configured
- [ ] Backups automated
- [ ] Monitoring set up
- [ ] First tenant created successfully
- [ ] DNS configured (vecna.co.ke, *.vecna.co.ke)

**Production URL:** https://vecna.co.ke  
**Admin Panel:** https://vecna.co.ke/admin  
**API Documentation:** https://vecna.co.ke/api/documentation

🎉 **Vecna ERP is now live and ready to empower Kenyan SMEs!**
