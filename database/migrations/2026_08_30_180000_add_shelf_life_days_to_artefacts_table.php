<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefacts', function (Blueprint $table) {
            $table->unsignedSmallInteger('shelf_life_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('artefacts', function (Blueprint $table) {
            $table->dropColumn('shelf_life_days');
        });
    }
};
