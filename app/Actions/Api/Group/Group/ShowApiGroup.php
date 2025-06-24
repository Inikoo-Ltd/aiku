<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Jun 2025 23:38:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Api\Group\Group;

use App\Actions\OrgAction;
use App\Http\Resources\Api\SysAdmin\GroupApiResource;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;

class ShowApiGroup extends OrgAction
{
    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): GroupApiResource
    {
        $this->initialisationFromGroup(group(), $request);

        return GroupApiResource::make($this->handle($this->group));
    }

}
