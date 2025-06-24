<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Jun 2025 23:38:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Api\Group\Organisation;

use App\Actions\OrgAction;
use App\Http\Resources\Api\SysAdmin\OrganisationApiResource;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class ShowApiOrganisation extends OrgAction
{
    public function handle(Organisation $organisation): Organisation
    {
        return $organisation;
    }

    public function authorize(ActionRequest $request): bool
    {
        $organisation = $request->route('organisation');
        if ($organisation->group_id == group()->id) {
            return true;
        }
        return false;
    }

    public function asController(Organisation $organisation, ActionRequest $request): OrganisationApiResource
    {
        $this->initialisation($organisation, $request);

        return OrganisationApiResource::make($this->handle($organisation));
    }

}
