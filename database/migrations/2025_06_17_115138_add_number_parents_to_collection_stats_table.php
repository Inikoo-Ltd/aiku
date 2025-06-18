<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Jun 2025 19:51:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('collection_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_parents')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('collection_stats', function (Blueprint $table) {
            $table->dropColumn('number_parents');
        });
    }
};
