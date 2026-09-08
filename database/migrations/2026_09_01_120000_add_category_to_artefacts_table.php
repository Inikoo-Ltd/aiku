<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefacts', function (Blueprint $table) {
            $table->string('category')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('artefacts', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
