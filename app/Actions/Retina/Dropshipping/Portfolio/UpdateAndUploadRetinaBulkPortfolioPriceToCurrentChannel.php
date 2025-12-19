<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Ebay\Product\StoreNewProductToCurrentEbay;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\Shopify\Product\StoreNewProductToCurrentShopify;
use App\Actions\Dropshipping\WooCommerce\Product\StoreWooCommerceProduct;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateAndUploadRetinaBulkPortfolioPriceToCurrentChannel extends RetinaAction
{
    use AsAction;

    public function handle(array $modelData, $isDraft = false): void
    {
        foreach (Arr::pull($modelData, 'items') as $itemId) {
            $portfolio = Portfolio::find($itemId);

            if(Arr::get($modelData, 'type') === 'fixed') {
                $newPrice = Arr::get($modelData, 'amount') + $portfolio->customer_price;
            } else if(Arr::get($modelData, 'type') === 'fixed') {
                $newPrice = $portfolio->customer_price * (1 + Arr::get($modelData, 'amount') / 100);
            } else {
                $newPrice = $portfolio->item->rrp;
            }

            data_set($modelData, 'customer_price', $newPrice);
            data_forget($modelData, 'amount');
            data_forget($modelData, 'type');

            UpdateAndUploadRetinaPortfolioToCurrentChannel::dispatch($portfolio, $modelData, $isDraft);
        }
    }

    public function rules(): array
    {
        return [
            'items' => ['array'],
            'amount' => ['sometimes', 'numeric'],
            'type' => ['sometimes', 'string'],
        ];
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {

        $this->initialisation($request);
        $this->handle($this->validatedData);
    }

    public function asDraft(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($this->validatedData, true);
    }

}
