<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sun, 14 Aug 2022 20:43:20 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('timezones', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name')->unique()->collation('und_ci');
            $table->integer('offset')->nullable()->comment('in hours');
            $table->unsignedSmallInteger('country_id')->nullable();
            $table->foreign('country_id')->references('id')->on('countries');
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
            $table->string('location');
            $table->jsonb('data');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timezones');
    }
};
