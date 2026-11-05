<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_edge_time_series_records', function (Blueprint $table) {
            $table->unsignedInteger('requests_bypass_redirect')->default(0)->comment('Of requests_bypass, the 3xx answers Cloudflare did not keep');
        });
    }

    public function down(): void
    {
        Schema::table('website_edge_time_series_records', function (Blueprint $table) {
            $table->dropColumn('requests_bypass_redirect');
        });
    }
};
