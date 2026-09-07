<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class CreateNewBulkPortfoliosToWix extends OrgAction implements ShouldBeUnique
{
    use WithActionUpdate;

    public function getJobUniqueId(CustomerSalesChannel $customerSalesChannel): int
    {
        return $customerSalesChannel->id;
    }

    public function handle(CustomerSalesChannel $customerSalesChannel, array $attributes): void
    {
        $portfoliosIds = DB::table('portfolios')
            ->select('id')
            ->where('customer_sales_channel_id', $customerSalesChannel->id)
            ->where('status', true)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        $totalNumber = count($portfoliosIds);

        $cacheKey = 'upload_progress_'.$customerSalesChannel->id.'_'.uniqid();
        Cache::put($cacheKey.'_success', 0, now()->addHour());
        Cache::put($cacheKey.'_fail', 0, now()->addHour());

        foreach ($portfoliosIds as $portfoliosId) {
            $portfolio = Portfolio::find($portfoliosId->id);

            if (!$portfolio) {
                continue;
            }

            $portfolio = StoreProductToWix::run($portfolio);

            if ($portfolio->platform_status) {
                Cache::increment($cacheKey.'_success');
            } else {
                Cache::increment($cacheKey.'_fail');
            }

            UploadProductToSalesChannelProgressEvent::dispatch($customerSalesChannel, $portfolio, [
                'total'   => $totalNumber,
                'success' => Cache::get($cacheKey.'_success'),
                'fail'    => Cache::get($cacheKey.'_fail'),
            ]);
        }

        Cache::forget($cacheKey.'_success');
        Cache::forget($cacheKey.'_fail');
    }

    public function rules(): array
    {
        return [
            'portfolios'   => ['required', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($customerSalesChannel->organisation, $request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
