<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 20:42:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithRetinaCustomerOwnedRouteModels;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class FetchEbayUserOrders extends OrgAction implements ShouldBeUnique
{
    use WithRetinaCustomerOwnedRouteModels;
    use WithActionUpdate;

    public string $jobQueue = 'ebay';

    public function getJobUniqueId(EbayUser $ebayUser): int
    {
        return $ebayUser->id;
    }


    /**
     * @throws \Throwable
     */
    public function handle(EbayUser $ebayUser): void
    {
        /** @noinspection SpellCheckingInspection */
        $filter   = 'orderfulfillmentstatus:{NOT_STARTED|IN_PROGRESS}';
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


            if ($this->hasOurProducts($ebayUser, Arr::get($ebayOrder, 'lineItems', []))) {
                if (Cache::add('ebay-order-logged:'.$ebayUser->id.':'.Arr::get($ebayOrder, 'orderId'), true, now()->addDays(30))) {
                    $ebayUser->debugWebhooks()->create([
                        'data' => $ebayOrder
                    ]);
                }

                StoreOrderFromEbay::run($ebayUser, $ebayOrder);
            }
        }
    }

    /**
     * A listing the merchant relisted or duplicated on eBay has a new item id but keeps our SKU,
     * so a line is ours when its listing is in the portfolio or its SKU matches a portfolio product.
     *
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    public function hasOurProducts(EbayUser $ebayUser, array $lineItems): bool
    {
        $portfolios = DB::table('portfolios')->where('customer_sales_channel_id', $ebayUser->customer_sales_channel_id);

        $listingIds = collect($lineItems)->pluck('legacyItemId')->filter()->values()->all();
        if ((clone $portfolios)->whereIn('platform_product_variant_id', $listingIds)->exists()) {
            return true;
        }

        $skus = collect($lineItems)->pluck('sku')->filter()->values()->all();
        if (!$skus || !(clone $portfolios)->where(fn ($query) => $query->whereIn('sku', $skus)->orWhereIn('platform_sku', $skus))->exists()) {
            return false;
        }

        $storeOrderFromEbay = StoreOrderFromEbay::make();

        return collect($lineItems)->contains(fn (array $lineItem) => $storeOrderFromEbay->matchLineItemToProductId($ebayUser, $lineItem) !== null);
    }

    public string $commandSignature = 'ebay-user-orders-fetch {customerSalesChannel}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();
        $this->handle($customerSalesChannel->user);
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
