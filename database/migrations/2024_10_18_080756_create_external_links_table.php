<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Oct 2024 23:28:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->text('url');
            $table->integer('number_websites_shown')->default(0);
            $table->integer('number_webpages_shown')->default(0);
            $table->integer('number_web_blocks_shown')->default(0);
            $table->integer('number_websites_hidden')->default(0);
            $table->integer('number_webpages_hidden')->default(0);
            $table->integer('number_web_blocks_hidden')->default(0);
            $table->string('status')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_links');
    }
};
