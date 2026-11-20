<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 20 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Helpers\ComparePeriod;
use App\Models\Web\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The periods the SEO dashboard shows and the ones it compares them with. Traffic covers the
 * interval up to today; Search Console data stops a few days before today, so its period ends on
 * its own last day and is compared with a period of that length.
 */
class GetSeoPeriods
{
    use AsObject;

    /**
     * @return array{traffic: array{from: string, to: string, previous_from: string|null, previous_to: string|null}|null, search: array{from: string, to: string, previous_from: string|null, previous_to: string|null}|null, label: string|null}
     */
    public function handle(Website $website, ?string $fromDate, ?string $toDate, ?ComparePeriod $compare = null): array
    {
        if (!$fromDate) {
            return ['traffic' => null, 'search' => null, 'label' => null];
        }

        $compare ??= ComparePeriod::previousPeriod();

        $from = Carbon::parse($fromDate)->startOfDay();
        $to   = Carbon::parse($toDate ?? today())->startOfDay();

        $lastSearchDay = DB::table('search_console_page_days')->where('website_id', $website->id)->max('date');
        $searchTo      = $lastSearchDay ? Carbon::parse($lastSearchDay)->min($to) : null;

        $period = function (Carbon $from, Carbon $to) use ($compare) {
            if ($to->lt($from)) {
                return null;
            }

            $previous = $compare->range($from, $to);

            return [
                'from'          => $from->toDateString(),
                'to'            => $to->toDateString(),
                'previous_from' => $previous ? $previous[0]->toDateString() : null,
                'previous_to'   => $previous ? $previous[1]->toDateString() : null,
            ];
        };

        $traffic = $period($from, $to);

        return [
            'traffic' => $traffic,
            'search'  => $searchTo ? $period($from, $searchTo) : null,
            'label'   => $traffic && $traffic['previous_from'] ? $compare->label() : null,
        ];
    }
}
