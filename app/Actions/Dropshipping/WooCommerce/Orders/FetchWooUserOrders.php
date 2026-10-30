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

    /**
     * A paid order that has since been cancelled, refunded or binned must never become a warehouse order.
     */
    private const array SKIPPED_STATUSES = ['cancelled', 'refunded', 'failed', 'trash'];

    /**
     * Stores that complete paid orders on their own move them past processing before the next
     * fetch (CUS-1818), so completed orders are asked for too, but only those paid after this
     * went live: older completed orders were handled by the merchant some other way.
     */
    private const string COMPLETED_ORDERS_PAID_AFTER = '2026-10-30T14:00:00';

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
        if (! $wooCommerceUser->checkConnection()) {
            return;
        }

        $wooOrders = [];
        for ($page = 1; $page <= 10; $page++) {
            $pageOrders = $wooCommerceUser->getWooCommerceOrders(
                [
                    'status'   => 'processing,completed',
                    'per_page' => 100,
                    'page'     => $page,
                    'after'    => now()->subDays(14)->toISOString(),
                ]
            );

            if (!is_array($pageOrders)) {
                break;
            }

            $wooOrders = array_merge($wooOrders, $pageOrders);

            if (count($pageOrders) < 100) {
                break;
            }
        }

        foreach ($wooOrders as $wooOrder) {
            if (blank($wooOrder) || !is_array($wooOrder)) {
                continue;
            }

            if (!Arr::get($wooOrder, 'date_paid') || in_array(Arr::get($wooOrder, 'status'), self::SKIPPED_STATUSES)) {
                continue;
            }

            if (Arr::get($wooOrder, 'status') === 'completed' && Arr::get($wooOrder, 'date_paid_gmt') < self::COMPLETED_ORDERS_PAID_AFTER) {
                continue;
            }

            if (!Arr::get($wooOrder, 'shipping.country')) {
                continue;
            }

            if (DB::table('orders')
                ->where('customer_id', $wooCommerceUser->customer_id)
                ->where('platform_order_id', Arr::get($wooOrder, 'order_key'))
                ->exists()) {
                continue;
            }

            $lineItems = collect(Arr::get($wooOrder, 'line_items', []))
                ->flatMap(fn ($item) => StoreOrderFromWooCommerce::lineItemPlatformProductIds($item))
                ->all();

            $hasOutProducts = DB::table('portfolios')
                ->where('customer_sales_channel_id', $wooCommerceUser->customer_sales_channel_id)
                ->whereIn('platform_product_id', $lineItems)
                ->exists();

            if ($hasOutProducts) {
                $wooCommerceUser->debugWebhooks()->create([
                    'data' => array_map(function ($value) {
                        return is_string($value) ? mb_convert_encoding($value, 'UTF-8', 'UTF-8') : $value;
                    }, $wooOrder)
                ]);

                if ($wooCommerceUser->customer->is_dropshipping) {
                    StoreOrderFromWooCommerce::run($wooCommerceUser, $wooOrder);
                } else {
                    StoreFulfilmentFromWooCommerce::run($wooCommerceUser, $wooOrder);
                }
            }
        }
    }

}
