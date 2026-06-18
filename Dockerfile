FROM php:8.2-apache

# Install extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli mbstring zip gd \
    && apt-get clean

# Fix MPM conflict: forcefully remove all MPM configs, enable only prefork
RUN cd /etc/apache2/mods-enabled && \
    rm -f mpm_event.conf mpm_event.load mpm_worker.conf mpm_worker.load && \
    ln -sf ../mods-available/mpm_prefork.conf mpm_prefork.conf && \
    ln -sf ../mods-available/mpm_prefork.load mpm_prefork.load && \
    a2enmod rewrite

# Copy app
COPY . /var/www/html/
RUN rm -f /var/www/html/index.html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/tmp/mpdf \
    && chmod -R 777 /var/www/html/tmp \
    && chmod -R 777 /var/www/html/uploads

# Apache config — allow .htaccess
RUN printf '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/venuepro.conf \
    && a2enconf venuepro

EXPOSE 80
CMD ["apache2-foreground"]
