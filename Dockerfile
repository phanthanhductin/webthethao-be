FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libonig-dev libpng-dev libjpeg-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo pdo_mysql intl gd zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Cài vendor nhưng KHÔNG cache config ở build-time
RUN composer install --no-dev --optimize-autoloader \
 && php artisan key:generate --force || true \
 && php artisan storage:link || true

# Start: clear rồi cache lại bằng ENV runtime; tùy chọn migrate khi bật RUN_MIGRATIONS=true
CMD sh -c "\
  php artisan config:clear && \
  php artisan route:clear  && \
  php artisan view:clear   && \
  php artisan config:cache && \
  php artisan route:cache  && \
  if [ \"$$RUN_MIGRATIONS\" = \"true\" ]; then php artisan migrate --force; fi && \
  php -S 0.0.0.0:8080 -t public \
"

