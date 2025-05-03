<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 May 2025 12:30:08 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::create('org_stock_family_sales_intervals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('org_stock_family_id')->index();
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families');


            $table = $this->decimalDateIntervals($table, [
                "revenue_org_currency",
                "profit_org_currency",
            ]);

            $this->unsignedIntegerDateIntervals($table, [
                "number_invoices",
                "number_customers",
            ]);

            $salesTypes = [
                'b2b','dropshipping','marketplace','partner','employee','vip'
            ];

            foreach ($salesTypes as $salesType) {
                $table = $this->decimalDateIntervals($table, [
                    "revenue_{$salesType}_org_currency",
                    "profit_{$salesType}_org_currency",
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
        Schema::dropIfExists('org_stock_family_sales_intervals');
    }
};
