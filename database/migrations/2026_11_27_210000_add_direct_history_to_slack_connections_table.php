<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_connections', function (Blueprint $table) {
            $table->dateTimeTz('direct_history_started_at')->nullable()->comment('When the walk over the person\'s earlier DMs and group DMs started (INI-433); null = not started');
            $table->unsignedInteger('direct_history_total')->nullable()->comment('DMs and group DMs Slack listed for the person');
            $table->dateTimeTz('direct_history_finished_at')->nullable()->comment('When every listed DM and group DM was read');
        });
    }

    public function down(): void
    {
        Schema::table('slack_connections', function (Blueprint $table) {
            $table->dropColumn(['direct_history_started_at', 'direct_history_total', 'direct_history_finished_at']);
        });
    }
};
