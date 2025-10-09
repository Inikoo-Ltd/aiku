<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Aug 2025 17:25:25 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('model_has_traffic_sources', function (Blueprint $table) {
            $table->increments('id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->unsignedInteger('traffic_source_id');
            $table->foreign('traffic_source_id')->references('id')->on('traffic_sources')->nullOnDelete();
            $table->decimal('share', 5)->default(1.00);
            $table->timestampsTz();
            $table->index(['model_type', 'model_id']);
            $table->unique(['model_type', 'model_id', 'traffic_source_id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('model_has_traffic_sources');
    }
};
