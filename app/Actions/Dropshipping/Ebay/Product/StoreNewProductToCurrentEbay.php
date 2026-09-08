<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-10h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\OrgAction;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreNewProductToCurrentEbay extends OrgAction
{
    use AsAction;

    public string $jobQueue = 'ebay';


    /**
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser, Portfolio $portfolio, ?array $bulkProgress = null): Portfolio
    {
        try {
            $portfolio = StoreEbayProduct::run($ebayUser, $portfolio);
        } catch (\Throwable $e) {
            if (!$bulkProgress) {
                throw $e;
            }
        }

        if ($bulkProgress) {
            $this->broadcastBulkProgress($ebayUser, $portfolio, $bulkProgress);
        }

        return $portfolio;
    }

    public function broadcastBulkProgress(EbayUser $ebayUser, Portfolio $portfolio, array $bulkProgress): void
    {
        $cacheKey = $bulkProgress['cache_key'];
        Cache::increment($cacheKey.($portfolio->platform_status ? '_success' : '_fail'));

        UploadProductToSalesChannelProgressEvent::dispatch($ebayUser->customerSalesChannel, $portfolio, [
            'total'   => $bulkProgress['total'],
            'success' => (int) Cache::get($cacheKey.'_success'),
            'fail'    => (int) Cache::get($cacheKey.'_fail'),
        ]);
    }

    /**
     * @throws \Exception
     */
    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($portfolio->organisation, $request);

        /** @var EbayUser $ebayUser */
        $ebayUser = $portfolio->customerSalesChannel->user;

        $this->handle($ebayUser, $portfolio);
    }
}
