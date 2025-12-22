FROM php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev \
    rabbitmq-c-dev \
    autoconf \
    g++ \
    make \
    linux-headers \
    brotli-dev \
    zstd-dev \
    openssl-dev \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    pcntl \
    sockets

# Install Swoole (disable optional features to avoid build issues)
RUN pecl install --nobuild swoole \
    && cd "$(pecl config-get temp_dir)/swoole" \
    && phpize \
    && ./configure --enable-openssl --disable-brotli --disable-zstd \
    && make -j$(nproc) \
    && make install \
    && docker-php-ext-enable swoole

# Install AMQP extension for RabbitMQ
RUN pecl install amqp \
    && docker-php-ext-enable amqp

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first
COPY composer.json composer.lock ./

# Copy packages directory (needed for local path repositories)
COPY packages/ ./packages/

# Install dependencies (packages must exist before composer install)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy rest of application code
COPY . .

# Create necessary directories
RUN mkdir -p var/cache var/log var/data

# Set permissions
RUN chown -R www-data:www-data var

# Default command (can be overridden)
CMD ["php", "server.php"]

