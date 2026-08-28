<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('traffic_source_campaigns', function (Blueprint $table) {
            $table->string('channel_type')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('traffic_source_campaigns', function (Blueprint $table) {
            $table->dropColumn('channel_type');
        });
    }
};
