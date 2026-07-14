<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('packaging_amount', 16, 2)->default(0)->after('insurance_amount');
            $table->decimal('leaflet_amount', 16, 2)->default(0)->after('packaging_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['packaging_amount', 'leaflet_amount']);
        });
    }
};
