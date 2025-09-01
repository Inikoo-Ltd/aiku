<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Web\Webpage\DeleteWebpage;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class DeleteWebpageTypeOperations
{
    use WithActionUpdate;
    use WithOrganisationSource;


    /**
     * @throws \Exception
     */
    public function handle(Webpage $webpage, Command $command): void
    {
        $command->line("Deleting webpage $webpage->slug");
        DeleteWebpage::run($webpage, true);
    }



    public string $commandSignature = 'repair:delete_webpages_type_operations';

    public function asCommand(Command $command): void
    {

        Webpage::where('type', 'operations')->orderBy('id')
            ->chunk(
                100,
                function (Collection $models) use ($command) {
                    foreach ($models as $model) {
                        $this->handle($model, $command);
                    }
                }
            );

    }

}
