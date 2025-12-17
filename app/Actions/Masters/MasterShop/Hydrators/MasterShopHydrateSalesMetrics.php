<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Wed, 17 Dec 2025 09:56:37 WITA
 * Location: Lembeng Beach, Bali, Indonesia
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateSalesMetrics;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterShopSalesMetrics;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateSalesMetrics implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateSalesMetrics;

    public string $commandSignature = 'hydrate:master-shop-sales-metrics {master_shop}';

    public function getJobUniqueId(MasterShop $masterShop, Carbon $date): string
    {
        return $masterShop->id . '-' . $date->format('YmdHis');
    }

    public function asCommand(Command $command): void
    {
        $masterShop = MasterShop::where('slug', $command->argument('master_shop'))->first();

        if (!$masterShop) {
            return;
        }

        $today = Carbon::today();

        $this->handle($masterShop, $today);
    }

    public function handle(MasterShop $masterShop, Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $metrics = $this->getSalesMetrics([
            'context' => ['master_shop_id' => $masterShop->id],
            'start'   => $dayStart,
            'end'     => $dayEnd,
            'fields'  => [
                'invoices',
                'refunds',
                'orders',
                'registrations',
                'baskets_created_grp_currency',
                'sales_grp_currency',
                'revenue_grp_currency',
                'lost_revenue_grp_currency'
            ]
        ]);

        MasterShopSalesMetrics::updateOrCreate(
            [
                'group_id'       => $masterShop->group_id,
                'master_shop_id' => $masterShop->id,
                'date'           => $dayStart
            ],
            $metrics
        );
    }
}
