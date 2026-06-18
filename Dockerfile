FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV APACHE_LOG_DIR=/var/log/apache2
ENV APACHE_RUN_DIR=/var/run/apache2
ENV APACHE_LOCK_DIR=/var/lock/apache2
ENV APACHE_PID_FILE=/var/run/apache2/apache2.pid

RUN apt-get update && apt-get install -y \
    apache2 \
    php8.1 php8.1-mysql php8.1-mbstring php8.1-zip php8.1-gd \
    php8.1-xml php8.1-curl libapache2-mod-php8.1 \
    && a2enmod rewrite \
    && apt-get clean

# Copy app
COPY . /var/www/html/
RUN rm -f /var/www/html/index.html

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/tmp/mpdf \
    && chmod -R 777 /var/www/html/tmp \
    && chmod -R 777 /var/www/html/uploads

# Apache config
RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80
CMD ["apache2ctl", "-D", "FOREGROUND"]
