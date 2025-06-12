<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Jun 2025 12:31:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance;

use App\Actions\Catalogue\Collection\DeleteCollection;
use App\Actions\Catalogue\Collection\HydrateCollection;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Collection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairDeleteEmptyFetchedCollections
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(Collection $collection, Command $command): void
    {
        HydrateCollection::run($collection);
        $collection->refresh();
        if ($collection->stats->number_products == 0 && $collection->stats->number_families == 0) {

            if (!$collection->webpage) {
                $command->line("Deleting empty collection {$collection->source_id} - {$collection->name}");
                DeleteCollection::make()->action($collection, true);
            }

        }
    }


    public string $commandSignature = 'repair:delete-empty-fetched-collections';

    public function asCommand(Command $command): void
    {
        $collectionIds = DB::table('collections')->select('id')->whereNotNull('source_id')->get();


        foreach ($collectionIds as $collectionId) {
            $collection = Collection::find($collectionId->id);
            if ($collection) {
                $this->handle($collection, $command);
            }
        }
    }

}
