<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Feb 2025 10:12:17 Central Indonesia Time, Bali Airport, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Aurora;

use App\Actions\Traits\WithOrganisationSource;
use App\Models\Helpers\UniversalSearch;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class DeletePortfolioUniversalSearch
{
    use AsAction;
    use WithOrganisationSource;

    private int $count = 0;

    /**
     * @throws \Throwable
     */
    public function handle(Command $command): void
    {

        $count = UniversalSearch::where('model_type', 'Portfolio')->count();
        $command->info('Deleting '.$count.' Portfolio Universal Search');

        UniversalSearch::where('model_type', 'Portfolio')->chunk(100, function ($universalSearches) {
            foreach ($universalSearches as $universalSearch) {

                $universalSearch->forceDelete();
            }
        });
    }




    public function getCommandSignature(): string
    {
        return 'maintenance:delete_portfolio_universal_search';
    }

    public function asCommand(Command $command): int
    {
        try {
            $this->handle($command);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}
