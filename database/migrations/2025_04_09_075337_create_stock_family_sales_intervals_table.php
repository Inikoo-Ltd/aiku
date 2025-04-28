<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Apr 2025 12:57:25 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::create('stock_family_sales_intervals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('stock_family_id')->index();
            $table->foreign('stock_family_id')->references('id')->on('stock_families');


            $salesTypes = [
                'b2b','dropshipping','marketplace','partner','employee','vip'
            ];

            foreach ($salesTypes as $salesType) {
                $table = $this->decimalDateIntervals($table, [
                    "revenue_{$salesType}_grp_currency",
                    "profit_{$salesType}_grp_currency",
                ]);
                $table = $this->jsonDateIntervals($table, [
                    "revenue_{$salesType}_data",
                ]);
                $table = $this->unsignedIntegerDateIntervals($table, [
                    $salesType."_number_invoices",
                    $salesType."_number_customers",
                ]);
            }



            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('stock_family_sales_intervals');
    }
};
