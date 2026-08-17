# ==========================================
# 1. Base Stage: PHP 8.5 Runtime
# ==========================================
FROM dunglas/frankenphp AS base

ENV DEBIAN_FRONTEND=noninteractive

# System dependencies & Symfony extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    && rm -rf /lib/apt/lists/*

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    apcu

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

# ==========================================
# 2. Development Stage (Local environment)
# ==========================================
FROM base AS dev

RUN install-php-extensions xdebug

ENV APP_ENV=dev \
    APP_DEBUG=1

EXPOSE 80

# ==========================================
# 3. Build Stage: Production Vendor Setup
# ==========================================
FROM base AS builder

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && composer run-script post-install-cmd \
    && bin/console cache:warmup

# ==========================================
# 4. Final Production Stage
# ==========================================
FROM dunglas/frankenphp AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    FRANKENPHP_CONFIG="worker ./public/index.php"

COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d
COPY --from=builder /app /app

WORKDIR /app

# Production OPcache tuning
RUN echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

EXPOSE 80 443

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
