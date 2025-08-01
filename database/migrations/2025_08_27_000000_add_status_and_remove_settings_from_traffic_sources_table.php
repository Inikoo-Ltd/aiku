<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 14:51:55 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('traffic_sources', function (Blueprint $table) {
            $table->boolean('status')->default(true);
            $table->dropColumn('settings');
        });
    }


    public function down(): void
    {
        Schema::table('traffic_sources', function (Blueprint $table) {
            $table->json('settings')->default('{}')->after('name');
            $table->dropColumn('status');
        });
    }
};
