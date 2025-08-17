# Utiliser PHP 8.2 avec Apache
FROM php:8.2-apache

# Copier ton code dans le dossier par défaut d'Apache
COPY . /var/www/html/

# Donner les bons droits
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exposer le port 80
EXPOSE 80
