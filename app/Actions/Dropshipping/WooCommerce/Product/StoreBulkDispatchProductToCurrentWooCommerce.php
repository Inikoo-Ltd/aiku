<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreBulkDispatchProductToCurrentWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public string $jobQueue = 'woo';

    /**
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, $portfolios, int $totalNumber): void
    {
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $customerSalesChannel->user;

        $cacheKey = 'upload_progress_' . $customerSalesChannel->id . '_' . uniqid();
        Cache::put($cacheKey . '_success', 0, now()->addHour());
        Cache::put($cacheKey . '_fail', 0, now()->addHour());

        $needCheckConnection = !$wooCommerceUser->checkConnection();

        $bulkProgress = [
            'cache_key' => $cacheKey,
            'total'     => $totalNumber,
        ];

        // ponytail: counters cleaned by TTL; a hard-failed product job leaves the progress bar short of total
        foreach ($portfolios as $portfolio) {
            StoreNewProductToCurrentWooCommerce::dispatch($wooCommerceUser, $portfolio, $needCheckConnection, $bulkProgress);
        }
    }
}
