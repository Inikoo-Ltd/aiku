<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['shop_id', 'is_for_sale', 'state']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'is_for_sale', 'state']);
        });
    }
};
