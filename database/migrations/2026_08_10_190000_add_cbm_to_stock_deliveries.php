<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_deliveries', 'cbm')) {
                $table->decimal('cbm', 16, 3)->nullable()->comment('carton cubic meters');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('stock_deliveries', 'cbm')) {
                $table->dropColumn('cbm');
            }
        });
    }
};
