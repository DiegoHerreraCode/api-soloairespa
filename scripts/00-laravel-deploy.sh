#!/usr/bin/env bash
echo "Iniciando despliegue de Laravel 13 en Render..."

# Cachear configuración, rutas y vistas para máximo rendimiento
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Forzar el enlace simbólico del storage público
echo "Generando enlace simbólico para /public/storage..."
php artisan storage:link --force

# Ejecutar migraciones si la base de datos está disponible
echo "Ejecutando migraciones de base de datos..."
php artisan migrate --force
