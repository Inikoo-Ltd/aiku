<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 17:04:29 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_codes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('section');
            $table->string('hs_code')->unique();
            $table->text('description');
            $table->unsignedInteger('parent_id')->index()->nullable();
            $table->unsignedSmallInteger('level')->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_codes');
    }
};
