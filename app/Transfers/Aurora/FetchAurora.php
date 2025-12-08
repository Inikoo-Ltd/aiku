<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\SysAdmin\Organisation;
use App\Transfers\SourceOrganisationService;

class FetchAurora
{
    use WithAuroraParsers;

    protected Organisation $organisation;

    protected ?array $parsedData;

    protected ?object $auroraModelData;

    protected SourceOrganisationService $organisationSource;

    public function __construct(SourceOrganisationService $organisationSource)
    {
        $this->organisationSource = $organisationSource;
        $this->organisation = $organisationSource->organisation;
        $this->parsedData = null;
        $this->auroraModelData = null;
    }

    public function fetch(int $id): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if ($this->auroraModelData) {
            $this->parseModel();
        }

        return $this->parsedData;
    }

    protected function fetchData($id): ?object
    {
        return null;
    }

    protected function parseModel(): void {}
}
