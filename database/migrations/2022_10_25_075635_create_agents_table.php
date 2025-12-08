<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Apr 2023 11:28:24 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table->string('code')->index()->collation('und_ns')->comment('mirror of organisation');
            $table->string('name')->index()->comment('mirror of organisation');
            $table->boolean('status')->default(true)->index();
            $table->string('slug')->unique()->collation('und_ns');
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media')->onDelete('cascade');
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('source_slug')->index()->nullable();
            $table->string('source_id')->index()->nullable();
            $table->json('sources');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
