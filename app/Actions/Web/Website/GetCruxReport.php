<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 23:40:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Models\Web\CruxRecord;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCruxReport
{
    use AsAction;

    private const int WEEKS = 52;

    /**
     * A page Google has no real user data for is shown the whole website's history instead, and
     * the report says so.
     *
     * @return array{scope: string|null, url: string|null, history: array<string, array<int, array<string, mixed>>>}
     */
    public function handle(Website $website, ?Webpage $webpage = null): array
    {
        if ($webpage) {
            FetchCruxHistory::run($website, $webpage);

            $history = $this->history($website, $webpage);

            if ($history) {
                return [
                    'scope'   => 'page',
                    'url'     => FetchCruxHistory::publicUrl($webpage),
                    'history' => $history,
                ];
            }
        }

        FetchCruxHistory::run($website);

        $history = $this->history($website, null);

        return [
            'scope'   => $history ? 'website' : null,
            'url'     => FetchCruxHistory::origin($website),
            'history' => $history,
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function history(Website $website, ?Webpage $webpage): array
    {
        return CruxRecord::where('website_id', $website->id)
            ->where('webpage_id', $webpage?->id)
            ->where('period_end', '>=', now()->subWeeks(self::WEEKS)->toDateString())
            ->orderBy('period_end')
            ->get()
            ->groupBy('form_factor')
            ->map(fn ($records) => $records->map(fn (CruxRecord $record) => [
                'period_start' => $record->period_start->toDateString(),
                'period_end'   => $record->period_end->toDateString(),
                'lcp'          => $record->lcp_p75,
                'inp'          => $record->inp_p75,
                'cls'          => $record->cls_p75,
                'fcp'          => $record->fcp_p75,
                'ttfb'         => $record->ttfb_p75,
                'histograms'   => $record->histograms,
            ])->values()->all())
            ->all();
    }
}
