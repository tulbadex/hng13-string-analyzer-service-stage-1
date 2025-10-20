# EC2 Deployment Guide - String Analyzer API

## Quick Setup Commands

### 1. Initial Server Setup
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Add PHP 8.3 repository
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3 and extensions
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-xml php8.3-curl php8.3-mbstring php8.3-zip php8.3-sqlite3 php8.3-bcmath php8.3-gd php8.3-intl

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Nginx and Git
sudo apt install -y nginx git
```

### 2. SSH Key Setup for GitHub
```bash
# Generate SSH key
ssh-keygen -t rsa -b 4096 -C "your-email@example.com"

# Display public key (copy this to GitHub)
cat ~/.ssh/id_rsa.pub

# Add GitHub to known hosts
ssh-keyscan github.com >> ~/.ssh/known_hosts
```

### 3. Clone and Setup Application
```bash
# Clone repository
cd /var/www
sudo git clone git@github.com:YOUR_USERNAME/string-analyzer-api.git
cd string-analyzer-api

# Install dependencies
sudo composer install --no-dev --optimize-autoloader

# Set permissions
sudo chown -R www-data:www-data /var/www/string-analyzer-api
sudo chmod -R 755 /var/www/string-analyzer-api/storage
sudo chmod -R 755 /var/www/string-analyzer-api/bootstrap/cache
sudo chmod 644 /var/www/string-analyzer-api/database/database.sqlite

# Setup environment
sudo cp .env.example .env
sudo php artisan key:generate
sudo php artisan migrate --force
sudo php artisan config:cache
sudo php artisan route:cache
```

### 4. Configure Nginx
```bash
# Create Nginx configuration
sudo tee /etc/nginx/sites-available/string-analyzer-api > /dev/null <<EOF
server {
    listen 80;
    server_name _;
    root /var/www/string-analyzer-api/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

# Enable site
sudo ln -s /etc/nginx/sites-available/string-analyzer-api /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test and restart services
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
sudo systemctl enable nginx
sudo systemctl enable php8.3-fpm
```

## GitHub Actions Secrets

Add these secrets to your GitHub repository:

| Secret Name | Value | Description |
|-------------|-------|-------------|
| `EC2_HOST` | `your-ec2-public-ip` | EC2 instance public IP |
| `EC2_USERNAME` | `ubuntu` | SSH username |
| `EC2_SSH_KEY` | `-----BEGIN OPENSSH PRIVATE KEY-----...` | Private SSH key content |

## Production Environment Variables

Update `/var/www/string-analyzer-api/.env`:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-ec2-ip

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/string-analyzer-api/database/database.sqlite
```

## Security Group Configuration

Allow these ports in your EC2 security group:
- **SSH (22)**: For server access
- **HTTP (80)**: For API access
- **HTTPS (443)**: For SSL (optional)

## Verification Commands

```bash
# Check services status
sudo systemctl status nginx
sudo systemctl status php8.3-fpm

# Test API endpoints
curl http://your-ec2-ip/
curl -X POST http://your-ec2-ip/strings -H "Content-Type: application/json" -d '{"value":"test"}'

# Check logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/www/string-analyzer-api/storage/logs/laravel.log
```

## Automatic Deployment

Once GitHub Actions is configured, every push to `main` branch will:
1. Run tests
2. Deploy to EC2 automatically
3. Update dependencies
4. Run migrations
5. Clear/rebuild caches
6. Restart services

## Troubleshooting

### Common Issues:
1. **Permission denied**: Check file permissions and ownership
2. **502 Bad Gateway**: Check PHP-FPM is running
3. **Database errors**: Ensure SQLite file exists and is writable
4. **GitHub clone fails**: Verify SSH key is added to GitHub

### Log Locations:
- Nginx: `/var/log/nginx/error.log`
- PHP-FPM: `/var/log/php8.3-fpm.log`
- Laravel: `/var/www/string-analyzer-api/storage/logs/laravel.log`