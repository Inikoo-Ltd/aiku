<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:38:30 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions;

use App\Actions\Traits\WithOrganisationsArgument;
use App\Models\Organisation\Organisation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateModel
{
    use AsAction;
    use WithOrganisationsArgument;

    protected Organisation $organisation;


    protected function getModel(int $id): ?Model
    {
        return null;
    }

    protected function getAllModels(): Collection
    {
        return new Collection();
    }


    public function asCommand(Command $command): int
    {
        $organisations  = $this->getTenants($command);

        $exitCode = 0;

        foreach ($organisations as $organisation) {
            $result = (int)$organisation->execute(function () use ($command) {
                if ($command->option('id')) {
                    if ($model = $this->getModel($command->option('id'))) {
                        $this->handle($model);
                        $command->info('Done!');
                    }
                } else {
                    $this->loopAll($command);
                }
            });

            if ($result !== 0) {
                $exitCode = $result;
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
        $command->info("");
    }
}
