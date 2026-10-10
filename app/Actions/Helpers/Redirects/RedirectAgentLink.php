<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Procurement\OrgAgent;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectAgentLink extends OrgAction
{
    use WithAgentOrganisationRedirect;

    public function handle(Agent $agent, ?User $user = null): RedirectResponse
    {
        if ($user && !$user->authTo('supply-chain.view') && $organisation = $this->getAgentOrganisationForUser($user, $agent->id)) {
            $orgAgent = OrgAgent::where('agent_id', $agent->id)->first();

            return Redirect::to($orgAgent
                ? route('grp.org.procurement.org_agents.show', [$organisation->slug, $orgAgent->slug])
                : route('grp.org.procurement.dashboard', [$organisation->slug]));
        }

        return Redirect::to(route('grp.supply-chain.agents.show', [$agent->slug]));
    }

    public function asController(Agent $agent, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($agent, $request->user());
    }
}
