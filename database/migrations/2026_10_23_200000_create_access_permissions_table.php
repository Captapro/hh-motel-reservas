<?php

use App\Support\AccessSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('section', 30);
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['role', 'section']);
        });

        $now = now();
        foreach (array_keys(AccessSections::ROLES) as $role) {
            foreach (array_keys(AccessSections::SECTIONS) as $section) {
                DB::table('access_permissions')->insert([
                    'role' => $role, 'section' => $section,
                    'allowed' => AccessSections::DEFAULTS[$role][$section] ?? false,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('access_permissions');
    }
};
