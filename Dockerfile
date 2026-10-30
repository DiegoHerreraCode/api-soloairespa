FROM php:8.4-fpm-alpine

# Instalar dependencias del sistema
RUN apk add --no-cache \
    nginx \
    bash \
    curl \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    postgresql-dev

# Instalar extensiones de PHP para Laravel 13
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd xml zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html

# Instalar dependencias de producción
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copiar configuración de Nginx
COPY ./docker/nginx.conf /etc/nginx/http.d/default.conf

# Asegurar permisos y preparar directorios de almacenamiento
RUN mkdir -p /var/www/html/storage/app/public \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

# Hacer ejecutable el script de despliegue antes de correrlo
CMD ["/bin/bash", "-c", "chmod +x ./scripts/00-laravel-deploy.sh && ./scripts/00-laravel-deploy.sh && nginx && php-fpm"]
