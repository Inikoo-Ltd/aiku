<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWebpagePageSpeedReport
{
    use AsAction;

    /**
     * @return array{status: string, message?: string, refresh_route?: array, mobile?: array, desktop?: array}
     */
    public function handle(Webpage $webpage): array
    {
        if (!GetWebpagePageSpeed::publiclyReachableUrl($webpage)) {
            return [
                'status'  => 'unavailable',
                'message' => __('This webpage has no publicly reachable URL to analyse'),
            ];
        }

        $report = [
            'status'        => 'ready',
            'refresh_route' => [
                'name'       => 'grp.models.webpage.pagespeed.refresh',
                'parameters' => ['webpage' => $webpage->id],
                'method'     => 'post',
            ],
        ];

        foreach (GetWebpagePageSpeed::STRATEGIES as $strategy) {
            $report[$strategy] = $this->strategyReport($webpage, $strategy);
        }

        return $report;
    }

    /**
     * A failed run is remembered for a few minutes, so a page Google cannot measure is not asked
     * about again on every load. Anything else is measured in the request: the report is served to
     * a deferred prop, and a cached result comes back without touching Google at all.
     */
    private function strategyReport(Webpage $webpage, string $strategy): array
    {
        $error = cache()->get(GetWebpagePageSpeed::errorKey($webpage, $strategy));

        if ($error) {
            return [
                'strategy' => $strategy,
                'error'    => $error,
            ];
        }

        return ['strategy' => $strategy] + GetWebpagePageSpeed::run($webpage, $strategy);
    }
}
