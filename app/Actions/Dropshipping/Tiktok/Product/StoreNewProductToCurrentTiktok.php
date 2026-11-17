<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\WithPortfolioErrorResponse;
use App\Actions\Traits\Authorisations\WithCustomerSalesChannelEditAuthorisation;
use App\Actions\OrgAction;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreNewProductToCurrentTiktok extends OrgAction implements ShouldBeUnique
{
    use WithCustomerSalesChannelEditAuthorisation;
    use AsAction;
    use WithPortfolioErrorResponse;

    public const int BULK_PROGRESS_HOURS = 6;

    public const string LISTING_LIMIT_REFUSAL = 'Cannot list more products';

    public string $jobQueue = 'tiktok';

    public bool $jobDeleteWhenMissingModels = true;

    public function getJobUniqueId(Portfolio $portfolio): int
    {
        return $portfolio->id;
    }

    /**
     * A product already on TikTok is not sent again: a job can sit in the queue past its unique lock and be queued a
     * second time. Once TikTok refuses a product because the shop is at its listing limit, the rest of that bulk upload
     * is not sent either: each would cost several calls only to be refused the same way, so they get TikTok's reason.
     */
    public function asJob(Portfolio $portfolio, ?array $bulkProgress = null): void
    {
        $portfolio = $portfolio->fresh() ?? $portfolio;
        $listingLimitKey = $bulkProgress ? $bulkProgress['cache_key'].'_listing_limit' : null;

        if (!$portfolio->platform_status) {
            $this->upload($portfolio, $bulkProgress, $listingLimitKey);
        }

        if ($bulkProgress) {
            $this->broadcastBulkProgress($portfolio, $bulkProgress);
        }
    }

    private function upload(Portfolio &$portfolio, ?array $bulkProgress, ?string $listingLimitKey): void
    {
        $listingLimitMessage = $listingLimitKey ? Cache::get($listingLimitKey) : null;

        if ($listingLimitMessage) {
            UpdatePortfolio::run($portfolio, [
                'errors_response' => ['message' => $listingLimitMessage],
            ]);

            return;
        }

        try {
            $portfolio = $this->handle($portfolio);
        } catch (\Throwable $e) {
            $this->recordPortfolioUploadFailure($portfolio, $e);

            if (!$bulkProgress) {
                throw $e;
            }
        }

        $errorMessage = (string) Arr::get($portfolio->errors_response, 'message');

        if ($listingLimitKey && !$portfolio->platform_status && str_contains($errorMessage, self::LISTING_LIMIT_REFUSAL)) {
            Cache::put($listingLimitKey, $errorMessage, now()->addHours(self::BULK_PROGRESS_HOURS));
        }
    }

    /**
     * An upload with progress swallows its own errors, so it only fails here when the queue killed it
     * (timeout, lost worker); without this the page waiting for its result never hears back.
     */
    public function jobFailed(\Throwable $e, Portfolio $portfolio, ?array $bulkProgress = null): void
    {
        if (!$bulkProgress) {
            return;
        }

        $portfolio = $portfolio->fresh() ?? $portfolio;

        if (!$portfolio->platform_status) {
            $this->recordPortfolioUploadFailure($portfolio, $e);
        }

        $this->broadcastBulkProgress($portfolio, $bulkProgress);
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

    public function handle(Portfolio $portfolio): Portfolio
    {
        return StoreProductToTiktok::run($portfolio);
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($portfolio->customerSalesChannel->organisation, $request);
        $this->handle($portfolio);
    }
}
