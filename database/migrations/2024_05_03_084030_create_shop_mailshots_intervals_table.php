<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 17:36:57 British Summer Time, Sheffield, UK
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
        Schema::create('shop_mailshots_intervals', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade')->onDelete('cascade');

            $table = $this->unsignedIntegerDateIntervals($table, [
                'newsletters',
                'marketing_emails',
                'abandoned_carts',
                'total_mailshots',
                'total_emails',
            ]);

            $table->timestampsTz();
            $table->unique(['shop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_mailshots_intervals');
    }
};
