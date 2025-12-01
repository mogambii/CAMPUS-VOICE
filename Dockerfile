FROM php:8.2-apache

WORKDIR /var/www/html
COPY . .
RUN a2enmod rewrite
RUN chown -R www-data:www-data /var/www/html