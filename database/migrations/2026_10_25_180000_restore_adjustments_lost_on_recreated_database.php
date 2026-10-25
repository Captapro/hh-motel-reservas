<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Al recrear la base de producción, las migraciones corrieron antes que
     * los seeders, así que varios ajustes que hacían sobre datos ya
     * existentes (UPDATE) no encontraron nada. Acá se restituyen, pero solo
     * donde el valor sigue vacío: nunca pisan lo que se haya editado a mano.
     */
    public function up(): void
    {
        // Precio de la hora adicional por tarifa (antes: add_extra_hour_price_to_rate_rules).
        foreach (['HH' => 10000, 'HOT' => 15000] as $name => $price) {
            DB::table('rate_rules')->where('name', $name)->where(fn ($q) => $q->whereNull('extra_hour_price')->orWhere('extra_hour_price', 0))->update(['extra_hour_price' => $price]);
        }

        // Edad mínima del cupón de adulto mayor (antes: add_min_age_to_coupons).
        DB::table('coupons')->where('code', 'EXPERTOS EN VIDA')->whereNull('min_age')->update(['min_age' => 65]);

        // Frase de venta del catálogo por categoría (antes: add_sales_tip_to_room_categories).
        $tips = [
            'GO' => 'Ambiente íntimo con mobiliario seleccionado para disfrutar una experiencia diferente.',
            'LITE' => 'Privacidad y comodidad en un ambiente equipado para compartir sin apuros.',
            'NEW LITE' => 'Baño interior con ducha integrada al ambiente, visible desde la cama.',
            'PLUS' => 'Un espacio preparado para disfrutar su mobiliario y vivir una experiencia más intensa.',
            'MAX' => 'Ambiente amplio para explorar y disfrutar en compañía; una de las preferidas para grupos de más de dos personas.',
        ];
        foreach ($tips as $name => $tip) {
            DB::table('room_categories')->where('name', $name)->where(fn ($q) => $q->whereNull('sales_tip')->orWhere('sales_tip', ''))->update(['sales_tip' => $tip]);
        }
    }

    public function down(): void {}
};
