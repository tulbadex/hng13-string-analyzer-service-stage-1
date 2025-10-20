#!/bin/bash

# EC2 Deployment Script for String Analyzer API - PHP 8.3.9

# Update system
sudo apt update && sudo apt upgrade -y

# Add PHP repository for 8.3
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3 and required extensions
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-xml php8.3-curl php8.3-mbstring php8.3-zip php8.3-sqlite3 php8.3-bcmath php8.3-gd php8.3-intl

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Nginx and Git
sudo apt install -y nginx git

# Setup SSH key for GitHub (if not exists)
if [ ! -f ~/.ssh/id_rsa ]; then
    ssh-keygen -t rsa -b 4096 -C "your-email@example.com" -f ~/.ssh/id_rsa -N ""
    echo "Add this public key to your GitHub account:"
    cat ~/.ssh/id_rsa.pub
    echo "Press Enter after adding the key to GitHub..."
    read
fi

# Add GitHub to known hosts
ssh-keyscan github.com >> ~/.ssh/known_hosts

# Clone application
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

# Configure Nginx
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

# Test Nginx configuration
sudo nginx -t

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
sudo systemctl enable nginx
sudo systemctl enable php8.3-fpm

echo "Deployment complete! API available at http://your-ec2-ip"
echo "Don't forget to:"
echo "1. Add the SSH public key to your GitHub account"
echo "2. Update security group to allow HTTP (port 80)"
echo "3. Update .env file with production settings"