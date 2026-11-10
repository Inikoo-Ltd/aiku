<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::create('catalogue_search_embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('shop_id')->index();
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->string('content_hash', 40);
            $table->vector('embedding', dimensions: 1024);
            $table->timestampsTz();
            $table->unique(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_search_embeddings');
    }
};
