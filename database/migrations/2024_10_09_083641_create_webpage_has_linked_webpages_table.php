<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Oct 2024 19:37:23 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webpage_has_linked_webpages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webpage_id')->index();
            $table->foreign('webpage_id')->references('id')->on('webpages')->cascadeOnDelete();
            $table->unsignedInteger('child_id')->index()->comment('Webpage linked in webpage content');
            $table->foreign('child_id')->references('id')->on('webpages')->cascadeOnDelete();
            $table->string('model_type')->nullable()->index()->comment('Child webpage model type');
            $table->unsignedInteger('model_id')->nullable();
            $table->string('model_type_scope')->nullable()->index()->comment('product|family|department|sub_department; Used to distinguish between different types ProductCategory');
            $table->unsignedBigInteger('web_block_id')->nullable()->index();
            $table->foreign('web_block_id')->references('id')->on('web_blocks')->cascadeOnDelete();
            $table->string('web_block_type_code')->nullable()->index()->comment('Web block type code');
            $table->index(['model_type', 'model_id']);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webpage_has_linked_webpages');
    }
};
