<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Feb 2026 12:26:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\CloneCollections;
use App\Models\Catalogue\Collection;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CopyShopCollectionToMaster
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(Collection $collection): void
    {
        $shop             = $collection->shop;
        $masterCollection = CloneCollections::make()->upsertMasterCollection($shop->masterShop, $collection);

        foreach ($shop->masterShop->shops as $_shops) {
            if ($_shops->id != $shop->id) {
                CloneCollections::make()->upsertCollection($_shops, $masterCollection, true);
            }
        }
    }

    public function getCommandSignature(): string
    {
        return 'repair:copy_shop_collection_to_master {collection}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $collection = Collection::where('slug', $command->argument('collection'))->firstOrFail();

        $this->handle($collection);

        return 0;
    }

}
