# Use PHP CLI image
FROM php:8.4-cli

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Optional: tools Composer may use and PHP extensions
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libpq-dev \
 && docker-php-ext-install pdo pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# Set workdir
WORKDIR /app

# Install PHP dependencies
COPY composer.json composer.lock* ./
RUN composer install --prefer-dist --no-interaction --no-progress || true

# Copy source
COPY . .

# Expose app port
EXPOSE 8080

# Start the built-in PHP server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]