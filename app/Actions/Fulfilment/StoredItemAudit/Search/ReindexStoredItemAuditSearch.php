<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 18:37:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItemAudit\Search;

use App\Actions\HydrateModel;
use App\Models\Fulfilment\StoredItemAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexStoredItemAuditSearch extends HydrateModel
{
    public string $commandSignature = 'search:stored_item_audits {organisations?*} {--s|slugs=}';


    public function handle(StoredItemAudit $storedItemAudit): void
    {
    }

    protected function getModel(string $slug): StoredItemAudit
    {
        return StoredItemAudit::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return StoredItemAudit::get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Stored Item Audits");
        $count = StoredItemAudit::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        StoredItemAudit::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
