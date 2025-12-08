<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Jul 2023 14:24:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('web_block_type_id');
            $table->foreign('web_block_type_id')->references('id')->on('web_block_types')->onUpdate('cascade')->onDelete('cascade');
            $table->string('checksum')->index()->nullable();
            $table->jsonb('layout');
            $table->jsonb('data');
            $table->timestampsTz();
            $table->string('migration_checksum')->index()->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_blocks');
    }
};
