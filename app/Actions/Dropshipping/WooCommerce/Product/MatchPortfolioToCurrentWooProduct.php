<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Shopify\Product\CheckShopifyPortfolio;
use App\Actions\OrgAction;
use App\Events\UploadProductToWooCommerceProgressEvent;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchPortfolioToCurrentWooProduct extends OrgAction
{
    use AsAction;

    public function handle(Portfolio $portfolio, array $modelData)
    {
        $wooProductId = Arr::get($modelData, 'woo_product_id');
        $portfolio->update([
            'platform_product_id' => $wooProductId,
        ]);

        $portfolio->refresh();
        StoreWooCommerceProduct::run($portfolio);
        $portfolio = CheckShopifyPortfolio::run($portfolio);

        UploadProductToWooCommerceProgressEvent::dispatch($portfolio->customerSalesChannel->user, $portfolio);
    }


    public function rules(): array
    {
        return [
            'woo_product_id' => ['required', 'string'],
        ];
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {

        $this->initialisation($portfolio->customerSalesChannel->organisation, $request);
        $this->handle($portfolio, $this->validatedData);
    }

}
