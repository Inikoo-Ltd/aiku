<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 18:25:25 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasSalesStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasSalesStats;
    public function up(): void
    {
        Schema::create('shop_sales_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->salesStatsFields($table);
            $table = $this->purgeStatsFields($table);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('shop_sales_stats');
    }
};
