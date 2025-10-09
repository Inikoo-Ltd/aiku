<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Aug 2025 18:10:00 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasDateIntervalsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasDateIntervalsStats;

    public function up(): void
    {
        Schema::table('master_shop_sales_intervals', function (Blueprint $table) {
            $this->decimalDateIntervals($table, [
                'baskets_created_grp_currency',
                'baskets_updated_grp_currency',
            ]);
        });
    }


    public function down(): void
    {
        Schema::table('master_shop_sales_intervals', function (Blueprint $table) {
            $columnsToDrop = collect(
                \DB::select(
                    "
                    SELECT column_name FROM information_schema.columns
                    WHERE table_name = 'master_shop_sales_intervals'
                    AND (column_name LIKE '%baskets_created_grp_currency%' OR column_name LIKE '%baskets_updated_grp_currency%')
                "
                )
            )->pluck('column_name')->toArray();

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
