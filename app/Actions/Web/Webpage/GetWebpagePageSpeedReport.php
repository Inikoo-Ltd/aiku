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
     * @return array{status: string, message?: string, refresh_route?: array, mobile?: array|null, desktop?: array|null}
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
            $strategyReport = $this->strategyReport($webpage, $strategy);
            $isMeasuring    = cache()->has(GetWebpagePageSpeed::pendingKey($webpage, $strategy));

            if (!$strategyReport && !$isMeasuring) {
                $isMeasuring = QueueWebpagePageSpeed::run($webpage, $strategy);
            }

            if ($isMeasuring) {
                $report['status'] = 'measuring';
            }

            $report[$strategy] = $strategyReport
                ? $strategyReport + ['measuring' => $isMeasuring]
                : null;
        }

        return $report;
    }

    private function strategyReport(Webpage $webpage, string $strategy): ?array
    {
        $result = cache()->get(GetWebpagePageSpeed::resultKey($webpage, $strategy));

        if ($result) {
            return $result;
        }

        $error = cache()->get(GetWebpagePageSpeed::errorKey($webpage, $strategy));

        if ($error) {
            return [
                'strategy' => $strategy,
                'error'    => $error,
            ];
        }

        return null;
    }
}
