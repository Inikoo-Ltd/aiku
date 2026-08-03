<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 02:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Helpers\SearchLog;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreSearchLog
{
    use AsAction;

    public function handle(array $modelData): SearchLog
    {
        $refined = $this->refinedLog($modelData);
        if ($refined) {
            $refined->update(Arr::only($modelData, ['ulid', 'query', 'scope', 'results_count']));

            return $refined;
        }

        return SearchLog::create($modelData);
    }

    protected function refinedLog(array $modelData): ?SearchLog
    {
        $sessionId = Arr::get($modelData, 'session_id');
        if (!$sessionId) {
            return null;
        }

        $previous = SearchLog::where('session_id', $sessionId)->latest('id')->first();
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
