<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateOrganisations
{
    use AsAction;

    public function handle(Group $group): void
    {

        $group->update(
            [
                'number_organisations' => $group->organisations()->where('type', OrganisationTypeEnum::SHOP)->count()
            ]
        );
    }

}
