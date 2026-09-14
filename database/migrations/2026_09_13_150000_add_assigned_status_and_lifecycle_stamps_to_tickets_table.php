<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Tickets before this migration only recorded when they were resolved and closed, so the
     * assignment and start of the work are backfilled from updated_at, the closest stamp there is.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestampTz('assigned_at')->nullable()->after('assignee_id');
            $table->timestampTz('started_at')->nullable()->after('assigned_at');
        });

        DB::table('tickets')->whereNotNull('assignee_id')->update(['assigned_at' => DB::raw('updated_at')]);
        DB::table('tickets')->whereIn('status', ['in_progress', 'waiting', 'resolved', 'cancelled'])->update(['started_at' => DB::raw('updated_at')]);
        DB::table('tickets')->where('status', 'open')->whereNotNull('assignee_id')->update(['status' => 'assigned']);
        DB::table('tickets')->where('status', 'resolved')->whereNull('closed_at')->whereNotNull('resolved_at')->update(['closed_at' => DB::raw('resolved_at')]);
    }

    public function down(): void
    {
        DB::table('tickets')->where('status', 'assigned')->update(['status' => 'open']);

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['assigned_at', 'started_at']);
        });
    }
};
