#!/bin/sh
# Use Railway's $PORT or default to 80
APP_PORT=${PORT:-80}
echo "Starting on port $APP_PORT"

# Replace port placeholder in nginx config
sed -i "s/PORT_PLACEHOLDER/$APP_PORT/g" /etc/nginx/nginx.conf

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/services.conf
