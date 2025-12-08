<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Oct 2024 14:07:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_block_has_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('web_block_id')->index();
            $table->foreign('web_block_id')->references('id')->on('web_blocks');
            $table->string('model_type')->index();
            $table->unsignedInteger('model_id');
            $table->timestampsTz();
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_block_has_models');
    }
};
