<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->boolean('is_gb_origin')->default(false)->index();
        });

        Schema::table('org_stock_families', function (Blueprint $table) {
            $table->boolean('gb_separate_pallet')->default(true);
        });

        Schema::table('org_partners', function (Blueprint $table) {
            $table->boolean('split_gb_origin')->default(false);
            $table->foreignId('gb_goods_out_location_id')->nullable()->constrained('locations')->nullOnDelete();
        });

        DB::statement("update stocks set is_gb_origin = true where exists (
            select 1 from model_has_trade_units
            join trade_units on trade_units.id = model_has_trade_units.trade_unit_id
            join countries on countries.id = trade_units.origin_country_id
            where model_has_trade_units.model_type = 'Stock' and model_has_trade_units.model_id = stocks.id and countries.code = 'GB'
        )");
    }

    public function down(): void
    {
        Schema::table('org_partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gb_goods_out_location_id');
            $table->dropColumn('split_gb_origin');
        });

        Schema::table('org_stock_families', function (Blueprint $table) {
            $table->dropColumn('gb_separate_pallet');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('is_gb_origin');
        });
    }
};
