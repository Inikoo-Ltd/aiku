<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Jul 2025 10:41:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Orders;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FetchWooUserOrders extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        $wooOrders = $wooCommerceUser->getWooCommerceOrders(
            [
                'status'   => 'processing',
                'per_page' => 100,
                'after'    => now()->subDays(14)->toISOString(),
            ]
        );

        if($wooOrders===null){
            return;
        }

        foreach ($wooOrders as $wooOrder) {
            if (DB::table('orders')
                ->where('customer_id', $wooCommerceUser->customer_id)
                ->where('platform_order_id', Arr::get($wooOrder, 'order_key'))
                ->exists()) {
                continue;
            }

            $lineItems = collect(Arr::get($wooOrder, 'line_items', []))->pluck('product_id')->filter()->toArray();

            $hasOutProducts = DB::table('portfolios')
                ->where('customer_sales_channel_id', $wooCommerceUser->customer_sales_channel_id)
                ->whereIn('platform_product_id', $lineItems)
                ->exists();

            if ($hasOutProducts) {
                StoreOrderFromWooCommerce::run($wooCommerceUser, $wooOrder);
            }
        }
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request): void
    {
        $this->initialisation($wooCommerceUser->organisation, $request);

        $this->handle($wooCommerceUser);
    }
}
