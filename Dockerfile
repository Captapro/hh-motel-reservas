# Apache + mod_php: atiende varias solicitudes a la vez (a diferencia de `php artisan serve`,
# que procesa de a una) y sirve las imágenes y el CSS directamente, sin pasar por PHP.
FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip gd mbstring opcache \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Configuración de producción de PHP y de Apache
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-hh.ini
COPY docker/apache-mpm.conf /etc/apache2/conf-available/hh-mpm.conf
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enconf hh-mpm

# Sin --no-dev: el seeder de arranque (DatabaseSeeder -> UserFactory) usa
# fake() de fakerphp/faker, que es una dependencia de desarrollo. Para una
# imagen de producción "de verdad" habría que sacar el seeder de la
# dependencia de Faker y volver a --no-dev.
RUN composer install --optimize-autoloader --no-interaction \
    && php artisan config:clear \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV PORT=10000
EXPOSE 10000

CMD ["/entrypoint.sh"]
