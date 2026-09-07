<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->unsignedInteger('artefact_id')->nullable()->index()->comment('set when the raw material is an intermediate made in-house');
            $table->foreign('artefact_id')->references('id')->on('artefacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('artefact_id');
        });
    }
};
