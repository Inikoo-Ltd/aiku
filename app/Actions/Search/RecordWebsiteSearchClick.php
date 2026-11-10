<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Events\Web\WebsiteSearchStatsUpdated;
use App\Models\Helpers\WebsiteSearchLog;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordWebsiteSearchClick
{
    use AsAction;

    public const array SUGGESTION_KINDS = ['did_you_mean', 'related_search', 'popular_search', 'family', 'best_sellers', 'new_arrivals', 'forgotten'];

    public const array TARGETS = ['link', 'button'];

    public const int MAX_CLICKS = 20;

    /**
     * A click on a Keep exploring suggestion is kept apart from the result click: only the first
     * one is stored and the headline stats, which count result clicks, are left alone. The first
     * suggestion click columns only take a click on a link, so a card's basket button does not use
     * them up; the first result click columns take the first result click whatever its target.
     * Every click, up to MAX_CLICKS per search, also gets a row in website_search_log_clicks.
     */
    public function handle(string $ulid, string $url, ?string $suggestion = null, string $target = 'link'): void
    {
        $searchLog = WebsiteSearchLog::where('ulid', $ulid)->first();
        if (!$searchLog) {
            return;
        }

        DB::insert(
            'insert into website_search_log_clicks (website_search_log_id, website_id, type, kind, target, url, created_at)
            select ?, ?, ?, ?, ?, ?, ? where (select count(*) from website_search_log_clicks where website_search_log_id = ?) < ?',
            [$searchLog->id, $searchLog->website_id, $suggestion ? 'suggestion' : 'result', $suggestion, $target, $url, now(), $searchLog->id, self::MAX_CLICKS]
        );

        if ($suggestion) {
            if (!$searchLog->suggestion_clicked_at && $target === 'link') {
                $searchLog->update([
                    'suggestion_kind'       => $suggestion,
                    'suggestion_url'        => $url,
                    'suggestion_clicked_at' => now(),
                ]);
            }

            return;
        }

        if ($searchLog->clicked_at) {
            return;
        }

        $searchLog->update([
            'clicked_url' => $url,
            'clicked_at'  => now(),
        ]);

        $website = Website::find($searchLog->website_id);
        if ($website) {
            WebsiteSearchStatsUpdated::dispatch($website);
        }
    }

    public function rules(): array
    {
        return [
            'ulid'       => ['required', 'string', 'size:26'],
            'url'        => ['required', 'string', 'max:2048'],
            'suggestion' => ['sometimes', 'nullable', 'string', Rule::in(self::SUGGESTION_KINDS)],
            'target'     => ['sometimes', 'nullable', 'string', Rule::in(self::TARGETS)],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $click = [$request->validated('ulid'), $request->validated('url'), $request->validated('suggestion'), $request->validated('target') ?? 'link'];
        logAfterResponse(fn () => $this->handle(...$click));

        return ['ok' => true];
    }
}
