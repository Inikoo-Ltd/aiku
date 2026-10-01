<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_asset_stats', function (Blueprint $table) {
            $table->jsonb('price_tip_check')->nullable();
            $table->timestampTz('price_tip_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('master_asset_stats', function (Blueprint $table) {
            $table->dropColumn(['price_tip_check', 'price_tip_checked_at']);
        });
    }
};
