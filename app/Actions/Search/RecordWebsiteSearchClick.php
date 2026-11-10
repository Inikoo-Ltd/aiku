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
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordWebsiteSearchClick
{
    use AsAction;

    public const array SUGGESTION_KINDS = ['did_you_mean', 'related_search', 'popular_search', 'family', 'best_sellers', 'new_arrivals', 'forgotten'];

    /**
     * A click on a Keep exploring suggestion is kept apart from the result click: only the first
     * one is stored and the headline stats, which count result clicks, are left alone.
     */
    public function handle(string $ulid, string $url, ?string $suggestion = null): void
    {
        $searchLog = WebsiteSearchLog::where('ulid', $ulid)->first();
        if (!$searchLog) {
            return;
        }

        if ($suggestion) {
            if (!$searchLog->suggestion_clicked_at) {
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
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->handle($request->validated('ulid'), $request->validated('url'), $request->validated('suggestion'));

        return ['ok' => true];
    }
}
