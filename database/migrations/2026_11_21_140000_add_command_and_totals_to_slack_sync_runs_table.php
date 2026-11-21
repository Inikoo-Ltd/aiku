<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_sync_runs', function (Blueprint $table) {
            $table->string('command')->default('slack:sync-public-channels')->index();
            $table->jsonb('totals')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('slack_sync_runs', function (Blueprint $table) {
            $table->dropColumn(['command', 'totals']);
        });
    }
};
