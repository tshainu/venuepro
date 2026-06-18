FROM php:8.2-apache

# Install extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli mbstring zip gd

# Fix MPM conflict cleanly
RUN if a2query -m mpm_event 2>/dev/null; then a2dismod mpm_event; fi && \
    if ! a2query -m mpm_prefork 2>/dev/null; then a2enmod mpm_prefork; fi && \
    a2enmod rewrite

# Copy app
COPY . /var/www/html/

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
