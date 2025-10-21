<?php

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopOrderingStats;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateRevenue implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:store-revenue {shop}';

    public function getJobUniqueId(int $shopId): string
    {
        return $shopId;
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();

        if (!$shop) {
            $command->error('Store not found.');
            return;
        }

        $this->handle($shop->id);

        $command->info("Hydration completed for shop {$shop->name}");
    }

    public function handle(int $shopId): void
    {
        $shop = Shop::find($shopId);

        if (!$shop) {
            return;
        }

        $customers = $shop->customers()->with('stats')->get();

        if ($customers->isEmpty()) {
            return;
        }

        $stats = [
            'revenue_amount' => $customers->sum(fn ($c) => $c->stats->revenue_amount ?? 0),
            'lost_revenue_other_amount' => $customers->sum(fn ($c) => $c->stats->lost_revenue_other_amount ?? 0),
            'lost_revenue_out_of_stock_amount' => $customers->sum(fn ($c) => $c->stats->lost_revenue_out_of_stock_amount ?? 0),
            'lost_revenue_replacements_amount' => $customers->sum(fn ($c) => $c->stats->lost_revenue_replacements_amount ?? 0),
            'lost_revenue_compensations_amount' => $customers->sum(fn ($c) => $c->stats->lost_revenue_compensations_amount ?? 0),
        ];

        $shopStats = $shop->orderingStats ?? new ShopOrderingStats(['shop_id' => $shop->id]);
        $shopStats->fill($stats);
        $shopStats->save();
    }
}
