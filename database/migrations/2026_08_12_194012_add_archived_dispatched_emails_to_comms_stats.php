<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = ['mailshot_stats', 'outbox_stats', 'email_bulk_run_stats'];

    /**
     * The counts of the dispatched emails archived out of the operational database, keyed by the stats
     * column they belong to, so the hydrators can keep reporting the historical totals after the rows
     * they were counted from are gone. Null until an archive run writes to it.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->jsonb('archived_dispatched_emails')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('archived_dispatched_emails');
            });
        }
    }
};
