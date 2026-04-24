<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Apr 2026 21:38:28 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_tracking_events', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_tracking_events', function (Blueprint $table) {
            $table->timestampTz('updated_at')->nullable();
        });
    }
};
