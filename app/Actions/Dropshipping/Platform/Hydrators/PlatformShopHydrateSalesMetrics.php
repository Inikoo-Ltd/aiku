<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Tue, 17 Dec 2025 11:20:00 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateSalesMetrics;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformShopSalesMetrics;
use App\Models\Dropshipping\Portfolio;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PlatformShopHydrateSalesMetrics implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateSalesMetrics;

    public string $commandSignature = 'hydrate:platform-shop-sales-metrics {platform} {shop}';

    public function getJobUniqueId(Platform $platform, Shop $shop, Carbon $date): string
    {
        return $platform->id . '-' . $shop->id . '-' . $date->format('YmdHis');
    }

    public function asCommand(Command $command): void
    {
        $platform = Platform::where('slug', $command->argument('platform'))->first();
        $shop = Shop::where('slug', $command->argument('shop'))->first();

        if (!$platform || !$shop) {
            return;
        }

        $today = Carbon::today();

        $this->handle($platform, $shop, $today);
    }

    public function handle(Platform $platform, Shop $shop, Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $metrics = $this->getSalesMetrics([
            'context' => [
                'platform_id' => $platform->id,
                'shop_id'     => $shop->id
            ],
            'start'   => $dayStart,
            'end'     => $dayEnd,
            'fields'  => [
                'invoices',
                'sales',
                'sales_grp_currency',
                'sales_org_currency'
            ]
        ]);

        $metrics = array_merge(
            $metrics,
            [
                'new_channels' => CustomerSalesChannel::where('platform_id', $platform->id)
                    ->where('shop_id', $shop->id)
                    ->where('status', CustomerSalesChannelStatusEnum::OPEN)
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->count(),

                'new_customers' => CustomerSalesChannel::leftJoin('customers', 'customer_sales_channels.customer_id', '=', 'customers.id')
                    ->where('platform_id', $platform->id)
                    ->where('customer_sales_channels.shop_id', $shop->id)
                    ->whereBetween('customer_sales_channels.created_at', [$dayStart, $dayEnd])
                    ->distinct('customer_sales_channels.customer_id')
                    ->count('customer_sales_channels.customer_id'),

                'new_portfolios' => Portfolio::where('portfolios.item_type', class_basename(Product::class))
                    ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
                    ->where('portfolios.platform_id', $platform->id)
                    ->where('portfolios.shop_id', $shop->id)
                    ->whereBetween('portfolios.created_at', [$dayStart, $dayEnd])
                    ->distinct('portfolios.item_id')
                    ->count('portfolios.item_id'),

                'new_customer_client' => CustomerClient::where('platform_id', $platform->id)
                    ->where('shop_id', $shop->id)
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->count(),
            ]
        );

        PlatformShopSalesMetrics::updateOrCreate(
            [
                'group_id'        => $shop->group_id,
                'organisation_id' => $shop->organisation_id,
                'shop_id'         => $shop->id,
                'platform_id'     => $platform->id,
                'date'            => $dayStart
            ],
            $metrics
        );
    }
}
