<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\RetinaAction;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CreateNewBulkPortfolioToWooCommerce extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    public string $jobQueue = 'woo';

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, array $attributes = [])
    {
        $customerSalesChannel = $wooCommerceUser->customerSalesChannel;

        $portfolios = $wooCommerceUser
            ->customerSalesChannel
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
            $portfolio = StoreNewProductToCurrentWooCommerce::run($wooCommerceUser, $portfolio);

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

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($wooCommerceUser, $this->validatedData);
    }
}
