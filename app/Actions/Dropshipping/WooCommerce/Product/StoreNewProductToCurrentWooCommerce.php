<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\WithPortfolioErrorResponse;
use App\Actions\OrgAction;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Redis;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Decorators\JobDecorator;

class StoreNewProductToCurrentWooCommerce extends OrgAction implements ShouldBeUnique
{
    use WithPortfolioErrorResponse;
    use AsAction;

    public string $jobQueue = 'woo';

    public int $jobMaxExceptions = 2;

    /**
     * A shared host serves few requests at once and each create downloads every image, so a bulk
     * upload sending one request per worker stalls the store past the timeout and holds the shared
     * dropshipping workers meanwhile. ponytail: fixed cap for every store, per-channel setting if a
     * host needs lower.
     */
    public const int MAX_CONCURRENT_CREATES_PER_STORE = 4;

    /**
     * At 4 creates at a time a store takes ~750 products an hour, so an 8k bulk upload queues for
     * ~11h. A spread wait keeps thousands of waiting jobs from being popped every few seconds in
     * lockstep, and retryUntil must outlast the whole upload. ponytail: fixed window, derive it from
     * the store's backlog if uploads above ~18k products appear.
     */
    public const int MIN_WAIT_FOR_SLOT_SECONDS = 20;

    public const int MAX_WAIT_FOR_SLOT_SECONDS = 180;

    public const int RETRY_FOR_HOURS = 24;

    /**
     * A bulk upload staggers its creates at this pace so slots are rarely busy when a job arrives
     * and waiting jobs are not popped over and over; a slow store still falls back to the funnel.
     */
    public const int STAGGER_SECONDS_PER_SLOT_ROUND = 10;

    public int $jobUniqueFor = (self::RETRY_FOR_HOURS + 1) * 3600;

    public function getJobUniqueId(WooCommerceUser $wooCommerceUser, Portfolio $portfolio): string
    {
        return $portfolio->id;
    }

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, Portfolio $portfolio, bool $checkConnection = true, ?array $bulkProgress = null): Portfolio
    {
        try {
            $result = true;
            if ($checkConnection) {
                $result = $wooCommerceUser->checkConnection();
            }

            if ($result) {
                $portfolio = StoreWooCommerceProduct::run($wooCommerceUser, $portfolio);
            } else {
                $wooCommerceUser->customerSalesChannel->update([
                    'ban_stock_update_util' => now()->addSeconds(10)
                ]);
            }
        } catch (\Throwable $e) {
            $this->recordPortfolioUploadFailure($portfolio, $e);

            if (!$bulkProgress) {
                throw $e;
            }
        }

        if ($bulkProgress) {
            $this->broadcastBulkProgress($wooCommerceUser, $portfolio, $bulkProgress);
        }

        return $portfolio;
    }

    /**
     * Waiting for a slot releases the job instead of blocking a worker; a release is an attempt, so
     * the job lives by retryUntil and only real exceptions count against it.
     */
    public function asJob(JobDecorator $job, WooCommerceUser $wooCommerceUser, Portfolio $portfolio, bool $checkConnection = true, ?array $bulkProgress = null): void
    {
        Redis::funnel('woo_product_create_'.$wooCommerceUser->id)
            ->limit(self::MAX_CONCURRENT_CREATES_PER_STORE)
            ->releaseAfter(StoreWooCommerceProduct::CREATE_TIMEOUT_SECONDS * 3)
            ->block(0)
            ->then(
                fn () => $this->handle($wooCommerceUser, $portfolio, $checkConnection, $bulkProgress),
                fn () => $job->release(random_int(self::MIN_WAIT_FOR_SLOT_SECONDS, self::MAX_WAIT_FOR_SLOT_SECONDS))
            );
    }

    public function getJobRetryUntil(): \DateTimeInterface
    {
        return now()->addHours(self::RETRY_FOR_HOURS);
    }

    /**
     * A job the queue kills (timeout, lost worker) never reaches the broadcast in handle, and the
     * page would wait for ever; count it as a failure so the progress can still complete.
     */
    public function jobFailed(\Throwable $e, WooCommerceUser $wooCommerceUser, Portfolio $portfolio, bool $checkConnection = true, ?array $bulkProgress = null): void
    {
        if ($bulkProgress) {
            $this->broadcastBulkProgress($wooCommerceUser, $portfolio->fresh() ?? $portfolio, $bulkProgress);
        }
    }

    public function broadcastBulkProgress(WooCommerceUser $wooCommerceUser, Portfolio $portfolio, array $bulkProgress): void
    {
        $cacheKey = $bulkProgress['cache_key'];
        Cache::increment($cacheKey.($portfolio->platform_status ? '_success' : '_fail'));

        UploadProductToSalesChannelProgressEvent::dispatch($wooCommerceUser->customerSalesChannel, $portfolio, [
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
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $portfolio->customerSalesChannel->user;
        $this->initialisation($portfolio->organisation, $request);

        $this->handle($wooCommerceUser, $portfolio);
    }
}
