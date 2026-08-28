<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The watchdog behind the tax sweep progress bar: reconciles from the data every few
 * seconds until the sweep is done, so a dropped or dead reprice job can delay the bar
 * but never wedge it. Reschedules itself; the cache TTL is the hard stop.
 */
class CheckTaxPresetBasketProgress
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function handle(int $masterAssetId): void
    {
        TaxPresetBasketProgress::advance($masterAssetId);

        $progress = Cache::get(TaxPresetBasketProgress::progressKey($masterAssetId));

        /** On the sync driver a reschedule runs inline immediately: instant infinite recursion. */
        if ($progress && $progress['state'] != 'finished' && config('queue.default') != 'sync') {
            static::dispatch($masterAssetId)->delay(5);
        }
    }
}
