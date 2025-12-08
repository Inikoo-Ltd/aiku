<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jul 2023 15:01:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasFulfilmentStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasFulfilmentStats;

    public function up(): void
    {
        Schema::create('fulfilment_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('fulfilment_id');
            $table->foreign('fulfilment_id')->references('id')->on('fulfilments')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->fulfilmentCustomersStats($table);
            $table = $this->fulfilmentStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfilment_stats');
    }
};
