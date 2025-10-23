# ===== PHP 8.2 + extensions cho Laravel =====
FROM php:8.2-cli

# System deps
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libonig-dev libpng-dev libjpeg-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo pdo_mysql intl gd zip \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# App
WORKDIR /app
COPY . .

# Cài vendor + cache cấu hình (không cần .env vì ENV sẽ set trên Render)
RUN composer install --no-dev --optimize-autoloader \
 && php artisan key:generate --force || true \
 && php artisan storage:link || true \
 && php artisan config:cache && php artisan route:cache && php artisan view:cache

# Expose 8080 trong container (Render sẽ map $PORT)
EXPOSE 8080

# Start PHP built-in server trỏ vào public
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
