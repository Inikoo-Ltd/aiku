<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 May 2025 12:34:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\DateIntervals\PreviousQuartersEnum;
use App\Enums\DateIntervals\PreviousYearsEnum;
use App\Stubs\Migrations\HasDateIntervalsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasDateIntervalsStats;

    public function up(): void
    {
        Schema::table('org_stock_intervals', function (Blueprint $table) {
            $columnsToDrop = [];
            $toDrop        = [
                'revenue_1d',
                'revenue_1d_ly',
                'revenue_org_currency_1d',
                'revenue_org_currency_1d_ly',
                'revenue_grp_currency_1d',
                'revenue_grp_currency_1d_ly',
                'profit_1d',
                'profit_1d_ly',
                'profit_org_currency_1d',
                'profit_org_currency_1d_ly',
                'profit_grp_currency_1d',
                'profit_grp_currency_1d_ly'
            ];

            foreach ($toDrop as $col) {
                if (Schema::hasColumn('org_stock_intervals', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (!empty($columnsToDrop)) {
                Schema::table('org_stock_intervals', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }


            $subjects = ['revenue', 'revenue_org_currency', 'revenue_grp_currency', 'profit', 'profit_org_currency', 'profit_grp_currency'];
            foreach ($subjects as $subject) {
                $subject = $subject ? $subject.'_' : '';

                foreach (DateIntervalEnum::values() as $col) {
                    if (Schema::hasColumn('org_stock_intervals', $subject.$col)) {
                        $table->dropColumn($subject.$col);
                    }
                }
                foreach (DateIntervalEnum::lastYearValues() as $col) {
                    if (Schema::hasColumn('org_stock_intervals', $subject.$col.'_ly')) {
                        $table->dropColumn($subject.$col.'_ly');
                    }
                }
                foreach (PreviousYearsEnum::values() as $col) {
                    if (Schema::hasColumn('org_stock_intervals', $subject.$col)) {
                        $table->dropColumn($subject.$col);
                    }
                }
                foreach (PreviousQuartersEnum::values() as $col) {
                    if (Schema::hasColumn('org_stock_intervals', $subject.$col)) {
                        $table->dropColumn($subject.$col);
                    }
                }
            }
        });
    }


    public function down(): void
    {
        Schema::table('stock_intervals', function (Blueprint $table) {
            $this->decimalDateIntervals($table, [
                'revenue',
                "revenue_org_currency",
                "revenue_grp_currency",
                'profit',
                "profit_org_currency",
                "profit_grp_currency",
            ]);
        });
    }
};
