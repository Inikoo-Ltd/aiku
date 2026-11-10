<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_asset_competitor_products', function (Blueprint $table) {
            $table->boolean('units_verified')->default(false);
            $table->timestampTz('checked_at')->nullable()->index();
            $table->text('check_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('master_asset_competitor_products', function (Blueprint $table) {
            $table->dropColumn(['units_verified', 'checked_at', 'check_note']);
        });
    }
};
