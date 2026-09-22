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
     * @return array{status: string, message?: string, pending?: bool, refresh_route?: array, mobile?: array, desktop?: array}
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

        $report['pending'] = collect(GetWebpagePageSpeed::STRATEGIES)
            ->contains(fn (string $strategy) => (bool)($report[$strategy]['pending'] ?? false));

        return $report;
    }

    /**
     * Nothing here talks to Google: a run takes up to a minute per strategy and would hang the
     * deferred request until it timed out. A cached result is served, anything else is queued and
     * reported as pending so the page can poll for it.
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

        $cached = cache()->get(GetWebpagePageSpeed::resultKey($webpage, $strategy));

        if ($cached) {
            return ['strategy' => $strategy] + $cached;
        }

        QueueWebpagePageSpeed::run($webpage, $strategy);

        return [
            'strategy' => $strategy,
            'pending'  => true,
        ];
    }
}
