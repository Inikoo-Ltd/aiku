<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterCollection;

use App\Actions\Catalogue\Collection\DeleteCollection;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterCollections;
use App\Models\Masters\MasterCollection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteMasterCollection extends GrpAction
{
    use AsAction;
    use WithAttributes;

    private ?MasterCollection $masterCollection;

    /**
     * @throws \Throwable
     */
    public function handle(MasterCollection $masterCollection, bool $forceDelete = false, ?Command $command = null): void
    {
        DB::transaction(function () use ($masterCollection, $forceDelete, $command) {
            $masterCollection
                ->childrenCollections
                ->each(function ($collection) use ($forceDelete, $command) {
                    $command?->line('Deleting collection '.$collection->name);
                    DeleteCollection::make()->action($collection, $forceDelete, $command);
                });

            if ($forceDelete) {
                DB::table('model_has_master_collections')->where('master_collection_id', $masterCollection->id)->delete();
                DB::table('master_collection_has_models')->where('master_collection_id', $masterCollection->id)->delete();
                DB::table('master_collection_sales_intervals')->where('master_collection_id', $masterCollection->id)->delete();

                if ($masterCollection->stats) {
                    $masterCollection->stats->delete();
                }


                if ($masterCollection->orderingStats) {
                    $masterCollection->orderingStats->delete();
                }

                $masterCollection->forceDelete();
            } else {
                $masterCollection->delete();
            }
        });

        MasterShopHydrateMasterCollections::dispatch($masterCollection->masterShop);
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterCollection $masterCollection, bool $forceDelete = false): void
    {
        $this->masterCollection = $masterCollection;
        $this->handle($masterCollection, $forceDelete);
    }

    /**
     * @throws \Throwable
     */
    public function asController(MasterCollection $masterCollection, ActionRequest $request): void
    {
        $this->masterCollection = $masterCollection;
        $this->initialisation($masterCollection->group, $request);

        $forceDelete = $request->boolean('force_delete');

        $this->handle($masterCollection, $forceDelete);
    }

    public function getCommandSignature(): string
    {
        return 'master-collection:delete {masterCollectionId} {--force_delete : Force delete the master collection}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): void
    {
        $masterCollection = MasterCollection::where('slug', $command->argument('masterCollectionId'))->firstOrFail();
        $command->line('Deleting master collection '.$masterCollection->name);
        $this->handle($masterCollection, $command->option('force_delete'), $command);
    }

}
