<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\OrgAction;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreNewProductToCurrentShopify extends OrgAction implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'shopify';

    public function getJobUniqueId(Portfolio $portfolio): int
    {
        return $portfolio->id;
    }


    public function asJob(Portfolio $portfolio, ?array $bulkProgress = null): void
    {
        try {
            $portfolio = $this->handle($portfolio, []);
        } catch (\Throwable) {
        }

        if ($bulkProgress) {
            $this->broadcastBulkProgress($portfolio, $bulkProgress);
        }
    }

    public function broadcastBulkProgress(Portfolio $portfolio, array $bulkProgress): void
    {
        $cacheKey = $bulkProgress['cache_key'];
        Cache::increment($cacheKey.($portfolio->platform_status ? '_success' : '_fail'));

        UploadProductToSalesChannelProgressEvent::dispatch($portfolio->customerSalesChannel, $portfolio, [
            'total'   => $bulkProgress['total'],
            'success' => (int) Cache::get($cacheKey.'_success'),
            'fail'    => (int) Cache::get($cacheKey.'_fail'),
        ]);
    }

    public function handle(Portfolio $portfolio, array $modelData): Portfolio
    {
        [$stored] = StoreShopifyProduct::run($portfolio, $modelData);

        if ($stored) {
            $portfolio = CheckShopifyPortfolio::run($portfolio->refresh());
        }

        return $portfolio;
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($portfolio->customerSalesChannel->organisation, $request);
        $this->handle($portfolio, $this->validatedData);
    }

}
