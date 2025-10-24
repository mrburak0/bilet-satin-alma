# docker/Dockerfile
FROM php:8.3-apache

RUN apt-get update && apt-get install -y libsqlite3-dev \
 && docker-php-ext-install pdo pdo_sqlite \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Tüm repo içeriğini kopyala
COPY . /var/www/html

# Apache DocumentRoot'u public yap
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/app.conf \
 && a2enconf app

RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

# İzinler
RUN mkdir -p /var/www/html/database \
 && chown -R www-data:www-data /var/www/html

EXPOSE 80
