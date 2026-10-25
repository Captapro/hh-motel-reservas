#!/bin/sh
set -e

php artisan migrate --force

# Los seeders base (categorías, habitaciones, tarifas, cupones, métodos de
# pago, productos) usan insert() plano, no son idempotentes -- solo se
# corren una vez, cuando la base recién creada todavía está vacía.
EMPTY_DB=$(php artisan tinker --execute="echo App\Models\RoomCategory::count();" 2>/dev/null | tail -1)
if [ "$EMPTY_DB" = "0" ]; then
    php artisan db:seed --force
fi

# Red de seguridad: si la base quedó sin ningún usuario (base nueva, o la
# anterior se borró como pasó una vez) y hay credenciales de admin en el
# entorno, crea esa cuenta -- sin esto, una base vacía deja el sistema sin
# forma de entrar por el panel. No hace nada si ya existe algún usuario.
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    EMPTY_USERS=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null | tail -1)
    if [ "$EMPTY_USERS" = "0" ]; then
        php artisan tinker --execute="App\Models\User::create(['name' => 'Admin', 'email' => env('ADMIN_EMAIL'), 'password' => bcrypt(env('ADMIN_PASSWORD')), 'role' => 'administrador', 'is_active' => true]); echo 'admin creado';"
    fi
fi

# Cuenta de soporte (master): se crea o se repara desde SUPPORT_EMAIL y
# SUPPORT_PASSWORD, que se definen en Render. No pisa la contraseña de una
# cuenta que ya existe.
php artisan app:soporte-sincronizar || true

# Barrido de retenciones: cada 30 s libera las reservas del catálogo que no pagaron a tiempo
# (además se libera al crear reservas y al abrir el tablero).
( while true; do sleep 30; php artisan app:reservas-expirar >/dev/null 2>&1 || true; done ) &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
