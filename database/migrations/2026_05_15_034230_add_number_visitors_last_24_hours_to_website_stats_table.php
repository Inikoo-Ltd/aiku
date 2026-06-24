<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 May 2026 11:43:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_visitors_last_24_hours')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('website_stats', function (Blueprint $table) {
            $table->dropColumn('number_visitors_last_24_hours');
        });
    }
};
