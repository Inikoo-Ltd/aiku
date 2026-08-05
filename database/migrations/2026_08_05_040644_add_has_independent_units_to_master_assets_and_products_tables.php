<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['master_assets', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('has_independent_units')->default(false)->comment(
                    'Units are set by hand instead of being read off the trade unit composition'
                );
            });
        }

        $this->seedAssortments();
    }

    /**
     * Assortments hold many different trade units that share a quantity by coincidence, so their
     * composition cannot tell us a pack size. They are opted out up front, otherwise the first
     * composition edit would overwrite the units somebody set by hand.
     */
    private function seedAssortments(): void
    {
        $assortmentIDs = DB::table('model_has_trade_units')
            ->where('model_type', 'MasterAsset')
            ->groupBy('model_id')
            ->havingRaw('count(*) > ?', [3])
            ->pluck('model_id');

        DB::table('master_assets')
            ->where(fn ($query) => $query->whereIn('id', $assortmentIDs)->orWhere('code', 'ilike', '%-st'))
            ->update(['has_independent_units' => true]);

        DB::table('products')
            ->whereIn('master_product_id', DB::table('master_assets')->where('has_independent_units', true)->select('id'))
            ->update(['has_independent_units' => true]);
    }

    public function down(): void
    {
        foreach (['master_assets', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('has_independent_units');
            });
        }
    }
};
