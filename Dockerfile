# SoyaCore — image produksi untuk Render (M3).
# Render tidak punya runtime PHP native, jadi deploy-nya lewat Docker.
FROM php:8.2-apache

# Dependency sistem untuk ekstensi PHP yang dibutuhkan project ini:
# - gd + zip  -> WAJIB untuk maatwebsite/excel (export .xlsx). Tanpa ini
#                `composer install` GAGAL dengan error "ext-gd is missing".
# - libpq-dev -> untuk pdo_pgsql (Supabase/PostgreSQL).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        libpq-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip pdo_pgsql bcmath opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Laravel butuh mod_rewrite untuk pretty URL (public/.htaccess).
# ServerName di-set supaya log Render tidak dipenuhi warning AH00558.
RUN a2enmod rewrite \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf
COPY docker/apache-soyacore.conf /etc/apache2/sites-available/000-default.conf

# Opcache: bikin request jauh lebih ringan di free tier.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install dependency dulu (tanpa kode app) supaya layer ini bisa di-cache
# dan build ulang jadi cepat selama composer.json tidak berubah.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Render menyuntikkan $PORT saat runtime; entrypoint yang mengarahkan Apache.
EXPOSE 10000

ENTRYPOINT ["entrypoint.sh"]
