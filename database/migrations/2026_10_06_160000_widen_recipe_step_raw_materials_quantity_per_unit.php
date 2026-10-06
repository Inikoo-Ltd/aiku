<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('recipe_step_raw_materials', function (Blueprint $table) {
            $table->decimal('quantity_per_unit', 18, 8)->change();
        });
    }

    public function down(): void
    {
        Schema::table('recipe_step_raw_materials', function (Blueprint $table) {
            $table->decimal('quantity_per_unit', 16, 4)->change();
        });
    }
};
