<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-10h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\RetinaAction;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

class StoreBulkNewProductToCurrentEbay extends RetinaAction
{
    public string $jobQueue = 'ebay';

    /**
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser, array $attributes = []): void
    {
        $customerSalesChannel = $ebayUser->customerSalesChannel;

        $portfolios = $ebayUser
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
            $portfolio = StoreNewProductToCurrentEbay::run($ebayUser, $portfolio);

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
            'portfolios'   => ['required', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    /**
     * @throws \Exception
     */
    public function asController(EbayUser $ebayUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($ebayUser, $this->validatedData);
    }
}
