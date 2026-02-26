<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\OrgAction;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreBulkNewProductToCurrentWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $attributes): void
    {
        $portfolios = $customerSalesChannel
            ->portfolios()
            ->where('status', true)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        $totalNumber = count($portfolios);

        // Use a unique key per job/session to avoid cross-request pollution
        $cacheKey = 'upload_progress_' . $customerSalesChannel->id . '_' . uniqid();
        Cache::put($cacheKey . '_success', 0, now()->addHour());
        Cache::put($cacheKey . '_fail', 0, now()->addHour());


        foreach ($portfolios as $portfolio) {
            $portfolio = StoreNewProductToCurrentWooCommerce::run($customerSalesChannel->user, $portfolio);

            if ($portfolio->platform_status) {
                Cache::increment($cacheKey . '_success');
            } else {
                Cache::increment($cacheKey . '_fail');
            }

            broadcast(new UploadProductToSalesChannelProgressEvent($customerSalesChannel, $portfolio, [
                'total'   => $totalNumber,
                'success' => Cache::get($cacheKey . '_success'),
                'fail'    => Cache::get($cacheKey . '_fail'),
            ]));
        }

        Cache::forget($cacheKey . '_success');
        Cache::forget($cacheKey . '_fail');
    }
}
