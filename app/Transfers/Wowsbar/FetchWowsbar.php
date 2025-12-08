<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:52:25 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Wowsbar;

use App\Models\SysAdmin\Organisation;
use App\Transfers\SourceOrganisationService;

class FetchWowsbar
{
    protected Organisation $organisation;

    protected ?array $parsedData;

    protected ?object $wowModelData;

    protected SourceOrganisationService $organisationSource;

    public function __construct(SourceOrganisationService $organisationSource)
    {
        $this->organisationSource = $organisationSource;
        $this->organisation = $organisationSource->organisation;
        $this->parsedData = null;
        $this->wowModelData = null;
    }

    public function fetch(int $id): ?array
    {
        $this->wowModelData = $this->fetchData($id);

        if ($this->wowModelData) {
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
