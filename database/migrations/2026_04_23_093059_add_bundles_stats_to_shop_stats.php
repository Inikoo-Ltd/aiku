<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->unsignedMediumInteger('number_bundles')->default(0)->after('number_returns_state_cancelled');
            $table->unsignedMediumInteger('number_bundles_state_active')->default(0)->after('number_bundles');
            $table->unsignedMediumInteger('number_bundles_state_discontinuing')->default(0)->after('number_bundles_state_active');
        });
    }

    public function down(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->dropColumn(['number_bundles', 'number_bundles_state_active', 'number_bundles_state_discontinuing']);
        });
    }
};
