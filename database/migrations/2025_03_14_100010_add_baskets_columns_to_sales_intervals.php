<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 22:06:57 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::table('group_sales_intervals', function (Blueprint $table) {
            $this->decimalDateIntervals($table, [
                'baskets_created_grp_currency',
                'baskets_updated_grp_currency',
            ]);
        });
        Schema::table('organisation_sales_intervals', function (Blueprint $table) {
            $this->decimalDateIntervals($table, [
                'baskets_created_org_currency',
                'baskets_updated_org_currency',
                'baskets_created_grp_currency',
                'baskets_updated_grp_currency',
            ]);
        });
        Schema::table('shop_sales_intervals', function (Blueprint $table) {
            $this->decimalDateIntervals($table, [
                'baskets_created',
                'baskets_updated',
                'baskets_created_org_currency',
                'baskets_updated_org_currency',
                'baskets_created_grp_currency',
                'baskets_updated_grp_currency',
            ]);
        });
    }


    public function down(): void
    {

        Schema::table('group_sales_intervals', function (Blueprint $table) {
            $columnsToDrop = collect(
                \DB::select(
                    "
                    SELECT column_name FROM information_schema.columns 
                    WHERE table_name = 'group_sales_intervals' 
                    AND (column_name LIKE '%baskets_created_grp_currency%' OR column_name LIKE '%baskets_updated_grp_currency%')
                "
                )
            )->pluck('column_name')->toArray();

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::table('organisation_sales_intervals', function (Blueprint $table) {
            $columnsToDrop = collect(
                \DB::select(
                    "
                    SELECT column_name FROM information_schema.columns 
                    WHERE table_name = 'organisation_sales_intervals' 
                    AND (column_name LIKE '%baskets_created_org_currency%' OR 
                         column_name LIKE '%baskets_updated_org_currency%' OR 
                         column_name LIKE '%baskets_created_grp_currency%' OR 
                         column_name LIKE '%baskets_updated_grp_currency%' 
                        )
                "
                )
            )->pluck('column_name')->toArray();

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::table('shop_sales_intervals', function (Blueprint $table) {
            $columnsToDrop = collect(
                \DB::select(
                    "
                    SELECT column_name FROM information_schema.columns 
                    WHERE table_name = 'shop_sales_intervals' 
                    AND (column_name LIKE '%baskets_created%' OR 
                         column_name LIKE '%baskets_updated%' OR 
                         column_name LIKE '%baskets_created_org_currency%' OR 
                         column_name LIKE '%baskets_updated_org_currency%' OR
                         column_name LIKE '%baskets_created_grp_currency%' OR 
                         column_name LIKE '%baskets_updated_grp_currency%'
                        )
                "
                )
            )->pluck('column_name')->toArray();

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });



    }
};
