FROM php:8.2-apache

# preseed timezone and suppress prompts
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Australia/Brisbane

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
       git unzip \
       libpng-dev \
       tzdata \
       default-mysql-client \
       mailutils \
       libicu-dev \
       g++ \
 # ensure /etc/localtime matches TZ, then rebuild the tzdata config
 && ln -fs /usr/share/zoneinfo/$TZ /etc/localtime \
 && dpkg-reconfigure --frontend noninteractive tzdata \
 \
 # build & enable the intl extension
 && docker-php-ext-configure intl \
 && docker-php-ext-install intl \
 \
 # clean up
 && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/mtgnew
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot ${APACHE_DOCUMENT_ROOT}#' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's#/var/www/html#${APACHE_DOCUMENT_ROOT}#' /etc/apache2/apache2.conf

# Ensure logs directory exists for mounting
RUN mkdir -p /var/log/mtg && chown -R www-data:www-data /var/log/mtg

# Copy custom Apache configuration and enable required modules
COPY setup/mtgc_ctr.conf /etc/apache2/sites-available/mtgc.conf
RUN a2dissite 000-default.conf && a2ensite mtgc.conf \
    && a2enmod rewrite expires headers deflate

# Copy source
COPY . /var/www/mtgnew

# Copy setup scripts for container startup and cron use
RUN mkdir -p /opt/mtg
COPY setup/*.sh /opt/mtg/
RUN find /opt/mtg -name "*.sh" -exec sed -i 's/\r$//' {} \;
RUN chmod +x /opt/mtg/*.sh

# Final working directory
WORKDIR /var/www/mtgnew

# Install PHP dependencies
RUN composer install --no-dev --no-interaction --prefer-dist

# Install required PHP extensions
RUN docker-php-ext-install gd mysqli

# Optional: Set fallback ownership of important mount targets
RUN chown -R www-data:www-data /mnt/data/cardimg || true \
 && chown -R www-data:www-data /opt/mtg || true

EXPOSE 80

# Entry point and wait for DB, then launch Apache
COPY setup/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/opt/mtg/wait-for-mysql.sh", "db", "apache2-foreground"]
