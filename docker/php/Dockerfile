# ==============================================================================
# CONECTA EGRESSO (SEJUS/ES) - PHP 8.3 FPM Container
# ==============================================================================
FROM php:8.3-fpm-bookworm

# Evitar prompts interativos durante o build
ENV DEBIAN_FRONTEND=noninteractive

# Instalacao de dependencias do sistema operacional
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    libssl-dev \
    curl \
    git \
    unzip \
    zip \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Configuracao e instalacao das extensoes PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        intl \
        bcmath \
        opcache \
        pcntl \
        posix \
        mbstring \
        xml

# Instalacao da extensao Redis via PECL
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

# Instalacao do Composer 2 Oficial (Multi-stage copy)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Configuracao de usuario nao-root (UID/GID 1000) para conformidade de permissoes
ARG PUID=1000
ARG PGID=1000
RUN groupmod -o -g ${PGID} www-data || true \
    && usermod -o -u ${PUID} -g www-data www-data || true

# Diretorio de trabalho
WORKDIR /var/www/html

# Configuracao de permissoes para storage e cache do Laravel
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
