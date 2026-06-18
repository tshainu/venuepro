FROM php:8.2-fpm

# Install nginx, supervisor, PHP extensions
RUN apt-get update && apt-get install -y \
    nginx supervisor \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli mbstring zip gd \
    && apt-get clean

# Nginx config
RUN cat > /etc/nginx/sites-available/default << 'NGINX'
server {
    listen 80;
    root /var/www/html;
    index index.php index.html;
    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX

# Supervisor config
RUN mkdir -p /etc/supervisor/conf.d && cat > /etc/supervisor/conf.d/services.conf << 'SUPER'
[supervisord]
nodaemon=true
logfile=/dev/null
logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
SUPER

# Copy app
COPY . /var/www/html/
RUN rm -f /var/www/html/index.html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/tmp/mpdf \
    && chmod -R 777 /var/www/html/tmp \
    && chmod -R 777 /var/www/html/uploads

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
