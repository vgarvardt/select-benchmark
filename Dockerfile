FROM php:7.2-fpm-alpine

RUN apk add --no-cache --virtual .ext-deps \
    autoconf \
    g++ \
    make \
    postgresql-dev \
    pcre-dev

RUN apk add --no-cache \
    libpq \
    git

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql

RUN pecl install mongodb && \
    docker-php-ext-enable mongodb

RUN set -ex && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('SHA384', 'composer-setup.php') === '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer

RUN apk del .ext-deps && \
    pecl clear-cache && \
    docker-php-source delete

WORKDIR /app
