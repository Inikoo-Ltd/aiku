<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Nov 2024 10:20:09 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_unit_has_ingredients', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('trade_unit_id');
            $table->foreign('trade_unit_id')->references('id')->on('trade_units')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('ingredient_id');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onUpdate('cascade')->onDelete('cascade');
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->string('notes')->nullable();
            $table->string('concentration')->nullable()->comment('percentage');
            $table->string('purity')->nullable()->comment('gold and silver');
            $table->string('percentage')->nullable()->comment('used for textile');
            $table->string('aroma')->nullable()->comment('used for textile');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_unit_has_ingredients');
    }
};
