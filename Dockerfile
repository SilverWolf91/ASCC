FROM php:8.2-apache

# Extensiones necesarias para PDO + MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar mod_rewrite para .htaccess
RUN a2enmod rewrite

# Copiar el proyecto dentro de /var/www/html/ascc/
# Así las rutas /ascc/... del proyecto funcionan igual que en XAMPP
COPY . /var/www/html/ascc/

# Permisos para la carpeta de uploads (escritura de imágenes)
RUN mkdir -p /var/www/html/ascc/public/uploads/productos \
             /var/www/html/ascc/public/uploads/perfiles \
    && chown -R www-data:www-data /var/www/html/ascc/public/uploads

# Script de arranque que adapta el puerto que Railway asigna dinámicamente
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
