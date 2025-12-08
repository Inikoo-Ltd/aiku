<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:24:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
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
        Schema::create('master_product_category_sales_intervals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('master_product_category_id')->index();
            $table->foreign('master_product_category_id')->references('id')->on('master_product_categories')->onDelete('cascade');
            $table = $this->decimalDateIntervals($table, [
                'sales_grp_currency',
            ]);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_category_sales_intervals');
    }
};
