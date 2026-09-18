<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->boolean('is_divisible')->default(false);
        });

        DB::table('trade_units')
            ->whereIn('id', function ($query) {
                $query->select('model_has_trade_units.trade_unit_id')
                    ->from('model_has_trade_units')
                    ->join('master_assets', 'master_assets.id', '=', 'model_has_trade_units.model_id')
                    ->join('master_shops', 'master_shops.id', '=', 'master_assets.master_shop_id')
                    ->where('model_has_trade_units.model_type', 'MasterAsset')
                    ->where('master_shops.slug', 'aroma')
                    ->whereRaw('model_has_trade_units.quantity <> round(model_has_trade_units.quantity)');
            })
            ->update(['is_divisible' => true]);
    }

    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropColumn('is_divisible');
        });
    }
};
