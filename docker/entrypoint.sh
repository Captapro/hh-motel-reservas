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
echo "DEBUG: ADMIN_EMAIL is set = $([ -n "$ADMIN_EMAIL" ] && echo yes || echo no); ADMIN_PASSWORD is set = $([ -n "$ADMIN_PASSWORD" ] && echo yes || echo no)"
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    EMPTY_USERS=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null | tail -1)
    echo "DEBUG: EMPTY_USERS = [$EMPTY_USERS]"
    if [ "$EMPTY_USERS" = "0" ]; then
        php artisan tinker --execute="App\Models\User::create(['name' => 'Admin', 'email' => env('ADMIN_EMAIL'), 'password' => bcrypt(env('ADMIN_PASSWORD')), 'role' => 'administrador', 'is_active' => true]); echo 'admin creado';"
    fi
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
