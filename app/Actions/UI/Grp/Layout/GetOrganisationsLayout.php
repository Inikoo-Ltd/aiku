<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:12:46 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Layout;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetOrganisationsLayout
{
    use AsAction;

    public function handle(User $user, Organisation $organisation): array
    {
        return match ($organisation->type) {
            OrganisationTypeEnum::AGENT => GetAgentOrganisationNavigation::run($user, $organisation),
            OrganisationTypeEnum::SHOP => GetOrganisationNavigation::run($user, $organisation),
            OrganisationTypeEnum::DIGITAL_AGENCY => GetDigitalAgencyOrganisationNavigation::run($user, $organisation),
            default => [],
        };
    }
}
