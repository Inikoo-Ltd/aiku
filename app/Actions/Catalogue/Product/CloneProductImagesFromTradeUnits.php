<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 11:11:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Concerns\CanCloneImages;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateImages;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;

class CloneProductImagesFromTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use AsCommand;
    use CanCloneImages;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        if (!$product->is_single_trade_unit) {
            return;
        }

        /** @var \App\Models\Goods\TradeUnit $tradeUnit */
        $tradeUnit = $product->tradeUnits->first();

        if (!$tradeUnit) {
            return;
        }

        $this->cloneImages($tradeUnit, $product);

        $product->update([
            'image_id'                 => $tradeUnit->image_id,
            'front_image_id'           => $tradeUnit->front_image_id,
            '34_image_id'              => $tradeUnit->{'34_image_id'},
            'left_image_id'            => $tradeUnit->left_image_id,
            'right_image_id'           => $tradeUnit->right_image_id,
            'back_image_id'            => $tradeUnit->back_image_id,
            'top_image_id'             => $tradeUnit->top_image_id,
            'bottom_image_id'          => $tradeUnit->bottom_image_id,
            'size_comparison_image_id' => $tradeUnit->size_comparison_image_id,
            'art1_image_id'            => $tradeUnit->art1_image_id,
            'art2_image_id'            => $tradeUnit->art2_image_id,
            'art3_image_id'            => $tradeUnit->art3_image_id,
            'art4_image_id'            => $tradeUnit->art4_image_id,
            'art5_image_id'            => $tradeUnit->art5_image_id,
            'lifestyle_image_id'       => $tradeUnit->lifestyle_image_id,
        ]);
        $changed = Arr::except($product->getChanges(), ['updated_at', 'last_fetched_at']);

        if(!empty($changed)){
            BreakProductInWebpagesCache::dispatch($product)->delay(15);
        }

        ProductHydrateImages::run($product);
        UpdateProductWebImages::run($product);





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
