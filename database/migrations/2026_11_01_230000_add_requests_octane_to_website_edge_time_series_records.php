<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_edge_time_series_records', function (Blueprint $table) {
            $table->unsignedInteger('requests_octane')->nullable()->comment('Storefront HTML pages Octane rendered for Cloudflare requests; null before it was counted');
        });
    }

    public function down(): void
    {
        Schema::table('website_edge_time_series_records', function (Blueprint $table) {
            $table->dropColumn('requests_octane');
        });
    }
};
