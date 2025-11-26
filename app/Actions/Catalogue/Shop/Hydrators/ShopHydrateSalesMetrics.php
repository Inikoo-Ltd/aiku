<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 25 Nov 2025 15:05:47 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateSalesMetrics;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopSalesMetrics;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateSalesMetrics implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateSalesMetrics;

    public string $commandSignature = 'hydrate:shop-sales-metrics {shop}';

    public function getJobUniqueId(Shop $shop, Carbon $date): string
    {
        return $shop->id . '-' . $date->format('YmdHis');
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();

        if (!$shop) {
            return;
        }

        $today = Carbon::today();

        $this->handle($shop, $today);
    }

    public function handle(Shop $shop, Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $metrics = $this->getSalesMetrics([
            'context' => ['shop_id' => $shop->id],
            'start'   => $dayStart,
            'end'     => $dayEnd,
            'fields'  => [
                'invoices',
                'refunds',
                'orders',
                'registrations',
                'baskets_created',
                'baskets_created_grp_currency',
                'baskets_created_org_currency',
                'sales',
                'sales_grp_currency',
                'sales_org_currency',
                'revenue',
                'revenue_grp_currency',
                'revenue_org_currency',
                'lost_revenue',
                'lost_revenue_grp_currency',
                'lost_revenue_org_currency'
            ]
        ]);

        dump($metrics);

//        ShopSalesMetrics::updateOrCreate(
//            [
//                'group_id'        => $shop->group_id,
//                'organization_id' => $shop->organisation_id,
//                'shop_id'         => $shop->id,
//                'date'            => $dayStart
//            ],
//            $metrics
//        );
    }
}
