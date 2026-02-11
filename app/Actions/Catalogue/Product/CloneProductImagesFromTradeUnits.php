<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 11:11:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Concerns\CanCloneImages;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductImagesFromTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use CanCloneImages;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        if (!$product->is_single_trade_unit) {
            CloneProductImagesFromMasterProduct::run($product);

            return;
        }

        /** @var TradeUnit $tradeUnit */
        $tradeUnit = $product->tradeUnits->first();

        if (!$tradeUnit) {
            return;
        }

        $this->syncProductImages($tradeUnit, $product);
    }

    public string $commandSignature = 'catalogue:product:clone-images-from-trade-units {product?}';

    public function asCommand(Command $command): int
    {
        if ($command->argument('product')) {
            /** @var Product $product */
            $product = Product::where('slug', $command->argument('product'))
                ->firstOrFail();

            $this->handle($product);

            $command->info("Images cloned from trade units for product: $product->code");
            return 0;
        }

        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();

        $query = Product::whereIn('shop_id', $aikuShops);
        $total = $query->count();

        $command->getOutput()->progressStart($total);

        $query->chunk(100, function ($products) use ($command) {
            foreach ($products as $product) {
                $this->handle($product);
                $command->getOutput()->progressAdvance();
            }
        });

        $command->getOutput()->progressFinish();

        return 0;
    }
}
