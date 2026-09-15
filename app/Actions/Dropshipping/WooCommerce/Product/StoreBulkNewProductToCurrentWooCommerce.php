<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreBulkNewProductToCurrentWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public string $jobQueue = 'dropshipping-long';

    /**
     * One progress counter for the whole upload, shared by every chunk, so the page sees
     * success + fail reach the total instead of each chunk starting again from zero.
     *
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $attributes): void
    {
        $portfolios = $customerSalesChannel
            ->portfolios()
            ->where('status', true)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        $cacheKey = 'upload_progress_'.$customerSalesChannel->id.'_'.uniqid();
        Cache::put($cacheKey.'_success', 0, now()->addHour());
        Cache::put($cacheKey.'_fail', 0, now()->addHour());

        $bulkProgress = [
            'cache_key' => $cacheKey,
            'total'     => $portfolios->count(),
        ];

        foreach ($portfolios->chunk(100) as $portfolioChunk) {
            StoreBulkDispatchProductToCurrentWooCommerce::dispatch($customerSalesChannel, $portfolioChunk, $bulkProgress);
        }
    }
}
