<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait WithArchivedDispatchedEmails
{
    /**
     * Every dispatched email counter is recounted from scratch, so once rows older than the retention
     * window are archived out of the database the live count alone would quietly rewrite history with
     * a post archive number. The stats row keeps what was archived, keyed by the stats column it feeds,
     * and every recount is added on top of that baseline.
     *
     * @param array<string, int> $counts
     * @return array<string, int>
     */
    protected function addArchivedDispatchedEmails(Model $stats, array $counts): array
    {
        $archived = $stats->archived_dispatched_emails;

        if (!$archived) {
            return $counts;
        }

        foreach ($counts as $key => $count) {
            $counts[$key] = $count + (int) Arr::get($archived, $key, 0);
        }

        return $counts;
    }
}
