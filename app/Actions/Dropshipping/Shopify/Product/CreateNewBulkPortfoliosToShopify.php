<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

class CreateNewBulkPortfoliosToShopify extends OrgAction implements ShouldBeUnique
{
    use WithActionUpdate;


    public string $jobQueue = 'dropshipping-long';
    public int $jobTries = 1;

    public function getJobUniqueId(CustomerSalesChannel $customerSalesChannel): int
    {
        return $customerSalesChannel->id;
    }

    /**
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $attributes): void
    {
        $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
            ->where('status', true)
            ->where('platform_status', false)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        $cacheKey = 'upload_progress_' . $customerSalesChannel->id . '_' . uniqid();
        Cache::put($cacheKey . '_success', 0, now()->addHour());
        Cache::put($cacheKey . '_fail', 0, now()->addHour());

        $bulkProgress = [
            'cache_key' => $cacheKey,
            'total'     => $portfolios->count(),
        ];

        // ponytail: counters cleaned by TTL; a hard-failed product job leaves the progress bar short of total
        foreach ($portfolios as $portfolio) {
            StoreNewProductToCurrentShopify::dispatch($portfolio, $bulkProgress);
        }
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array'],
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
