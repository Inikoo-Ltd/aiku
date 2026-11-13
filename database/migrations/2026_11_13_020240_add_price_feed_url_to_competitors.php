<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 10:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->text('price_feed_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->dropColumn('price_feed_url');
        });
    }
};
