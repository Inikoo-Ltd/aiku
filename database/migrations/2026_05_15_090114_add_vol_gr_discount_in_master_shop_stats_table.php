<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('master_shop_stats', 'number_master_families_with_vol_gr_discount')) {
            Schema::table('master_shop_stats', function (Blueprint $table) {
                $table->unsignedInteger('number_master_families_with_vol_gr_discount')->default(0);
            });
        }
    }

    public function down(): void
    {

        if (Schema::hasColumn('master_shop_stats', 'number_master_families_with_vol_gr_discount')) {
            Schema::table('master_shop_stats', function (Blueprint $table) {
                $table->dropColumn('number_master_families_with_vol_gr_discount');
            });
        }
    }
};
