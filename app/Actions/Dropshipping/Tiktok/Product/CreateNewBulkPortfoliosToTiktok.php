<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\Shopify\Product\StoreNewProductToCurrentShopify;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class CreateNewBulkPortfoliosToTiktok extends OrgAction
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
        $failNumber = 0;
        $successNumber = 0;

        /** @var \App\Models\Dropshipping\Portfolio $portfolio */
        foreach ($portfoliosIds as $portfoliosId) {
            $portfolio = Portfolio::find($portfoliosId->id);
            if ($portfolio) {
                $portfolio = StoreProductToTiktok::run($portfolio);

                if ($portfolio->platform_status) {
                    $successNumber++;
                } else {
                    $failNumber++;
                }

                UploadProductToSalesChannelProgressEvent::dispatch($customerSalesChannel, $portfolio, [
                    'total' => $totalNumber,
                    'success' => $successNumber,
                    'fail' => $failNumber
                ]);
            }
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
