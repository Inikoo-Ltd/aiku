<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

trait WithDispatchedEmailArchiveRead
{
    /**
     * Emails older than the retention window live in the archive database. Only parents whose sends
     * are a single burst use this: when the operational database has no rows for the parent but the
     * archive does, the whole listing is served from the archive. Parents that accumulate emails over
     * years stay on the operational database, because switching wholesale would hide the recent half.
     *
     * The archive lives on another server, so it is never allowed to break a page: any failure
     * reaching it degrades to the operational database.
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

        try {
            return DB::connection('archive')->table($pivotTable)->where($where)->exists() ? 'archive' : null;
        } catch (Throwable $exception) {
            Log::warning('Archive database unreachable, serving live data only: '.$exception->getMessage());

            return null;
        }
    }
}
