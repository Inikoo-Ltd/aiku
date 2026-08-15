<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Support\Facades\DB;

trait WithDispatchedEmailArchiveRead
{
    /**
     * Emails older than the retention window live in the archive database. Sends happen in bursts,
     * so a parent's emails are archived all-or-nothing in practice: when the operational database has
     * no rows for the parent but the archive does, the whole listing is served from the archive.
     *
     * @param array<string, mixed> $where
     */
    protected function dispatchedEmailReadConnection(string $pivotTable, array $where): ?string
    {
        if (DB::table($pivotTable)->where($where)->exists()) {
            return null;
        }

        if (!config('database.connections.archive.database')) {
            return null;
        }

        return DB::connection('archive')->table($pivotTable)->where($where)->exists() ? 'archive' : null;
    }
}
