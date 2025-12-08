<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Apr 2024 16:17:51 British Summer Time, Sheffield, UK
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
        Schema::create('asset_sales_intervals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('asset_id')->index();
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('asset_sales_intervals');
    }
};
