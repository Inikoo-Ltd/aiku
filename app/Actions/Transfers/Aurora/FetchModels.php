<?php

/** @noinspection PhpUnused */

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 06 Sept 2022 16:15:47 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Traits\WithOrganisationsArgument;
use App\Actions\Traits\WithOrganisationSource;
use App\Transfers\SourceOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchModels
{
    use AsAction;
    use WithOrganisationsArgument;
    use WithOrganisationSource;

    public string $commandSignature = 'fetch:models {organisations?*}';

    public function handle(SourceOrganisationService $organisationSource): void
    {
        FetchAuroraShippers::dispatch($organisationSource);
        FetchAuroraShops::dispatch($organisationSource);
        FetchAuroraEmployees::dispatch($organisationSource);
        Bus::chain([
            FetchAuroraWarehouses::makeJob($organisationSource),
            FetchAuroraWarehouseAreas::makeJob($organisationSource),
            FetchAuroraLocations::makeJob($organisationSource),
            FetchAuroraStocks::makeJob($organisationSource),
        ])->dispatch();
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        $organisations = $this->getOrganisations($command);
        $exitCode = 0;

        foreach ($organisations as $organisation) {

            $organisationSource = $this->getOrganisationSource($organisation);
            $organisationSource->initialisation($organisation);

            $this->handle($organisationSource);

        }

        return $exitCode;
    }
}
