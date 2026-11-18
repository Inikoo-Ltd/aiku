<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CreateNewBulkPortfoliosToTiktok extends OrgAction implements ShouldBeUnique
{
    use WithActionUpdate;

    public string $jobQueue = 'tiktok';

    public function getJobUniqueId(CustomerSalesChannel $customerSalesChannel): int
    {
        return $customerSalesChannel->id;
    }

    /**
     * Each product is its own job: one upload takes several TikTok calls, so a channel's whole catalogue in one job
     * outlived the queue timeout and every retry started again from the first product.
     *
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $attributes): void
    {
        if (!$customerSalesChannel->user) {
            throw ValidationException::withMessages(['message' => __('No authenticated TikTok channel found.')]);
        }

        $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
            ->where('status', true)
            ->where('platform_status', false)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        $cacheKey = 'upload_progress_'.$customerSalesChannel->id.'_'.uniqid();
        Cache::put($cacheKey.'_success', 0, now()->addHours(StoreNewProductToCurrentTiktok::BULK_PROGRESS_HOURS));
        Cache::put($cacheKey.'_fail', 0, now()->addHours(StoreNewProductToCurrentTiktok::BULK_PROGRESS_HOURS));

        $bulkProgress = [
            'cache_key' => $cacheKey,
            'total'     => $portfolios->count(),
        ];

        foreach ($portfolios as $portfolio) {
            StoreNewProductToCurrentTiktok::dispatch($portfolio, $bulkProgress);
        }
    }

    public function rules(): array
    {
        return [
            'portfolios'   => ['required', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    /**
     * @throws \Exception
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($customerSalesChannel->organisation, $request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
