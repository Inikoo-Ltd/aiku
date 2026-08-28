<?php

namespace App\Actions\Catalogue\Product;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class DiscontinueProductsInClosedShop
{
    use AsAction;

    public string $commandSignature = 'catalogue:discontinue_closed_shop_products {organisation?} {--commit : Apply the changes, otherwise dry run}';

    public function handle(Shop $shop, int $hydratorsDelay = 120): int
    {
        if ($shop->state != ShopStateEnum::CLOSED) {
            return 0;
        }

        $discontinued = 0;
        $shop->products()
            ->where('state', ProductStateEnum::ACTIVE)
            ->whereNull('deleted_at')
            ->chunkById(200, function ($products) use (&$discontinued, $hydratorsDelay) {
                foreach ($products as $product) {
                    UpdateProduct::make()->action(
                        product: $product,
                        modelData: ['state' => ProductStateEnum::DISCONTINUED],
                        hydratorsDelay: $hydratorsDelay
                    );
                    $discontinued++;
                }
            });

        return $discontinued;
    }

    public function asCommand(Command $command): int
    {
        $commit = (bool)$command->option('commit');

        $shops = Shop::where('state', ShopStateEnum::CLOSED)
            ->when($command->argument('organisation'), function ($query) use ($command) {
                $query->whereHas('organisation', fn ($q) => $q->where('slug', $command->argument('organisation')));
            })
            ->withCount(['products' => fn ($q) => $q->where('state', ProductStateEnum::ACTIVE)->whereNull('deleted_at')])
            ->get()
            ->filter(fn ($shop) => $shop->products_count > 0);

        if ($shops->isEmpty()) {
            $command->info('No closed shops with active products found');

            return 0;
        }

        $total = 0;
        foreach ($shops as $shop) {
            $command->line(($commit ? 'DISCONTINUING ' : 'WOULD DISCONTINUE ')."{$shop->products_count} products in {$shop->organisation->slug}/{$shop->slug} ({$shop->name}) [shop state: {$shop->state->value}]");
            if ($commit) {
                $total += $this->handle($shop);
            } else {
                $total += $shop->products_count;
            }
        }

        $command->info(($commit ? 'Discontinued' : 'Would discontinue').": $total products in {$shops->count()} closed shops");

        return 0;
    }
}
