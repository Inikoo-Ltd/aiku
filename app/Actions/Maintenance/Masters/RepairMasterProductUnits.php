<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Dec 2025 16:04:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\OrgAction;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Actions\Traits\WithMasterAssetTradeUnits;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairMasterProductUnits extends OrgAction
{
    use AsAction;
    use WithMasterAssetTradeUnits;


    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        $seedShopID = null;
        if ($masterAsset->masterShop->slug == 'aw') {
            $seedShopID = 18;
        }

        if (!$seedShopID) {
            return $masterAsset;
            //abort(419, 'Seed shop not found');
        }


        if ($masterAsset->type != MasterAssetTypeEnum::PRODUCT) {
            return $masterAsset;
        }

        $masterAsset = ModelHydrateSingleTradeUnits::run($masterAsset);

        $units = null;
        $unit  = null;


        $seedProduct = $masterAsset->products()->where('shop_id', $seedShopID)->first();

        $sharedQuantity = $this->isAssortment($masterAsset)
            ? null
            : $this->getSharedTradeUnitQuantity(
                $masterAsset->tradeUnits->map(fn ($tradeUnit) => ['quantity' => $tradeUnit->pivot->quantity])->all()
            );

        if ($sharedQuantity !== null) {
            $units = $sharedQuantity;

            if ($seedProduct && $seedProduct->units != $units) {
                print "\n$seedProduct->code units $seedProduct->units do not match trade units $units ";
                $units = null;
            }
        } elseif ($seedProduct) {
            $units = $seedProduct->units;
        }
        $unit = $seedProduct?->unit;

        $dataToUpdate = [];
        if ($units) {
            $dataToUpdate['units'] = $units;

            $name = $this->stripUnitsPrefixFromName($masterAsset->name, $units);
            if ($name !== $masterAsset->name) {
                $dataToUpdate['name'] = $name;
            }
        }
        if ($unit) {
            $dataToUpdate['unit'] = $unit;
        }

        if ($units || $unit) {
            $masterAsset->update($dataToUpdate);
        }

        $discontinuedStates = [ProductStateEnum::DISCONTINUING, ProductStateEnum::DISCONTINUED];

        if ($units) {
            foreach ($masterAsset->products()->whereNotIn('state', $discontinuedStates)->get() as $product) {
                $productData = [];

                if ($product->units != $units) {
                    $productData['units'] = $units;
                }

                $name = $this->stripUnitsPrefixFromName($product->name, $units);
                if ($name !== $product->name) {
                    $productData['name'] = $name;
                }

                if ($productData) {
                    UpdateProduct::run($product, $productData);
                }
            }
        }

        return $masterAsset;
    }

    public const int MAX_COMPONENT_TRADE_UNITS = 3;

    /**
     * An assortment holds many different trade units that happen to share a quantity, so that
     * quantity is "so many of each design", not a pack size — a starter pack of 93 incense lines
     * at 6 each is not a pack of 6. A component set (bottle plus cap, cushion plus inner) is the
     * opposite and does carry a pack size. Codes name the starter packs, and past that the count
     * of distinct trade units separates them. Assortments keep the units of their seed product.
     */
    public function isAssortment(MasterAsset $masterAsset): bool
    {
        return preg_match('/-st$/i', $masterAsset->code)
            || $masterAsset->tradeUnits->count() > self::MAX_COMPONENT_TRADE_UNITS;
    }

    /**
     * Pack sizes used to live in the name because units was stuck at 1, giving names like
     * "192x 10ml Frosted Green Glass Dropper Bottle". Once units carries the pack size the prefix
     * is a duplicate, so it goes. Only a leading prefix matching the units is touched, dimensions
     * such as "45x45cm" or "17x22cm" stay.
     */
    public function stripUnitsPrefixFromName(?string $name, float $units): ?string
    {
        if ($name === null) {
            return null;
        }

        $quantity = rtrim(rtrim(sprintf('%.3f', $units), '0'), '.');

        return preg_replace('/^\s*'.preg_quote($quantity, '/').'\s*x\s+/i', '', $name) ?? $name;
    }

    public function getCommandSignature(): string
    {
        return 'repair:master_products_units {masterAsset?}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('masterAsset')) {
            $masterAsset = MasterAsset::where('slug', $command->argument('masterAsset'))->firstOrFail();
            $command->info("Fixing units value from trade units for $masterAsset->code");
            $this->handle($masterAsset);

            return 0;
        }

        $command->info('Fix units value from trade units');

        $chunkSize = 100;
        $count     = 0;

        $query = MasterAsset::where('is_main', true)->where('status', true);

        $totalCount = (clone $query)->count();

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat('aiku_eta');
        $bar->start();


        $query->chunk(
            $chunkSize,
            function ($masterAssets) use (&$count, $bar, $command) {
                foreach ($masterAssets as $asset) {
                    try {
                        $this->handle($asset);
                    } catch (Exception $e) {
                        $command->error("Error processing asset $asset->id: {$e->getMessage()}");
                    }
                    $count++;
                    //$bar->advance();
                }
            }
        );

        $bar->finish();
        $command->newLine();

        return 0;
    }
}
