FROM php:8.2-apache
RUN a2enmod rewrite
# DocumentRoot par défaut = /var/www/html (on ne le change pas)
COPY . /var/www/html

EXPOSE 8080
CMD ["apache2-foreground"]

