<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\OrgSupplier\Search;

use App\Actions\HydrateModel;
use App\Models\Procurement\OrgSupplier;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexOrgSupplierSearch extends HydrateModel
{
    public string $commandSignature = 'search:org_suppliers {organisations?*} {--s|slugs=} ';

    public function handle(OrgSupplier $orgSupplier): void
    {
        OrgSupplierRecordSearch::run($orgSupplier);
    }

    protected function getModel(string $slug): OrgSupplier
    {
        return OrgSupplier::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return OrgSupplier::all();
    }

    protected function loopAll(Command $command): void
    {
        $command->info('Reindex Org Suppliers');
        $count = OrgSupplier::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        OrgSupplier::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');
    }
}
