<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 11:12:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrgAgents implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $numberAgents = $organisation->orgAgents()->count();
        $activeAgents = $organisation->orgAgents()->where('org_agents.status', true)->count();

        $stats = [
            'number_org_agents' => $numberAgents,
            'number_active_org_agents' => $activeAgents,
            'number_archived_org_agents' => $numberAgents - $activeAgents,
        ];

        $organisation->procurementStats()->update($stats);
    }
}
