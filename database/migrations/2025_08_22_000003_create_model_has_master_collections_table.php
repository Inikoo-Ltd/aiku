<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('model_has_master_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('master_collection_id')->nullable();
            $table->foreign('master_collection_id')->references('id')->on('master_collections');
            $table->string('model_type')->nullable();
            $table->unsignedInteger('model_id')->nullable();
            $table->string('type')->nullable();
            $table->index(['model_type','model_id']);
            $table->unique(['master_collection_id','model_type','model_id']);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('model_has_master_collections');
    }
};