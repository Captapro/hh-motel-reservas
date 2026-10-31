<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cupón personal: de un solo cliente (ej. el 10% por dejar su opinión);
        // nunca se muestra en el catálogo público.
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('code')->constrained()->nullOnDelete();
            $table->string('origin', 30)->nullable()->after('customer_id');
        });

        // Cuándo se le pidió la opinión de esta estadía (una sola vez).
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('review_requested_at')->nullable();
        });

        Schema::table('guest_reviews', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('booking_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guest_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('review_requested_at');
        });
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('origin');
        });
    }
};
