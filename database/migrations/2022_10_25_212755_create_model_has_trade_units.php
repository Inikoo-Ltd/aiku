<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 May 2023 15:26:45 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_trade_units', function (Blueprint $table) {
            $table->increments('id');
            $table->string('model_type');
            $table->unsignedInteger('model_id')->nullable();
            $table->unsignedInteger('trade_unit_id')->nullable()->index();
            $table->foreign('trade_unit_id')->references('id')->on('trade_units');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('notes')->nullable();
            $table->timestampsTz();
            $table->index(['model_type', 'model_id']);
            $table->unique(['model_type', 'model_id', 'trade_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_trade_units');
    }
};
