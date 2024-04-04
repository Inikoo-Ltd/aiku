<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:22:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasSalesStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use  HasSalesStats;

    public function up(): void
    {
        Schema::create('organisation_sales_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');


            $table=$this->salesStats($table,['org_amount','group_amount']);

            $table->timestampsTz();
            $table->unique(['organisation_id', 'currency_id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('organisation_sales_stats');
    }
};
