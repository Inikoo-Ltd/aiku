<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Jul 2026 10:44:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Product\CloneProductImagesFromTradeUnits;
use App\Actions\Masters\MasterAsset\CloneMasterAssetImagesFromTradeUnits;
use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class HydrateProductImagesFromTradeUnits extends OrgAction
{
    public string $commandSignature = 'catalogue:product:hydrate-images-from-trade-units {parent?} {slug?}';

    public function handle(Product $product): void
    {

        $masterAsset                = $product->masterProduct;
        $masterFollowTradeUnitMedia = $masterAsset?->follow_trade_unit_media;

        $followMaster = false;
        if ($masterAsset) {
            if ($masterAsset->is_single_trade_unit && $masterFollowTradeUnitMedia) {
                CloneMasterAssetImagesFromTradeUnits::run($masterAsset);
            }

            if (!$masterFollowTradeUnitMedia) {
                $followMaster = true;
            }
        }

        if ($product->is_single_trade_unit && !$followMaster) {
            CloneProductImagesFromTradeUnits::run($product);
        }
    }

    public function asCommand(Command $command): int
    {
        $shopIds = null;

        if ($command->argument('parent')) {
            if ($command->argument('parent') === 'product' || $command->argument('parent') === 'p') {
                $product = Product::where('slug', $command->argument('slug'))->firstOrFail();

                $this->handle($product);

                $command->info("Images hydrated from trade units for product: $product->code");

                return 0;
            }

            if ($command->argument('parent') === 'shop' || $command->argument('parent') === 's') {
                $shop = Shop::where('slug', $command->argument('slug'))->firstOrFail();
                $shopIds = [$shop->id];
            }
        }

        if ($shopIds === null) {
            $shopIds = Shop::where('is_aiku', true)->pluck('id')->all();
        }

       
        $query = Product::whereIn('shop_id', $shopIds);
        $total = $query->count();

        $command->getOutput()->setVerbosity(\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_DEBUG);
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

    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisationFromShop($product->shop, $request);

        $this->handle($product);
    }



}
