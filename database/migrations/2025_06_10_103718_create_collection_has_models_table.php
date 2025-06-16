<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 19:53:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('collection_has_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('collection_id')->index();
            $table->foreign('collection_id')->references('id')->on('collections');
            $table->string('model_type')->index();
            $table->unsignedBigInteger('model_id')->index();
            $table->timestampsTz();
            $table->index(['model_type', 'model_id']);
            $table->unique(['collection_id', 'model_type', 'model_id']);
        });


    }


    public function down(): void
    {
        Schema::dropIfExists('collection_has_models');
    }
};
