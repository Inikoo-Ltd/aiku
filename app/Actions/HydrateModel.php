<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:38:30 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions;

use App\Actions\Traits\WithOrganisationsArgument;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateModel
{
    use AsAction;
    use WithOrganisationsArgument;

    protected Organisation $organisation;

    protected function getModel(string $slug)
    {
        return null;
    }

    protected function getAllModels(): Collection
    {
        return new Collection;
    }

    public function asCommand(Command $command): int
    {
        $exitCode = 0;
        if (! $command->option('slugs')) {
            if ($command->argument('organisations')) {
                $this->organisation = $this->getOrganisations($command)->first();
            }

            $this->loopAll($command);
        } else {
            $model = $this->getModel($command->option('slugs'));
            if ($model) {
                $this->handle($model);
                $command->line(class_basename($model)." $model->name hydrated 💦");
            } else {
                $command->error('Model not found');
                $exitCode = 1;
            }
        }

        return $exitCode;
    }

    protected function loopAll(Command $command): void
    {
        $command->withProgressBar($this->getAllModels(), function ($model) {
            if ($model) {
                $this->handle($model);
            }
        });
        $command->info('');
    }
}
