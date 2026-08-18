<?php

namespace App\Actions\Catalogue\Shop;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CloseShop
{
    use AsAction;

    public string $commandSignature = 'catalogue:close_shop {shop : Shop slug}';

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        if ($shop->state == ShopStateEnum::CLOSED) {
            $command->info("Shop {$shop->slug} is already closed");

            return 0;
        }

        $activeProducts = $shop->products()->where('state', ProductStateEnum::ACTIVE)->whereNull('deleted_at')->count();

        $command->warn("Closing {$shop->organisation->slug}/{$shop->slug} ({$shop->name}), state: {$shop->state->value}");
        $command->warn("This will discontinue its $activeProducts active products");

        if (!$command->confirm('Are you sure?')) {
            $command->info('Aborted');

            return 1;
        }

        UpdateShop::make()->action($shop, ['state' => ShopStateEnum::CLOSED]);
        $command->info("Shop {$shop->slug} closed; product discontinuation queued");

        return 0;
    }
}
