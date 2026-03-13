<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 13 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateHealthRank;
use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateHealthRank;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateHealthRank;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitHydrateHealthRank;
use App\Actions\Goods\TradeUnitFamily\Hydrators\TradeUnitFamilyHydrateHealthRank;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateHealthRank;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateHealthRank;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateHealthRank;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateHealthRank;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateHealthRank;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateHealthRank
{
    use AsAction;

    public string $commandSignature = 'hydrate:health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('All health ranks updated.');
    }

    public function handle(): void
    {
        AssetHydrateHealthRank::run();
        CollectionHydrateHealthRank::run();
        ProductCategoryHydrateHealthRank::run();
        TradeUnitHydrateHealthRank::run();
        TradeUnitFamilyHydrateHealthRank::run();
        OrgStockHydrateHealthRank::run();
        OrgStockFamilyHydrateHealthRank::run();
        MasterAssetHydrateHealthRank::run();
        MasterCollectionHydrateHealthRank::run();
        MasterProductCategoryHydrateHealthRank::run();
    }
}
