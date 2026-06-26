<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 May 2026 21:19:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Analytics;

use App\Actions\Web\WebsiteVisitor\IsBot;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessWebsiteHit
{
    use AsAction;

    public string $jobQueue = 'analytics';
    public int $jobTimeout = 300;

    public function handle(array $metrics, string $userAgent): void
    {
        data_set($metrics, 'is_bot', IsBot::run($userAgent));

        \Sentry\traceMetrics()->count(
            'website.hit',
            1,
            $metrics
        );
    }

}
