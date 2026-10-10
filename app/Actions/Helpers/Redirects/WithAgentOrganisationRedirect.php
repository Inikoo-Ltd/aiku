<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;

/**
 * Staff of an agent open the agent's records from their own organisation's procurement pages,
 * never from supply chain or from the buying organisation, neither of which they can reach.
 */
trait WithAgentOrganisationRedirect
{
    protected function getAgentOrganisationForUser(?User $user, ?int $agentId): ?Organisation
    {
        if (!$user || !$agentId) {
            return null;
        }

        $organisation = Agent::find($agentId)?->organisation;

        if (!$organisation || !$user->authTo("procurement.$organisation->id.view")) {
            return null;
        }

        return $organisation;
    }
}
