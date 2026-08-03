<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('web_block_types', function (Blueprint $table) {
            $table->jsonb('shop_availability')->default(json_encode([]));
        });
    }


    public function down(): void
    {
        Schema::table('web_block_types', function (Blueprint $table) {
            $table->dropColumn([
                'shop_availability'
            ]);
        });
    }
};
