<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('artefact_labels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('artefact_id')->index();
            $table->foreign('artefact_id')->references('id')->on('artefacts')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('artwork_id')->nullable()->index();
            $table->foreign('artwork_id')->references('id')->on('media')->nullOnDelete();
            $table->jsonb('layout');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artefact_labels');
    }
};
