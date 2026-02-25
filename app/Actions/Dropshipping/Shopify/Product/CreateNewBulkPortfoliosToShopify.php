<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class CreateNewBulkPortfoliosToShopify extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $attributes): void
    {
        $portfoliosIds = DB::table('portfolios')
            ->select('id')
            ->where('customer_sales_channel_id', $customerSalesChannel->id)
            ->where('status', true)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        $totalNumber = count($portfoliosIds);

        // Use a unique key per job/session to avoid cross-request pollution
        $cacheKey = 'upload_progress_' . $customerSalesChannel->id . '_' . uniqid();
        Cache::put($cacheKey . '_success', 0, now()->addHour());
        Cache::put($cacheKey . '_fail', 0, now()->addHour());

        /** @var Portfolio $portfolio */
        foreach ($portfoliosIds as $portfoliosId) {
            $portfolio = Portfolio::find($portfoliosId->id);
            if ($portfolio) {
                $portfolio = StoreNewProductToCurrentShopify::run($portfolio);

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
        }

        Cache::forget($cacheKey . '_success');
        Cache::forget($cacheKey . '_fail');
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
