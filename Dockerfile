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
        libgmp-dev \
        unzip \
        git \
        ffmpeg \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip gd mbstring opcache gmp \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*

# pg_dump para el respaldo diario: la versión del cliente debe igualar a la del servidor (16),
# y Debian trae otra, así que se instala desde el repositorio oficial de PostgreSQL.
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates curl gnupg \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && . /etc/os-release \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt ${VERSION_CODENAME}-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update && apt-get install -y --no-install-recommends postgresql-client-16 \
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
