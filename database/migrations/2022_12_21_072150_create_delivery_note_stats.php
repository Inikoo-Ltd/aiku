<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 21 Dec 2022 15:29:00 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('delivery_note_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('delivery_note_id')->index();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes')->cascadeOnDelete();

            $table->unsignedSmallInteger('number_items')->default(0)->comment('current number of items');

            $table->timestampsTz();
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_note_stats');
    }
};
