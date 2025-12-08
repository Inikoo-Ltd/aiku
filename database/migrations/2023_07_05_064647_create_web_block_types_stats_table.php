<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:23:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_block_type_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('web_block_type_id');
            $table->foreign('web_block_type_id')->references('id')->on('web_block_types')->onUpdate('cascade')->onDelete('cascade');
            $table->integer('number_organisations')->default(0);
            $table->integer('number_websites')->default(0);
            $table->integer('number_webpages')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_block_type_stats');
    }
};
