<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Jun 2025 22:01:46 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('collection_has_models', function (Blueprint $table) {
            $table->string('type')->index()->default('direct');
        });
    }


    public function down(): void
    {
        Schema::table('collection_has_models', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
