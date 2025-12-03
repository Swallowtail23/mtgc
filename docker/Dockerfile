FROM php:8.2-apache-bookworm

# preseed timezone and suppress prompts
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Australia/Hobart

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

# Ensure mount targets exist in the image; ownership is best-effort
RUN mkdir -p /mnt/data/cardimg /mnt/data/config /var/log/mtg /opt/mtg \
    && chown -R www-data:www-data /mnt/data /var/log/mtg /opt/mtg || true

# Copy custom Apache configuration and enable required modules
COPY docker/mtgc_ctr.conf /etc/apache2/sites-available/mtgc.conf
RUN a2dissite 000-default.conf && a2ensite mtgc.conf \
    && a2enmod rewrite expires headers deflate

# Copy source
COPY . /var/www/mtgnew

# Copy wait-for script into PATH so CMD can use it regardless of host mounts
COPY docker/wait-for-mysql.sh /usr/local/bin/wait-for-mysql.sh
RUN sed -i 's/\r$//' /usr/local/bin/wait-for-mysql.sh \
    && chmod +x /usr/local/bin/wait-for-mysql.sh

# Final working directory
WORKDIR /var/www/mtgnew

# Install required PHP extensions
RUN docker-php-ext-install gd mysqli

# Optional: Set fallback ownership of important mount targets
RUN chown -R www-data:www-data /mnt/data/cardimg || true \
 && chown -R www-data:www-data /mnt/data/config || true \
 && chown -R www-data:www-data /var/log/mtg || true \
 && chown -R www-data:www-data /opt/mtg || true 

EXPOSE 80

# Entry point and wait for DB, then launch Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/local/bin/wait-for-mysql.sh", "db", "apache2-foreground"]
