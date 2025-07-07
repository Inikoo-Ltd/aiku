<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 20:42:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FetchEbayUserOrders extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(EbayUser $ebayUser): void
    {

        $filter   = 'fulfillmentStatus:{NOT_STARTED,IN_PROGRESS},lastmodifieddate:['.now()->subDays(7)->toISOString().'..]';
        $response = $ebayUser->getOrders(limit: 150, filter: $filter);

        $ebayOrders = Arr::get($response, 'orders', []);
        foreach ($ebayOrders as $ebayOrder) {
            if (Arr::get($ebayOrder, 'cancelStatus.cancelState') == 'CANCELED') {
                continue;
            }

            if (DB::table('orders')->where('customer_id', $ebayUser->customer_id)
                ->where('platform_order_id', Arr::get($ebayOrder, 'orderId'))
                ->exists()) {
                continue;
            }


            $lineItems = collect(Arr::get($ebayOrder, 'lineItems', []))->pluck('legacyItemId')->filter()->toArray();

            $hasOutProducts = DB::table('portfolios')->where('customer_sales_channel_id', $ebayUser->customer_sales_channel_id)
                ->whereIn('platform_product_id', $lineItems)->exists();

            if ($hasOutProducts) {
                StoreOrderFromEbay::run($ebayUser, $ebayOrder);
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(EbayUser $ebayUser, ActionRequest $request): void
    {
        $this->initialisation($ebayUser->organisation, $request);

        $this->handle($ebayUser);
    }
}
