FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libzip-dev libicu-dev unzip git \
 && docker-php-ext-install pdo_mysql mbstring zip intl bcmath \
 && pecl install apcu-5.1.28 && docker-php-ext-enable apcu \
 && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ARG APP_UID=1000
ARG APP_GID=1000
RUN groupadd -g ${APP_GID} hzapp && useradd -u ${APP_UID} -g hzapp -m hzapp
ENV APACHE_RUN_USER=hzapp APACHE_RUN_GROUP=hzapp
COPY containers/php-cache.ini /usr/local/etc/php/conf.d/hz-cache.ini
COPY containers/apache.conf /etc/apache2/sites-available/000-default.conf
COPY containers/php-entrypoint.sh /usr/local/bin/hz-entrypoint
WORKDIR /workspace
ENTRYPOINT ["bash", "/usr/local/bin/hz-entrypoint"]
CMD ["apache2-foreground"]
