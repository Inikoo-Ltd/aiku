<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentOrder;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Procurement\OrgAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Opens the agent order being prepared, or numbers a new one. Its supplier orders are created as
 * products are added to it.
 */
class StoreAgentOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;

    private OrgAgent $orgAgent;

    public function handle(OrgAgent $orgAgent): string
    {
        return ResolveAgentOrderReference::run($orgAgent);
    }

    public function asController(OrgAgent $orgAgent, ActionRequest $request): string
    {
        $this->orgAgent = $orgAgent;
        $this->initialisation($orgAgent->organisation, $request);

        return $this->handle($orgAgent);
    }

    public function htmlResponse(string $agentOrderReference): RedirectResponse
    {
        return Redirect::route('grp.org.procurement.org_agents.show.agent_orders.show', [
            $this->orgAgent->organisation->slug,
            $this->orgAgent->slug,
            $agentOrderReference,
        ]);
    }
}
