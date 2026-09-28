FROM php:8.2-apache

# zip: necesario para leer .docx (PHPWord)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

COPY docker/templimail.conf /etc/apache2/conf-available/templimail.conf
RUN a2enconf templimail

# Subida de plantillas (.docx / .pdf hasta 5 MB)
RUN { echo 'upload_max_filesize=8M'; echo 'post_max_size=8M'; } \
    > /usr/local/etc/php/conf.d/templimail.ini

WORKDIR /var/www/html
