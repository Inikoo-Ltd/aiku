<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Jun 2025 17:00:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_collections_with_webpage')->default(0);
            $table->unsignedInteger('number_collections_with_online_webpage')->default(0);
            $table->unsignedInteger('number_collections_with_offline_webpage')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->dropColumn('number_collections_with_webpage');
            $table->dropColumn('number_collections_with_online_webpage');
            $table->dropColumn('number_collections_with_offline_webpage');
        });
    }
};
