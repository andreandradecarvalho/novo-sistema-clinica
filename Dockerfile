FROM php:8.5.12-cli-bookworm@sha256:104890ada56869090c9cd0ec709fe205834d3e383b46d9995dc16cd6388a9302
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install -j2 bcmath intl pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*
RUN curl -fsSL https://getcomposer.org/download/2.11.1/composer.phar -o /usr/local/bin/composer \
    && echo '6f1b891e1d4927d62740276c540fb490e47689e19e102c49e8da849794882f8c  /usr/local/bin/composer' | sha256sum -c - \
    && chmod +x /usr/local/bin/composer
WORKDIR /app
