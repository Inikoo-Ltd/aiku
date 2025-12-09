<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Jul 2025 10:41:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Orders;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Helpers\Country;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FetchWooUserOrders extends OrgAction implements ShouldBeUnique
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $jobQueue = 'woo';

    public function getJobUniqueId(WooCommerceUser $wooCommerceUser): string
    {
        return $wooCommerceUser->id;
    }

    public string $commandSignature = 'fetch:woo-user-orders {slug}';

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('slug'))->first();

        $this->handle($customerSalesChannel->user);
    }

    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        if (! Arr::has($wooCommerceUser->checkConnection(), 'environment')) {
            return;
        }

        $wooOrders = $wooCommerceUser->getWooCommerceOrders(
            [
                'status'   => 'processing',
                'per_page' => 100,
                'after'    => now()->subDays(14)->toISOString(),
            ]
        );

        if ($wooOrders === null) {
            return;
        }

        foreach ($wooOrders as $wooOrder) {
            if (!Arr::get($wooOrder, 'date_paid')) {
                continue;
            }

            if (!Arr::get($wooOrder, 'shipping.country')) {
                continue;
            }

            if ($wooCommerceUser->customerSalesChannel?->shop) {
                $country = Country::where('code', Arr::get($wooOrder, 'shipping.country'))->first();

                if ($country) {
                    if (in_array($country->id, $wooCommerceUser->customerSalesChannel->shop->forbidden_dispatch_countries ?? [])) {
                        continue;
                    }
                }
            }

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

}
