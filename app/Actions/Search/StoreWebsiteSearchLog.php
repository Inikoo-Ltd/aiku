<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\CRM\Customer\IsStaffCustomer;
use App\Events\Web\WebsiteSearchStatsUpdated;
use App\Models\Helpers\WebsiteSearchLog;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreWebsiteSearchLog
{
    use AsAction;

    public function handle(array $modelData): ?WebsiteSearchLog
    {
        $query = trim((string) Arr::get($modelData, 'query'));

        if ($query === '' || preg_match('/^[\d\s.,+\-]+$/u', $query) === 1) {
            return null;
        }

        if (IsStaffCustomer::run(Arr::get($modelData, 'customer_id'))) {
            return null;
        }

        $refined = $this->refinedLog($modelData);
        if ($refined) {
            $refined->update(array_merge(
                Arr::only($modelData, [
                    'ulid', 'query', 'scope',
                    'results_count', 'keyword_results_count', 'vector_results_count',
                ]),
                Arr::has($modelData, 'assistant_available') ? ['assistant_available' => $refined->assistant_available || Arr::get($modelData, 'assistant_available')] : []
            ));

            return $refined;
        }

        $log = WebsiteSearchLog::create($modelData);

        // only a genuinely new search moves the headline numbers; refinements above reuse
        // their row, so broadcasting there would fire once per keystroke for nothing
        $this->broadcastStats($log);

        return $log;
    }

    protected function broadcastStats(WebsiteSearchLog $log): void
    {
        $website = Website::find($log->website_id);
        if ($website) {
            WebsiteSearchStatsUpdated::dispatch($website);
        }
    }

    /**
     * Search-as-you-type fires one request per keystroke; within a short window the latest
     * refinement of the same query replaces the previous log row instead of stacking rows.
     * An identical query re-fired later in the session (pagination, facets, sorting on the
     * search page) reuses its row too, or it would count as a fresh search every time.
     * The reused row keeps its original source: the control that started the search is
     * what the entry point breakdown is after, not the one that refined it. Its assistant flag
     * stays true once any request of the search had the Ask button offered.
     * A click on a Keep exploring suggestion ends the search like a result click does.
     */
    protected function refinedLog(array $modelData): ?WebsiteSearchLog
    {
        $sessionId = Arr::get($modelData, 'session_id');
        if (!$sessionId) {
            return null;
        }

        $previous = WebsiteSearchLog::where('session_id', $sessionId)->latest('id')->first();
        if (!$previous) {
            return null;
        }

        $previousQuery = mb_strtolower($previous->query);
        $newQuery      = mb_strtolower(Arr::get($modelData, 'query', ''));

        if ($previousQuery === $newQuery && $previous->created_at->gt(now()->subMinutes(30))) {
            return $previous;
        }

        if ($previous->clicked_at || $previous->suggestion_clicked_at || $previous->created_at->lt(now()->subSeconds(15))) {
            return null;
        }

        if (str_starts_with($newQuery, $previousQuery) || str_starts_with($previousQuery, $newQuery)) {
            return $previous;
        }

        return null;
    }
}
