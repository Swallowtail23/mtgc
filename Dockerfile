FROM php:8.2-apache

# Install packages required for Composer
RUN apt-get update && apt-get install -y git unzip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/mtgnew
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot ${APACHE_DOCUMENT_ROOT}#' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's#/var/www/html#${APACHE_DOCUMENT_ROOT}#' /etc/apache2/apache2.conf

# Copy source
COPY . /var/www/mtgnew

WORKDIR /var/www/mtgnew

# Install PHP dependencies
RUN composer install --no-dev --no-interaction --prefer-dist

EXPOSE 80

CMD ["apache2-foreground"]
