<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** La estadía corta pasa a descuento por tramos: 10%, 15% y máximo 25%, vendiendo hasta 60 minutos antes del cierre. */
    public function up(): void
    {
        DB::table('short_stay_settings')->update(['pricing_mode' => 'tramos', 'min_minutes' => 60]);
    }

    public function down(): void
    {
        DB::table('short_stay_settings')->update(['pricing_mode' => 'proporcional']);
    }
};
