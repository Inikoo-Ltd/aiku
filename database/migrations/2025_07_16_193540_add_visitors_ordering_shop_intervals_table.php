<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Jul 2025 08:49:27 British Summer Time, Sheffield, UK
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

        Schema::table('shop_ordering_intervals', function (Blueprint $table) {
            $this->unsignedIntegerDateIntervals($table, [
                'visitors',
            ]);
        });



    }


    public function down(): void
    {
        $tables = [
            'shop_ordering_intervals',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columnsToDrop = collect(\DB::select("
                    SELECT column_name FROM information_schema.columns
                    WHERE table_name = '$tableName'
                    AND (column_name LIKE '%visitors%')
                "))->pluck('column_name')->toArray();

                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
