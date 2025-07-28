<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Collection\CollectionProductStatusEnum;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Models\Catalogue\Collection as CatalogueCollection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairCollectionStates
{
    use WithActionUpdate;


    public function handle(CatalogueCollection $collection): void
    {
        $data = [];
        if($collection->state == CollectionStateEnum::DISCONTINUING) {
            $data = [
                'state' => CollectionStateEnum::ACTIVE,
                'product_status' => CollectionProductStatusEnum::DISCONTINUING
            ];
        } elseif ($collection->state == CollectionStateEnum::DISCONTINUED) {
            $data = [
                'state' => CollectionStateEnum::INACTIVE,
                'product_status' => CollectionProductStatusEnum::DISCONTINUED
            ];
        }

        $this->update($collection, $data);
    }


    public string $commandSignature = 'repair:collection_states';

    public function asCommand(Command $command): void
    {
            $count = CatalogueCollection::whereIn('state', ['discontinued', 'discontinuing'])->count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            CatalogueCollection::orderBy('id')->whereIn('state', ['discontinued', 'discontinuing'])
                ->chunk(100, function (Collection $models) use ($bar) {
                    foreach ($models as $model) {
                        $this->handle($model);
                        $bar->advance();
                    }
                });
        }
    

}
