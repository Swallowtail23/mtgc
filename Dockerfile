FROM php:8.2-apache

# Copy application source
COPY . /var/www/html

# Install site configuration
COPY setup/mtgc.conf /etc/apache2/sites-available/mtgc.conf
RUN a2ensite mtgc.conf && a2dissite 000-default.conf

# Entrypoint setup
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2ctl", "-D", "FOREGROUND"]
