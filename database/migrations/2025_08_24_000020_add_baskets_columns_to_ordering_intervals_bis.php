<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Aug 2025 18:09:55 Central Standard Time, Mexico City, Mexico
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
        Schema::table('master_shop_ordering_intervals', function (Blueprint $table) {
            $this->unsignedIntegerDateIntervals($table, [
                'baskets_created',
                'baskets_updated',
            ]);
        });
    }


    public function down(): void
    {
        $tables = [
            'master_shop_ordering_intervals',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columnsToDrop = collect(
                    \DB::select(
                        "
                    SELECT column_name FROM information_schema.columns
                    WHERE table_name = '$tableName'
                    AND (column_name LIKE '%baskets_created%' OR column_name LIKE '%baskets_updated%')
                "
                    )
                )->pluck('column_name')->toArray();

                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
