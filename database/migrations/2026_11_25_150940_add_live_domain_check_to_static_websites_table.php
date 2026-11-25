<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('static_websites', function (Blueprint $table) {
            $table->string('live_domain_status')->nullable();
            $table->string('nameservers')->nullable();
            $table->timestampTz('live_domain_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('static_websites', function (Blueprint $table) {
            $table->dropColumn(['live_domain_status', 'nameservers', 'live_domain_checked_at']);
        });
    }
};
