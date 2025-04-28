<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 09:06:01 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::table('stock_family_sales_intervals', function (Blueprint $table) {
            $table = $this->decimalDateIntervals($table, [
                "revenue_grp_currency",
                "profit_grp_currency",
            ]);
            $this->jsonDateIntervals($table, [
                "revenue_data",
            ]);

            $this->unsignedIntegerDateIntervals($table, [
                "number_invoices",
                "number_customers",
            ]);


        });

        Schema::table('stock_family_intervals', function (Blueprint $table) {

            $subjects = ['revenue_grp_currency', 'profit_grp_currency'];
            foreach ($subjects as $subject) {
                $subject = $subject ? $subject.'_' : '';

                foreach (DateIntervalEnum::values() as $col) {
                    $table->dropColumn($subject.$col);
                }
                foreach (DateIntervalEnum::lastYearValues() as $col) {
                    $table->dropColumn($subject.$col.'_ly');
                }
                foreach (PreviousYearsEnum::values() as $col) {
                    $table->dropColumn($subject.$col);
                }
                foreach (PreviousQuartersEnum::values() as $col) {
                    $table->dropColumn($subject.$col);
                }
            }
        });
    }


    public function down(): void
    {
        Schema::table('stock_family_sales_intervals', function (Blueprint $table) {
            $subjects = ['revenue_grp_currency', 'profit_grp_currency', 'revenue_data','number_invoices','number_customers'];
            foreach ($subjects as $subject) {
                $subject = $subject ? $subject.'_' : '';

                foreach (DateIntervalEnum::values() as $col) {
                    $table->dropColumn($subject.$col);
                }
                foreach (DateIntervalEnum::lastYearValues() as $col) {
                    $table->dropColumn($subject.$col.'_ly');
                }
                foreach (PreviousYearsEnum::values() as $col) {
                    $table->dropColumn($subject.$col);
                }
                foreach (PreviousQuartersEnum::values() as $col) {
                    $table->dropColumn($subject.$col);
                }
            }
        });

        Schema::table('stock_family_intervals', function (Blueprint $table) {

            $this->decimalDateIntervals($table, [
                "revenue_grp_currency",
                "profit_grp_currency",
            ]);
        });
    }
};
