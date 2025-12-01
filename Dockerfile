FROM php:8.2-apache


RUN echo "ServerName mysql" >> /etc/apache2/apache2.conf

RUN a2enmod rewrite

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html

COPY . .


RUN chown -R www-data:www-data /var/www/html