FROM php:8.2-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libonig-dev \
    && docker-php-ext-install curl session pdo pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/*

RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

COPY . /var/www/html/
WORKDIR /var/www/html/

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html
RUN mkdir -p /var/www/html/data && chmod 777 /var/www/html/data

EXPOSE 80
CMD ["apache2-foreground"]