#syntax=docker/dockerfile:1

FROM php:8.1-cli-alpine
RUN docker-php-ext-install pdo pdo_mysql exif
COPY . /app
WORKDIR /app

CMD php bin/server.php