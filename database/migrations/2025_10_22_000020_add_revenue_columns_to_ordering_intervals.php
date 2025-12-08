<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Oct 2025 11:26:34 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasDateIntervalsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasDateIntervalsStats;

    public function up(): void
    {
        Schema::table('group_ordering_intervals', function (Blueprint $table) {
            $this->decimalDateIntervals($table, [
                'lost_revenue_other_amount_grp_currency',
            ]);
        });
        Schema::table('organisation_ordering_intervals', function (Blueprint $table) {
            $this->decimalDateIntervals($table, [
                'lost_revenue_other_amount_org_currency',
                'lost_revenue_other_amount_grp_currency',
            ]);
        });
        Schema::table('shop_ordering_intervals', function (Blueprint $table) {
            $this->decimalDateIntervals($table, [
                'lost_revenue_other_amount',
                'lost_revenue_other_amount_org_currency',
                'lost_revenue_other_amount_grp_currency',
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
                    AND (column_name LIKE '%lost_revenue_other_amount%' OR column_name LIKE '%lost_revenue_other_amount_org_currency%' OR column_name LIKE '%lost_revenue_other_amount_grp_currency%')
                "))->pluck('column_name')->toArray();

                if (! empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
