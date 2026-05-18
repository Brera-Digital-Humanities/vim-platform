FROM php:8.3-apache

# Estensioni richieste per Winter CMS: curl, gd, mbstring, openssl, pdo, simplexml, zip
# Estensione raccomandata: pdo_sqlite
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libonig-dev \
    libsqlite3-dev \
    libxml2-dev \
    libzip-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
    curl \
    gd \
    mbstring \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    simplexml \
    zip \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer CLI (per create-project)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN a2enmod rewrite headers expires auth_basic authn_file

WORKDIR /var/www/html
