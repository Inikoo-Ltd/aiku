<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jul 2023 13:31:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use App\Transfers\WowsbarOrganisationService;
use Exception;
use Illuminate\Support\Arr;

trait WithOrganisationSource
{
    /**
     * @throws \Exception
     */
    public function getOrganisationSource(Organisation $organisation): AuroraOrganisationService|WowsbarOrganisationService|null
    {
        $sourceType = Arr::get($organisation->source, 'type');
        if (! $sourceType) {
            throw new Exception('Organisation dont have source');
        }

        $organisationSource = match (Arr::get($organisation->source, 'type')) {
            'Aurora' => new AuroraOrganisationService,
            'Wowsbar' => new WowsbarOrganisationService,
            default => null
        };

        if (! $organisationSource) {
            throw new Exception("Organisation source $sourceType is not supported");
        }

        return $organisationSource;
    }
}
