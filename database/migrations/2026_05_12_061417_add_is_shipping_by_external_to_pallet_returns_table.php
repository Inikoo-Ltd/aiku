<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('pallet_returns', 'is_shipping_by_external')) {
                $table->boolean('is_shipping_by_external')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            if (Schema::hasColumn('pallet_returns', 'is_shipping_by_external')) {
                $table->dropColumn('is_shipping_by_external');
            }
        });
    }
};
