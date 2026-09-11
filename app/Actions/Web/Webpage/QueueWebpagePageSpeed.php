<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class QueueWebpagePageSpeed
{
    use AsAction;

    private const int PENDING_GRACE_MINUTES = 10;

    /**
     * A PageSpeed Insights run takes up to a minute, so it never happens inside the request.
     * The pending flag keeps a webpage from being queued twice while its run is still in flight.
     */
    public function handle(Webpage $webpage, string $strategy, int $delaySeconds = 0): bool
    {
        $pendingUntil = now()->addSeconds($delaySeconds)->addMinutes(self::PENDING_GRACE_MINUTES);

        if (!cache()->add(GetWebpagePageSpeed::pendingKey($webpage, $strategy), true, $pendingUntil)) {
            return false;
        }

        cache()->forget(GetWebpagePageSpeed::errorKey($webpage, $strategy));

        GetWebpagePageSpeed::dispatch($webpage, $strategy, true)->delay(now()->addSeconds($delaySeconds));

        return true;
    }
}
