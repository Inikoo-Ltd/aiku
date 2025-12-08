<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jun 2024 20:37:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_web_blocks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('organisation_id')->index()->nullable();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites');
            $table->unsignedInteger('webpage_id')->nullable()->index();
            $table->foreign('webpage_id')->references('id')->on('webpages');
            $table->unsignedSmallInteger('position')->index()->nullable();

            $table->unsignedInteger('web_block_id')->index();
            $table->foreign('web_block_id')->references('id')->on('web_blocks');

            $table->string('model_type');
            $table->unsignedInteger('model_id');

            $table->boolean('show')->index()->default(true);
            $table->boolean('show_logged_in')->index()->default(true);
            $table->boolean('show_logged_out')->index()->default(true);
            $table->timestampsTz();
            $table->string('migration_checksum')->index()->nullable();

            $table->index(['model_type', 'model_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_web_blocks');
    }
};
