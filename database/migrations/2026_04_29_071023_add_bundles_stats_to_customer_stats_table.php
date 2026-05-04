<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->unsignedMediumInteger('number_bundles')->default(0)->after('number_current_portfolios');
            $table->unsignedMediumInteger('number_current_bundles')->default(0)->after('number_bundles');
        });
    }

    public function down(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->dropColumn(['number_bundles', 'number_current_bundles']);
        });
    }
};
