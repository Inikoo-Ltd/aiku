<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('pallet_return_items', function (Blueprint $table) {
            $table->decimal('quantity_not_picked', 16, 6)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('pallet_return_items', function (Blueprint $table) {
            $table->dropColumn('quantity_not_picked');
        });
    }
};
