FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libpng-dev libicu-dev libonig-dev libcurl4-openssl-dev default-mysql-client curl unzip && docker-php-ext-install pdo_mysql mysqli zip gd intl mbstring curl && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY deploy/staging/apache.conf /etc/apache2/conf-available/redfox.conf
RUN a2enconf redfox
COPY . /var/www/html
COPY deploy/staging/entrypoint.sh /usr/local/bin/redfox-entrypoint
RUN chmod +x /usr/local/bin/redfox-entrypoint && chown -R www-data:www-data /var/www/html
ENTRYPOINT ["redfox-entrypoint"]
CMD ["apache2-foreground"]
