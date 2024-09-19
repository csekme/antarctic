# syntax=docker/dockerfile:1
FROM php:8.2-apache-bullseye

# install xdebug
RUN pecl install xdebug
RUN apt-get update
# install postgresql connector and mysql connector
RUN apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && docker-php-ext-install mysqli pdo pdo_mysql

RUN apt-get update; \
    apt-get install -y libmagickwand-dev; \
    pecl install imagick; \
    docker-php-ext-enable imagick;


# copy over the config files
COPY xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY error_reporting.ini /usr/local/etc/php/conf.d/error_reporting.ini

# enable the extension
RUN docker-php-ext-enable xdebug

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions yaml


# enable rewrite mode
RUN a2enmod rewrite
CMD ["apache2-foreground"]