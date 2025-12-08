<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:23:41 British Summer Time, Sheffield, UK
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
        Schema::create('collection_sales_intervals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('collection_id')->index();
            $table->foreign('collection_id')->references('id')->on('collections');
            $table = $this->decimalDateIntervals($table, [
                'sales',
                'sales_org_currency',
                'sales_grp_currency',
            ]);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_sales_intervals');
    }
};
