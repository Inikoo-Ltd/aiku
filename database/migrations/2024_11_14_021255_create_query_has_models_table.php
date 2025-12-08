<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Nov 2024 13:37:31 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_has_models', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->unsignedSmallInteger('query_id')->index();
            $table->foreign('query_id')->references('id')->on('queries');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_has_models');
    }
};
