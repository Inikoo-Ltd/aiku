<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 22:06:54 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::table('group_ordering_intervals', function (Blueprint $table) {
            $this->unsignedIntegerDateIntervals($table, [
                'baskets_created',
                'baskets_updated',
            ]);
        });
        Schema::table('organisation_ordering_intervals', function (Blueprint $table) {
            $this->unsignedIntegerDateIntervals($table, [
                'baskets_created',
                'baskets_updated',
            ]);
        });
        Schema::table('shop_ordering_intervals', function (Blueprint $table) {
            $this->unsignedIntegerDateIntervals($table, [
                'baskets_created',
                'baskets_updated',
            ]);
        });



    }


    public function down(): void
    {
        $tables = [
            'organisation_ordering_intervals',
            'shop_ordering_intervals',
            'group_ordering_intervals',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columnsToDrop = collect(\DB::select("
                    SELECT column_name FROM information_schema.columns 
                    WHERE table_name = '$tableName' 
                    AND (column_name LIKE '%baskets_created%' OR column_name LIKE '%baskets_updated%')
                "))->pluck('column_name')->toArray();

                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
