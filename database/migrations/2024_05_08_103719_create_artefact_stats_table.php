<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 13:54:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artefact_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('artefact_id')->index();
            $table->foreign('artefact_id')->references('id')->on('artefacts')->onUpdate('cascade')->onDelete('cascade');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artefact_stats');
    }
};
