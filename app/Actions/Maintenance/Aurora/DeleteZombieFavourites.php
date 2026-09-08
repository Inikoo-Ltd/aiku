<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Feb 2025 10:12:17 Central Indonesia Time, Bali Airport, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Aurora;

use App\Actions\Traits\WithOrganisationSource;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;
use Laravel\Nightwatch\Facades\Nightwatch;

class DeleteZombieFavourites
{
    use AsAction;
    use WithOrganisationSource;

    private int $count = 0;

    /**
     * @throws \Throwable
     */
    public function handle(?Command $command): void
    {
        foreach (Organisation::where('type', OrganisationTypeEnum::SHOP)->get() as $organisation) {
            $organisationSource = $this->getOrganisationSource($organisation);
            $organisationSource->initialisation($organisation);
            $this->deleteZombieFavourites($organisationSource, $command);
        }
    }

    public function deleteZombieFavourites(AuroraOrganisationService $organisationSource, ?Command $command): void
    {
        foreach ($organisationSource->organisation->favourites as $favourite) {
            $sourceData = explode(':', $favourite->source_id);
            if (!DB::connection('aurora')->table('Customer Favourite Product Fact')
                ->where('Customer Favourite Product Key', $sourceData[1])->exists()) {
                $this->count++;
                $command->info($this->count.' Deleting favourite '.$favourite->id.' '.$organisationSource->organisation->code);
                $favourite->delete();
            }
        }
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:delete_zombie_favourites';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        try {
            $this->handle($command);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}
