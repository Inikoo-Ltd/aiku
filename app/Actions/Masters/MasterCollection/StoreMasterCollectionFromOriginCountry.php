<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\Catalogue\Collection\SyncIndirectProductsToCollection;
use App\Models\Helpers\Country;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreMasterCollectionFromOriginCountry
{
    use AsAction;

    public string $commandSignature = 'master_collection:from_origin_country {master_shop : master shop slug} {country : ISO2 country code} {--code=} {--name=}';

    public function handle(MasterShop $masterShop, Country $country, string $code, string $name): MasterCollection
    {
        $masterCollection = MasterCollection::where('master_shop_id', $masterShop->id)->where('code', $code)->first()
            ?? StoreMasterCollection::make()->action($masterShop, ['code' => $code, 'name' => $name]);

        foreach ($masterCollection->masterFamilies as $masterFamily) {
            DetachMasterModelFromMasterCollection::make()->action($masterCollection, $masterFamily);
        }
        foreach ($masterCollection->childrenCollections as $collection) {
            SyncIndirectProductsToCollection::run($collection);
        }
        foreach ($masterCollection->masterProducts()->where(fn ($query) => $query->whereNot('origin_country_id', $country->id)->orWhereNull('origin_country_id'))->get() as $foreignMasterAsset) {
            DetachMasterModelFromMasterCollection::make()->action($masterCollection, $foreignMasterAsset);
        }

        $masterAssetIds = $masterShop->masterAssets()
            ->where('origin_country_id', $country->id)
            ->where('status', true)
            ->pluck('id')
            ->all();

        AttachModelsToMasterCollection::make()->action($masterCollection, ['products' => $masterAssetIds]);

        return $masterCollection;
    }

    public function asCommand(Command $command): int
    {
        $masterShop = MasterShop::where('slug', $command->argument('master_shop'))->firstOrFail();
        $country    = Country::where('code', strtoupper($command->argument('country')))->firstOrFail();
        $code       = $command->option('code') ?? 'made-in-'.strtolower($country->code);
        $name       = $command->option('name') ?? 'Made in '.$country->name;

        $masterCollection = $this->handle($masterShop, $country, $code, $name);

        $command->info("$masterCollection->slug: ".$masterCollection->masterProducts()->count()." master products, ".$masterCollection->childrenCollections()->count().' shop collections');

        return 0;
    }
}
