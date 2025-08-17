FROM php:8.2-cli

WORKDIR /app
COPY . /app

# Aller dans le dossier public où il y a index.php
WORKDIR /app/public

# Render choisit un port automatiquement via la variable $PORT
EXPOSE 10000

# Lancer le serveur PHP intégré en écoutant sur $PORT
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT}"]

