<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('external_shop_platform_status')->default(false);
            $table->dateTimeTz('external_shop_connection_failed_at')->nullable();
            $table->text('external_shop_connection_error')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'external_shop_platform_status',
                'external_shop_connection_failed_at',
                'external_shop_connection_error'
            ]);
        });
    }
};
