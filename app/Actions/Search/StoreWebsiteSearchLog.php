<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Helpers\WebsiteSearchLog;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreWebsiteSearchLog
{
    use AsAction;

    public function handle(array $modelData): WebsiteSearchLog
    {
        $refined = $this->refinedLog($modelData);
        if ($refined) {
            $refined->update(Arr::only($modelData, ['ulid', 'query', 'scope', 'results_count']));

            return $refined;
        }

        return WebsiteSearchLog::create($modelData);
    }

    /**
     * Search-as-you-type fires one request per keystroke; within a short window the latest
     * refinement of the same query replaces the previous log row instead of stacking rows.
     */
    protected function refinedLog(array $modelData): ?WebsiteSearchLog
    {
        $sessionId = Arr::get($modelData, 'session_id');
        if (!$sessionId) {
            return null;
        }

        $previous = WebsiteSearchLog::where('session_id', $sessionId)->latest('id')->first();
        if (!$previous || $previous->clicked_at || $previous->created_at->lt(now()->subSeconds(15))) {
            return null;
        }

        $previousQuery = mb_strtolower($previous->query);
        $newQuery      = mb_strtolower(Arr::get($modelData, 'query', ''));

        if (str_starts_with($newQuery, $previousQuery) || str_starts_with($previousQuery, $newQuery)) {
            return $previous;
        }

        return null;
    }
}
