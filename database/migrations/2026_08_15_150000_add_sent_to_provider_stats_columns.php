<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Aug 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Production already carries these columns but no migration creates them, so every environment
     * built from migrations is missing them. That stayed invisible while DispatchedEmailStateEnum
     * had its SENT_TO_PROVIDER case commented out; restoring the case (historic rows use it, and
     * without it they cannot be cast) makes the hydrators write the column everywhere.
     */
    protected array $tables = [
        'outbox_stats',
        'mailshot_stats',
        'email_bulk_run_stats',
        'email_ongoing_run_stats',
        'post_room_stats',
        'org_post_room_stats',
        'shop_comms_stats',
        'organisation_comms_stats',
        'group_comms_stats',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'number_dispatched_emails_state_sent_to_provider')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unsignedBigInteger('number_dispatched_emails_state_sent_to_provider')->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'number_dispatched_emails_state_sent_to_provider')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('number_dispatched_emails_state_sent_to_provider');
                });
            }
        }
    }
};
