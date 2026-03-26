<?php

/*
 * author Louis Perez
 * created on 09-03-2026-09h-10m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateNumberMismatches;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateMismatch implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int|null $masterAssetID): string
    {
        return $masterAssetID ?? 'empty';
    }

    public function handle(MasterAsset $masterProduct): void
    {
        $masterAssetTradeUnits = $masterProduct->tradeUnits->pluck('pivot.quantity', 'id');

        $products = $masterProduct->products;
        foreach ($products as $product) {
            $productTradeUnits = $product->tradeUnits->pluck('pivot.quantity', 'id');

            $diffFromMaster  = $masterAssetTradeUnits->diffAssoc($productTradeUnits);
            $diffFromProduct = $productTradeUnits->diffAssoc($masterAssetTradeUnits);

            if ($diffFromMaster->isNotEmpty() || $diffFromProduct->isNotEmpty()) {
                if ($masterProduct->master_shop_id == 1 && $product->shop_id == 18) {
                    $masterProduct->updateQuietly([
                        'mismatch_with_seeder_detected' => true
                    ]);
                }

                $masterProduct->updateQuietly([
                    'mismatch_detected' => true
                ]);
                $product->updateQuietly([
                    'mismatch_with_master_detected' => true
                ]);
            } else {
                $masterProduct->updateQuietly([
                    'mismatch_detected' => false
                ]);
                $product->updateQuietly([
                    'mismatch_with_master_detected' => false
                ]);
                if ($masterProduct->master_shop_id == 1 && $product->shop_id == 18) {
                    $masterProduct->updateQuietly([
                        'mismatch_with_seeder_detected' => false
                    ]);
                }
            }
        }

        MasterShopHydrateNumberMismatches::dispatch($masterProduct->masterShop)->delay(now()->addSeconds(60));
    }

    public function getCommandSignature(): string
    {
        return 'master_asset:hydrate_mismatch {master_asset?}}';
    }

    public function asCommand(Command $command): void
    {
        if ($command->argument('master_asset')) {
            $masterAsset = MasterAsset::where('slug', $command->argument('master_asset'))->firstOrFail();
            $this->handle($masterAsset);

            return;
        }


        $total = MasterAsset::where('type', MasterAssetTypeEnum::PRODUCT)->count();
        $bar   = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('debug');
        $bar->start();

        MasterAsset::where('type', MasterAssetTypeEnum::PRODUCT)
            ->orderBy('id')
            ->chunkById(1000, function ($masterProducts) use ($bar) {
                foreach ($masterProducts as $masterProduct) {
                    $this->handle($masterProduct);
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();
    }


}
