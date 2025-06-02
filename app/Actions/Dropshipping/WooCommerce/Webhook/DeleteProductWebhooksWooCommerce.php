<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-14h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\WooCommerce\Webhook;

use App\Actions\Dropshipping\Portfolio\DeletePortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteProductWebhooksWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser, array $modelData): void
    {
        $productId = Arr::get($modelData, 'id');

        $portfolio = Portfolio::where('customer_sales_channel_id', $wooCommerceUser->customer_sales_channel_id)
            ->where("platform_product_id", $productId)
            ->first();

        if ($portfolio) {
            DeletePortfolio::run($wooCommerceUser->customerSalesChannel, $portfolio, true);
        }
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request): void
    {
        $this->initialisation($wooCommerceUser->organisation, $request);

        $this->handle($wooCommerceUser, $request->all());
    }
}
