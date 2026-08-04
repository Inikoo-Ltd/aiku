<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_search_logs', function (Blueprint $table) {
            $table->string('source')->nullable()->index();
        });
    }


    public function down(): void
    {
        Schema::table('website_search_logs', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
