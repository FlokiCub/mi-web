# Dockerfile para Laravel 12 en fly.io
FROM php:8.2-fpm-alpine

# Instalar dependencias del sistema
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    nodejs \
    npm \
    linux-headers \
    ${PHPIZE_DEPS}

# Configurar GD
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_sqlite \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    xml \
    opcache \
    intl

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos de configuración primero (para cache de capas)
COPY composer.json composer.lock ./

# Instalar dependencias PHP (sin dev, optimizado)
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copiar package.json y package-lock.json
COPY package.json package-lock.json* ./

# Instalar dependencias Node y build assets
RUN npm ci --prefer-offline --no-audit --progress=false
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# Copiar el resto de la aplicación
COPY . .

# Generar key de aplicación si no existe
RUN php artisan key:generate --force || true

# Optimizar para producción
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Configurar Nginx
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Configurar Supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Exponer puerto
EXPOSE 8080

# Iniciar servicios
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]