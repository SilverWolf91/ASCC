#!/bin/bash
# Railway asigna un puerto aleatorio en $PORT. Apache debe escuchar en ese puerto.
PORT="${PORT:-8080}"

sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf

# Fix para Railway: Forzar solo un MPM activo para evitar el error "More than one MPM loaded"
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

exec "$@"
