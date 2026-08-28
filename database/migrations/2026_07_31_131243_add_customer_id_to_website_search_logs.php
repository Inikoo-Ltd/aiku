<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_search_logs', function (Blueprint $table) {
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->string('device')->nullable()->index();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('website_search_logs', function (Blueprint $table) {
            $table->dropColumn(['customer_id', 'device', 'browser', 'os']);
        });
    }
};
