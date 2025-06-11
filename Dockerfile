FROM php:8.2-apache

# Install packages required for Composer
RUN apt-get update && apt-get install -y \
        git unzip \
        libpng-dev \
        tzdata \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/mtgnew
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot ${APACHE_DOCUMENT_ROOT}#' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's#/var/www/html#${APACHE_DOCUMENT_ROOT}#' /etc/apache2/apache2.conf
# Copy the editable .ini template into image
COPY setup/mtg-new.ini /opt/mtg/mtg-new.ini

# Copy custom Apache configuration and enable required modules
COPY setup/mtgc_ctr.conf /etc/apache2/sites-available/mtgc.conf
RUN a2dissite 000-default.conf && a2ensite mtgc.conf \
    && a2enmod rewrite expires headers deflate

# Copy source
COPY . /var/www/mtgnew
# Copy setup scripts for cron jobs
RUN mkdir -p /opt/mtg 
COPY setup/*.sh /opt/mtg/
# Convert CRLF to LF to ensure scripts run in Linux
RUN find /opt/mtg -name "*.sh" -exec sed -i 's/\r$//' {} \;
RUN chmod +x /opt/mtg/*.sh

WORKDIR /var/www/mtgnew

# Install PHP dependencies
RUN composer install --no-dev --no-interaction --prefer-dist

# Install required PHP extensions
RUN docker-php-ext-install gd

EXPOSE 80

CMD ["/opt/mtg/wait-for-mysql.sh", "db", "apache2-foreground"]
