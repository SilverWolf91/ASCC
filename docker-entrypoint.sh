#!/bin/bash
PORT="${PORT:-80}"

sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf

# Fix para Railway: Forzar solo un MPM activo para evitar el error "More than one MPM loaded"
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# Railway inyecta las variables, pero Apache no se las pasa a PHP por defecto.
# Así que las guardamos en un .env que la app ya sabe leer.
cat <<EOF > /var/www/html/ascc/.env
DB_HOST=$DB_HOST
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS
DB_PORT=$DB_PORT
EOF

exec "$@"
