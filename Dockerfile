FROM php:8.2-fpm

# Install nginx, supervisor, PHP extensions
RUN apt-get update && apt-get install -y \
    nginx supervisor \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli mbstring zip gd \
    && apt-get clean

# Supervisor config
RUN mkdir -p /etc/supervisor/conf.d
COPY docker/supervisord.conf /etc/supervisor/conf.d/services.conf

# Nginx base config (port set at runtime via entrypoint)
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Copy app
RUN echo "DEPLOY_$(date +%s)" > /dev/null
COPY . /var/www/html/
RUN rm -f /var/www/html/index.html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/tmp/mpdf \
    && chmod -R 777 /var/www/html/tmp \
    && chmod -R 777 /var/www/html/uploads

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
CMD ["/entrypoint.sh"]
