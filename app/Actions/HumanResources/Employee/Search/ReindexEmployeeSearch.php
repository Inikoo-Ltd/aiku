<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 12:32:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\Search;

use App\Actions\HydrateModel;
use App\Models\HumanResources\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexEmployeeSearch extends HydrateModel
{
    public string $commandSignature = 'search:employees {organisations?*} {--s|slugs=}';


    public function handle(Employee $employee): void
    {
    }


    protected function getModel(string $slug): Employee
    {
        return Employee::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Employee::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Employees");
        $count = Employee::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Employee::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
