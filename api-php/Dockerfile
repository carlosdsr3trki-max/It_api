FROM php:8.2-cli

# Extensiones para MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Carpeta de la app
WORKDIR /app
COPY . /app

# Railway expone el puerto en la variable $PORT
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t /app"]