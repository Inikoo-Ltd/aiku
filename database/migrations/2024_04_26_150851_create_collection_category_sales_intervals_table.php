<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Apr 2024 16:17:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasSalesIntervals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasSalesIntervals;

    public function up(): void
    {
        Schema::create('collection_category_sales_intervals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('collection_category_id')->index();
            $table->foreign('collection_category_id')->references('id')->on('collection_categories');
            $table = $this->salesIntervalFields($table, ['shop_amount', 'org_amount', 'grp_amount']);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_category_sales_intervals');
    }
};
