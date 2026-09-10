<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefact_families', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_artefacts_without_recipe')->default(0);
            $table->unsignedSmallInteger('number_artefacts_without_batch_size')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('artefact_families', function (Blueprint $table) {
            $table->dropColumn(['number_artefacts_without_recipe', 'number_artefacts_without_batch_size']);
        });
    }
};
