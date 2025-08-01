<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Jul 2025 22:16:49 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\Product\CheckShopifyPortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallbackProductChanged extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $modelData): void
    {
        $portfolio = Portfolio::where('customer_sales_channel_id', $shopifyUser->customer_sales_channel_id)->where('platform_product_id', $modelData['id'])->first();
        if ($portfolio) {
            CheckShopifyPortfolio::run($portfolio);
        }

    }

    public function rules(): array
    {
        return [
            'id' => ['required','numeric'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        if (!$shopifyUser->customer_id) {
            abort(422);
        }

        $this->initialisation($shopifyUser->organisation, $request);

        $this->handle($shopifyUser, $this->validatedData);
    }
}
