<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\SupplyChain\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectAgentLink extends OrgAction
{
    public function handle(Agent $agent): RedirectResponse
    {
        return Redirect::to(route('grp.supply-chain.agents.show', [$agent->slug]));
    }

    public function asController(Agent $agent, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($agent);
    }
}
