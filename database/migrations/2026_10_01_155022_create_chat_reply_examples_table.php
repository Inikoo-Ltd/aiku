<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 23:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::create('chat_reply_examples', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->string('source');
            $table->unsignedInteger('source_id');
            $table->text('customer_wrote');
            $table->text('reply');
            $table->timestampTz('replied_at')->index();
            $table->vector('embedding', dimensions: 1024)->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reply_examples');
    }
};
