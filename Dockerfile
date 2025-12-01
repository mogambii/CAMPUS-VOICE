FROM php:8.2-apache


RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . .


RUN chown -R www-data:www-data /var/www/html