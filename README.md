# String Analyzer API

A RESTful API service that analyzes strings and stores their computed properties including length, palindrome detection, character frequency, and more.

## Features

- **String Analysis**: Computes length, palindrome status, unique characters, word count, SHA-256 hash, and character frequency map
- **Storage**: Stores analyzed strings with SQLite database
- **Filtering**: Advanced filtering with query parameters
- **Natural Language Queries**: Filter strings using natural language
- **RESTful Design**: Clean API endpoints following REST principles

## Tech Stack

- **Framework**: Laravel 12
- **Database**: SQLite
- **PHP**: 8.3.9

## Installation

### Prerequisites
- PHP 8.3.9 or higher
- Composer
- SQLite

### Setup Instructions

1. **Clone the repository:**
   ```bash
   git clone https://github.com/tulbadex/hng13-string-analyzer-service-stage-1.git
   cd string-analyzer-api
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Environment setup:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup:**
   ```bash
   php artisan migrate
   ```

5. **Run the development server:**
   ```bash
   php artisan serve
   ```

The API will be available at `http://localhost:8000`

## API Endpoints

### 0. Health Check
**GET** `/`

**Response:**
```json
{
  "message": "String Analyzer API",
  "version": "1.0.0",
  "endpoints": {
    "POST /strings": "Create/analyze string",
    "GET /strings/{value}": "Get specific string",
    "GET /strings": "Get all strings with filtering",
    "GET /strings/filter-by-natural-language": "Natural language filtering",
    "DELETE /strings/{value}": "Delete string"
  }
}
```

### 1. Create/Analyze String
**POST** `/strings`

**Request Body:**
```json
{
  "value": "string to analyze"
}
```

**Success Response (201):**
```json
{
  "id": "sha256_hash_value",
  "value": "string to analyze",
  "properties": {
    "length": 16,
    "is_palindrome": false,
    "unique_characters": 12,
    "word_count": 3,
    "sha256_hash": "abc123...",
    "character_frequency_map": {
      "s": 2,
      "t": 3,
      "r": 2
    }
  },
  "created_at": "2025-10-20T10:00:00Z"
}
```

### 2. Get Specific String
**GET** `/strings/{string_value}`

### 3. Get All Strings with Filtering
**GET** `/strings?is_palindrome=true&min_length=5&max_length=20&word_count=2&contains_character=a`

**Query Parameters:**
- `is_palindrome`: boolean (true/false)
- `min_length`: integer (minimum string length)
- `max_length`: integer (maximum string length)
- `word_count`: integer (exact word count)
- `contains_character`: string (single character to search for)

### 4. Natural Language Filtering
**GET** `/strings/filter-by-natural-language?query=all%20single%20word%20palindromic%20strings`

**Example Queries:**
- "all single word palindromic strings"
- "strings longer than 10 characters"
- "palindromic strings that contain the first vowel"
- "strings containing the letter z"

### 5. Delete String
**DELETE** `/strings/{string_value}`

## Testing

Run the built-in tests:
```bash
php artisan test
```

## Error Responses

- **400 Bad Request**: Invalid request body or query parameters
- **404 Not Found**: String does not exist
- **409 Conflict**: String already exists
- **422 Unprocessable Entity**: Invalid data type

## Environment Variables

Key environment variables in `.env`:
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=database/database.sqlite`

## Deployment

### AWS EC2 Deployment with PHP 8.3.9

#### Step 1: Launch EC2 Instance
- **AMI**: Ubuntu 22.04 LTS
- **Instance Type**: t3.micro (or larger)
- **Security Group**: Allow HTTP (80), HTTPS (443), SSH (22)
- **Key Pair**: Create/use existing key pair

#### Step 2: Server Setup
1. **SSH into your instance:**
   ```bash
   ssh -i your-key.pem ubuntu@your-ec2-ip
   ```

2. **Update system and install dependencies:**
   ```bash
   sudo apt update && sudo apt upgrade -y
   sudo apt install -y software-properties-common curl wget git
   ```

3. **Install PHP 8.3.9:**
   ```bash
   sudo add-apt-repository ppa:ondrej/php -y
   sudo apt update
   sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-xml php8.3-curl php8.3-mbstring php8.3-zip php8.3-sqlite3 php8.3-bcmath php8.3-gd php8.3-intl
   ```

4. **Install Composer:**
   ```bash
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   ```

5. **Install and configure Nginx:**
   ```bash
   sudo apt install -y nginx
   sudo systemctl enable nginx
   sudo systemctl start nginx
   ```

#### Step 3: SSH Setup for GitHub
1. **Generate SSH key for GitHub:**
   ```bash
   ssh-keygen -t rsa -b 4096 -C "your-email@example.com"
   cat ~/.ssh/id_rsa.pub
   ```

2. **Add the public key to your GitHub account:**
   - Go to GitHub Settings > SSH and GPG keys
   - Click "New SSH key"
   - Paste the public key

3. **Test SSH connection to GitHub:**
   ```bash
   ssh -T git@github.com
   ```
   Expected response: `Hi username! You've successfully authenticated, but GitHub does not provide shell access.`

#### Step 4: Deploy Application
1. **Clone repository with proper permissions:**
   ```bash
   # Clone to home directory first
   cd ~
   git clone git@github.com:tulbadex/hng13-string-analyzer-service-stage-1.git
   
   # Create web directory with proper permissions
   sudo mkdir -p /var/www/string-analyzer-api
   sudo chown ubuntu:ubuntu /var/www/string-analyzer-api
   
   # Move files to web directory
   mv ~/hng13-string-analyzer-service-stage-1/* /var/www/string-analyzer-api/
   mv ~/hng13-string-analyzer-service-stage-1/.* /var/www/string-analyzer-api/ 2>/dev/null || true
   
   # Navigate to project directory
   cd /var/www/string-analyzer-api
   ```

2. **Install dependencies and setup:**
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   ```

3. **Set proper permissions:**
   ```bash
   sudo chown -R www-data:www-data /var/www/string-analyzer-api
   sudo chmod -R 755 /var/www/string-analyzer-api/storage
   sudo chmod -R 755 /var/www/string-analyzer-api/bootstrap/cache
   sudo chmod 644 /var/www/string-analyzer-api/database/database.sqlite
   ```

4. **Configure Nginx:**
   ```bash
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
   ```

5. **Update environment for production:**
   ```bash
   sudo nano /var/www/string-analyzer-api/.env
   # Set APP_ENV=production
   # Set APP_DEBUG=false
   ```

#### Step 5: GitHub Actions Setup
**Required GitHub Secrets:**
- `EC2_HOST`: Your EC2 instance public IP
- `EC2_USERNAME`: SSH username (ubuntu)
- `EC2_SSH_KEY`: Your private SSH key content

**To add secrets:**
1. Go to your GitHub repo > Settings > Secrets and variables > Actions
2. Add each secret with the corresponding value

#### Step 6: Verify Deployment
- Visit `http://your-ec2-ip` to see the API health check
- Test endpoints: `http://your-ec2-ip/strings`

#### Troubleshooting:
```bash
# Check Nginx status
sudo systemctl status nginx

# Check PHP-FPM status
sudo systemctl status php8.3-fpm

# Check Nginx error logs
sudo tail -f /var/log/nginx/error.log

# Check Laravel logs
sudo tail -f /var/www/string-analyzer-api/storage/logs/laravel.log
```

## License

MIT License