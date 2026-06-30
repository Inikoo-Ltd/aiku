<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 01:33:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('restricted_country_region_logs', function (Blueprint $table) {
            $table->timestampTz('last_request_at')->useCurrent()->after('was_blocked');
            $table->unsignedInteger('number_requests')->default(1)->after('last_request_at');
        });
    }

    public function down(): void
    {
        Schema::table('restricted_country_region_logs', function (Blueprint $table) {
            $table->dropColumn(['last_request_at', 'number_requests']);
        });
    }
};
